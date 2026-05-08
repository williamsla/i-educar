<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrai CPFs e dados correlatos de PDFs do relatório "Acompanhamento de cidadãos vinculados" do eSUS
 * e verifica quais não possuem matrícula ativa no ano letivo informado (pmieducar.matricula).
 */
class EsusPdfCpfService
{
    /**
     * Situações de matrícula consideradas "concluiu a etapa" (aprovado).
     *
     * @see \App_Model_MatriculaSituacao
     */
    private const APROVADO_CONCLUIU_ETAPA = [1, 8, 10, 12, 13];

    /**
     * Situação transferido.
     */
    private const MATRICULA_TRANSFERIDO = 4;

    /**
     * Considera transferências em matrículas cujo ano letivo seja >= (ano verificado - N).
     */
    private const ANOS_LOOKBACK_TRANSFERENCIA = 5;

    /**
     * Padrão para CPF no formato XXX.XXX.XXX-XX (como no relatório eSUS).
     */
    private const CPF_PATTERN = '/\d{3}\.\d{3}\.\d{3}-\d{2}/';

    /**
     * Linhas que não devem ser interpretadas como nome do cidadão.
     */
    private const NOME_EXCLUDE_PATTERN = '/^(Cidadão|FILTROS|Equipe|Microárea|Sexo|Idade|Data de nasc|Endereço|Telefone|Última|MINISTÉRIO|ESTADO|MUNICÍCIO|UNIDADE|Pág\.|Impresso|Acompanhamento|eSUS|SAÚDE)/iu';

    /**
     * Extrai todos os CPFs únicos do texto do PDF (formato XXX.XXX.XXX-XX).
     *
     * @return string[]
     */
    public function extractCpfsFromPdf(string $pdfPath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        return $this->extractCpfsFromText($text);
    }

    /**
     * Extrai CPFs únicos de um texto (formato XXX.XXX.XXX-XX).
     *
     * @return string[]
     */
    public function extractCpfsFromText(string $text): array
    {
        $matches = [];
        preg_match_all(self::CPF_PATTERN, $text, $matches);
        $cpfs = $matches[0] ?? [];

        return array_values(array_unique($cpfs));
    }

    /**
     * Extrai, para cada CPF, nome (linha anterior) e data de nascimento a partir do texto do PDF.
     *
     * @return array<string, array{cpf: string, nome: string, data_nascimento: string}>
     *         Chave = CPF formatado
     */
    public function extrairRegistrosDoTexto(string $text): array
    {
        $pattern = self::CPF_PATTERN;
        preg_match_all($pattern.'u', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) {
            return [];
        }

        $registros = [];
        $cpfMatches = $matches[0];
        $count = count($cpfMatches);

        for ($i = 0; $i < $count; $i++) {
            $cpf = $cpfMatches[$i][0];
            $offset = $cpfMatches[$i][1];
            $prevEnd = $i > 0 ? $cpfMatches[$i - 1][1] + strlen($cpfMatches[$i - 1][0]) : 0;
            $nextStart = $i + 1 < $count ? $cpfMatches[$i + 1][1] : strlen($text);

            $before = substr($text, $prevEnd, $offset - $prevEnd);
            $after = substr($text, $offset + strlen($cpf), $nextStart - ($offset + strlen($cpf)));

            $nome = $this->extrairNomeAntesDoCpf($before);
            $dataNasc = $this->extrairDataNascimentoAntesDoCpf($before, $nome);
            if ($dataNasc === '') {
                $dataNasc = $this->extrairDataNascimentoAposCpf($after);
            }

            $registros[$cpf] = [
                'cpf' => $cpf,
                'cns' => '',
                'nome' => $nome,
                'data_nascimento' => $dataNasc,
                'endereco_relatorio' => '',
                'ultima_atualizacao_cadastral' => '',
            ];
        }

        return $registros;
    }

    /**
     * Data da coluna à direita do relatório eSUS (ex.: "21/05/2025 CDS") — não é nascimento.
     */
    private function dataEColunaCds(string $trecho, int $offsetData, int $lenData): bool
    {
        $apos = substr($trecho, $offsetData + $lenData, 24);

        return (bool) preg_match('/^\s*CDS\b/iu', $apos);
    }

    /**
     * Linhas do texto após a linha do nome do cidadão (evita datas da linha anterior, ex. coluna CDS).
     */
    private function trechoAposLinhaDoNome(string $before, string $nome): string
    {
        if ($nome === '') {
            return '';
        }

        $before = str_replace(["\r\n", "\r"], "\n", $before);
        $lines = array_map('trim', explode("\n", $before));
        $idxNome = -1;
        foreach ($lines as $i => $line) {
            if ($line === $nome) {
                $idxNome = $i;
            }
        }
        if ($idxNome < 0) {
            return '';
        }

        $restLines = array_slice($lines, $idxNome + 1);

        return trim(implode("\n", $restLines));
    }

    /**
     * Alguns PDFs trazem nome → data → CPF; só considera datas entre o nome e o CPF (não o bloco inteiro anterior).
     */
    private function extrairDataNascimentoAntesDoCpf(string $before, string $nome): string
    {
        $trecho = $this->trechoAposLinhaDoNome($before, $nome);
        if ($trecho === '') {
            return '';
        }

        if (! preg_match_all('/\b(\d{2}\/\d{2}\/\d{4})\b/', $trecho, $dm, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $ultima = '';
        foreach ($dm[0] as $pair) {
            $d = $pair[0];
            $off = $pair[1];
            if (! $this->dataPlausivelNascimento($d) || $this->dataEColunaCds($trecho, $off, strlen($d))) {
                continue;
            }
            $ultima = $d;
        }

        return $ultima;
    }

    /**
     * Layout típico após o CPF: sexo, idade ("X anos e Y meses"), data de nascimento, endereço…
     * Ignora datas seguidas de "CDS" (outra coluna do relatório eSUS).
     */
    private function extrairDataNascimentoAposCpf(string $after): string
    {
        $after = trim($after);
        if ($after === '') {
            return '';
        }

        $rest = preg_replace(
            '/^(Feminino|Masculino|Outro)\s+/iu',
            '',
            $after
        );
        $rest = preg_replace(
            '/^\d+\s+anos(?:\s+e\s+\d+\s+meses)?\s+/iu',
            '',
            $rest
        );

        if (! preg_match_all('/\b(\d{2}\/\d{2}\/\d{4})\b/', $rest, $dm, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        foreach ($dm[0] as $pair) {
            $d = $pair[0];
            $off = $pair[1];
            if ($this->dataPlausivelNascimento($d) && ! $this->dataEColunaCds($rest, $off, strlen($d))) {
                return $d;
            }
        }

        return '';
    }

    private function dataPlausivelNascimento(string $dataBr): bool
    {
        $parts = explode('/', $dataBr);
        if (count($parts) !== 3) {
            return false;
        }
        $y = (int) $parts[2];

        return $y >= 1900 && $y <= (int) date('Y');
    }

    private function extrairNomeAntesDoCpf(string $before): string
    {
        $before = str_replace(["\r\n", "\r"], "\n", $before);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $before)), fn ($l) => $l !== ''));

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if (preg_match(self::CPF_PATTERN, $line)) {
                continue;
            }
            if (preg_match(self::NOME_EXCLUDE_PATTERN, $line)) {
                continue;
            }
            if (strlen($line) < 3 || strlen($line) > 200) {
                continue;
            }
            // Nome: letras, espaços, apóstrofo, hífen, ponto (iniciais)
            if (preg_match('/^[\p{L}\s\'.´`^-]+$/u', $line)) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Indica se existe aluno com o CPF (cadastro.fisica) com matrícula ativa no ano letivo.
     */
    public function possuiMatriculaAtivaNoAno(string $cpfNormalizado, int $anoLetivo): bool
    {
        return $this->possuiMatriculaAtivaNoAnoPorCpfOuCns($cpfNormalizado, '', $anoLetivo);
    }

    /**
     * Matrícula ativa no ano por CPF (cadastro.fisica.cpf) ou CNS (15 dígitos em cadastro.fisica.sus).
     */
    private function possuiMatriculaAtivaNoAnoPorCpfOuCns(string $cpfNormalizado, string $cnsApenasDigitos, int $anoLetivo): bool
    {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns) {
            return false;
        }

        return DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->where(function ($q) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                if ($temCpf && $temCns) {
                    $q->where(function ($w) use ($cpfNormalizado, $cnsApenasDigitos) {
                        $w->where('f.cpf', $cpfNormalizado)
                            ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                    });
                } elseif ($temCpf) {
                    $q->where('f.cpf', $cpfNormalizado);
                } else {
                    $q->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                }
            })
            ->where('m.ano', $anoLetivo)
            ->where('m.ativo', 1)
            ->where('a.ativo', 1)
            ->exists();
    }

    /**
     * @param  array<string, array<string, mixed>>  $registrosPorCpf
     * @return list<array<string, mixed>>
     */
    public function getItensSemMatriculaNoAno(array $registrosPorCpf, int $anoLetivo): array
    {
        $lista = [];

        foreach ($registrosPorCpf as $_chave => $dados) {
            $cpfNormalizado = idFederal2int((string) ($dados['cpf'] ?? ''));
            if ($cpfNormalizado === null) {
                $cpfNormalizado = '';
            }
            $cnsDigitos = preg_replace('/\D+/', '', (string) ($dados['cns'] ?? '')) ?? '';
            if (strlen($cnsDigitos) !== 15) {
                $cnsDigitos = '';
            }
            if ($cpfNormalizado === '' && $cnsDigitos === '') {
                $lista[] = $this->normalizarLinhaItem($dados);

                continue;
            }
            if (! $this->possuiMatriculaAtivaNoAnoPorCpfOuCns($cpfNormalizado, $cnsDigitos, $anoLetivo)) {
                $lista[] = $this->normalizarLinhaItem($dados);
            }
        }

        $lista = $this->aplicarRegrasListaEsus($lista, $anoLetivo);

        return $this->enriquecerEnderecoAluno($lista);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizarLinhaItem(array $dados): array
    {
        return array_merge([
            'cns' => '',
            'endereco_relatorio' => '',
            'ultima_atualizacao_cadastral' => '',
        ], $dados);
    }

    /**
     * Regra 2: não exibir quem já concluiu o 9º ano (com aprovação).
     * Regra 3: exibir se houve transferência em anos recentes e data da transferência é anterior
     * à "Última atualização cadastral" do e-SUS (sobrepõe a regra 2).
     *
     * @param  list<array<string, mixed>>  $itens
     * @return list<array<string, mixed>>
     */
    private function aplicarRegrasListaEsus(array $itens, int $anoLetivo): array
    {
        $filtrados = [];

        foreach ($itens as $dados) {
            $cpfNormalizado = idFederal2int((string) ($dados['cpf'] ?? ''));
            if ($cpfNormalizado === null) {
                $cpfNormalizado = '';
            }
            $cnsDigitos = preg_replace('/\D+/', '', (string) ($dados['cns'] ?? '')) ?? '';
            if (strlen($cnsDigitos) !== 15) {
                $cnsDigitos = '';
            }
            if ($cpfNormalizado === '' && $cnsDigitos === '') {
                $filtrados[] = $dados;

                continue;
            }

            $ultimaEsus = $this->parseDataBr((string) ($dados['ultima_atualizacao_cadastral'] ?? ''));
            $incluirPorTransferencia = $this->possuiTransferenciaRecenteAntesUltimaAtualizacaoEsus(
                $cpfNormalizado,
                $cnsDigitos,
                $ultimaEsus,
                $anoLetivo
            );

            if ($incluirPorTransferencia) {
                $filtrados[] = $dados;

                continue;
            }

            if ($this->concluiuNonoAnoFundamental($cpfNormalizado, $cnsDigitos)) {
                continue;
            }

            $filtrados[] = $dados;
        }

        return $filtrados;
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array<string, mixed>>
     */
    private function enriquecerEnderecoAluno(array $itens): array
    {
        foreach ($itens as $i => $dados) {
            $cpfNormalizado = idFederal2int((string) ($dados['cpf'] ?? ''));
            if ($cpfNormalizado === null) {
                $cpfNormalizado = '';
            }
            $cnsDigitos = preg_replace('/\D+/', '', (string) ($dados['cns'] ?? '')) ?? '';
            if (strlen($cnsDigitos) !== 15) {
                $cnsDigitos = '';
            }
            $cadastro = ($cpfNormalizado !== '' || $cnsDigitos !== '')
                ? $this->obterEnderecoCadastroPorCpfOuCns($cpfNormalizado, $cnsDigitos)
                : '';
            $cadastro = $this->sanitizarTextoEndereco($cadastro);
            $relatorio = $this->sanitizarTextoEndereco((string) ($dados['endereco_relatorio'] ?? ''));
            if ($this->enderecoPossuiConteudoUtil($cadastro)) {
                $itens[$i]['endereco'] = $cadastro;
            } elseif ($this->relatorioEnderecoConsideradoPreenchido($relatorio)) {
                $itens[$i]['endereco'] = $relatorio;
            } else {
                $itens[$i]['endereco'] = '—';
            }
        }

        return $itens;
    }

    /**
     * Texto do e-SUS: aceita endereços longos mesmo com encoding imperfeito (não exige \pL).
     */
    private function relatorioEnderecoConsideradoPreenchido(string $valor): bool
    {
        $t = $this->sanitizarTextoEndereco($valor);
        if (strlen($t) < 4) {
            return false;
        }

        return ! (bool) preg_match('/^[—\-–\s|;\.,_\/º°]+$/u', $t);
    }

    /**
     * Excel costuma gravar CSV em Windows-1252; se não for UTF-8 válido, converte.
     */
    private function garantirUtf8ConteudoCsv(string $conteudo): string
    {
        if ($conteudo === '') {
            return $conteudo;
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($conteudo, 'UTF-8')) {
            return $conteudo;
        }
        $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        if ($convertido !== false && $convertido !== '') {
            return $convertido;
        }
        $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');

        return ($convertido !== false && $convertido !== '') ? $convertido : $conteudo;
    }

    /**
     * Localiza a coluna que contém CPF ou CNS na linha (desvio de colunas por `;` extra no meio do texto).
     *
     * @param  list<string>  $colunas
     */
    private function buscarIndiceColunaIdentificadorCpfOuCns(array $colunas): ?int
    {
        foreach ($colunas as $j => $celula) {
            if ($this->parseIdentificadorColunaCpfCnsCsv((string) $celula)['chave'] !== '') {
                return (int) $j;
            }
        }

        return null;
    }

    /**
     * Remove espaços em branco unicode (incl. NBSP) e caracteres de controle nas pontas.
     */
    private function sanitizarTextoEndereco(string $valor): string
    {
        return preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $valor) ?? '';
    }

    /**
     * Indica se o texto tem conteúdo útil (letra ou número), descartando só separadores "—", etc.
     */
    private function enderecoPossuiConteudoUtil(string $valor): bool
    {
        $t = $this->sanitizarTextoEndereco($valor);

        return $t !== '' && (bool) preg_match('/[\pL\pN]/u', $t);
    }

    private function parseDataBr(string $data): ?Carbon
    {
        $data = trim($data);
        if ($data === '' || ! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $data)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Endereço principal do cadastro (person_has_place + view addresses).
     */
    private function obterEnderecoCadastroPorCpfOuCns(string $cpfNormalizado, string $cnsApenasDigitos): string
    {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns) {
            return '';
        }

        $bindings = [];
        $wherePessoa = '';
        if ($temCpf && $temCns) {
            $wherePessoa = '(f.cpf = ? OR regexp_replace(coalesce(f.sus::text, \'\'), \'[^0-9]\', \'\', \'g\') = ?)';
            $bindings[] = $cpfNormalizado;
            $bindings[] = $cnsApenasDigitos;
        } elseif ($temCpf) {
            $wherePessoa = 'f.cpf = ?';
            $bindings[] = $cpfNormalizado;
        } else {
            $wherePessoa = "regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?";
            $bindings[] = $cnsApenasDigitos;
        }

        $row = DB::selectOne(
            "select trim(both ' ' from concat_ws(' — ',
                nullif(trim(a.address), ''),
                nullif(trim(cast(a.number as varchar)), ''),
                nullif(trim(a.neighborhood), ''),
                nullif(trim(a.city), ''),
                nullif(trim(a.postal_code), '')
            )) as linha
            from cadastro.fisica f
            inner join pmieducar.aluno al on al.ref_idpes = f.idpes and al.ativo = 1
            left join person_has_place php on php.person_id = f.idpes
            left join addresses a on a.id = php.place_id
            where {$wherePessoa}
            order by php.type asc nulls last
            limit 1",
            $bindings
        );

        $linha = is_object($row) ? (string) ($row->linha ?? '') : '';
        $linha = $this->sanitizarTextoEndereco($linha);

        return $this->enderecoPossuiConteudoUtil($linha) ? $linha : '';
    }

    private function concluiuNonoAnoFundamental(string $cpfNormalizado, string $cnsApenasDigitos): bool
    {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns) {
            return false;
        }

        $q = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as al', 'al.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->join('pmieducar.serie as s', 's.cod_serie', '=', 'm.ref_ref_cod_serie')
            ->where(function ($w) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                if ($temCpf && $temCns) {
                    $w->where(function ($x) use ($cpfNormalizado, $cnsApenasDigitos) {
                        $x->where('f.cpf', $cpfNormalizado)
                            ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                    });
                } elseif ($temCpf) {
                    $w->where('f.cpf', $cpfNormalizado);
                } else {
                    $w->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                }
            })
            ->where('al.ativo', 1)
            ->where('m.ativo', 1)
            ->whereIn('m.aprovado', self::APROVADO_CONCLUIU_ETAPA)
            ->whereNotNull('m.ref_ref_cod_serie')
            ->where(function ($q) {
                $q->where('s.etapa_curso', 9)
                    ->orWhereRaw('lower(s.nm_serie) like ?', ['%nono%'])
                    ->orWhereRaw('lower(s.nm_serie) like ?', ['%9º%'])
                    ->orWhereRaw('lower(s.nm_serie) like ?', ['%9 ano%'])
                    ->orWhereRaw('lower(s.nm_serie) like ?', ['%9°%']);
            });

        return $q->exists();
    }

    private function possuiTransferenciaRecenteAntesUltimaAtualizacaoEsus(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        ?Carbon $ultimaAtualizacaoEsus,
        int $anoLetivo
    ): bool {
        if ($ultimaAtualizacaoEsus === null) {
            return false;
        }

        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns) {
            return false;
        }

        $anoMin = $anoLetivo - self::ANOS_LOOKBACK_TRANSFERENCIA;

        $q = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as al', 'al.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->where(function ($w) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                if ($temCpf && $temCns) {
                    $w->where(function ($x) use ($cpfNormalizado, $cnsApenasDigitos) {
                        $x->where('f.cpf', $cpfNormalizado)
                            ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                    });
                } elseif ($temCpf) {
                    $w->where('f.cpf', $cpfNormalizado);
                } else {
                    $w->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                }
            })
            ->where('al.ativo', 1)
            ->where('m.ativo', 1)
            ->where('m.aprovado', self::MATRICULA_TRANSFERIDO)
            ->whereNotNull('m.data_cancel')
            ->where('m.ano', '>=', $anoMin)
            ->whereRaw('DATE(m.data_cancel) < ?', [$ultimaAtualizacaoEsus->toDateString()]);

        return $q->exists();
    }

    /**
     * Processa o PDF: extrai registros e retorna os que não têm matrícula ativa no ano letivo, com nome e data de nascimento.
     *
     * @return array{
     *     cpfs_extraidos: int,
     *     ano_letivo: int,
     *     cpfs_nao_cadastrados: list<array{cpf: string, nome: string, data_nascimento: string}>,
     *     erro?: string
     * }
     */
    public function processarPdf(string $pdfPath, int $anoLetivo): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();
            $registros = $this->extrairRegistrosDoTexto($text);
            $semMatricula = $this->getItensSemMatriculaNoAno($registros, $anoLetivo);

            return [
                'cpfs_extraidos' => count($registros),
                'ano_letivo' => $anoLetivo,
                'cpfs_nao_cadastrados' => $semMatricula,
            ];
        } catch (Throwable $e) {
            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => $anoLetivo,
                'cpfs_nao_cadastrados' => [],
                'erro' => $e->getMessage(),
            ];
        }
    }

    /**
     * Processa CSV do eSUS (Acompanhamento de cidadãos vinculados): extrai registros e
     * retorna os que não têm matrícula ativa no ano letivo.
     *
     * @return array{
     *     cpfs_extraidos: int,
     *     ano_letivo: int,
     *     cpfs_nao_cadastrados: list<array{cpf: string, nome: string, data_nascimento: string}>,
     *     erro?: string
     * }
     */
    public function processarCsv(string $csvPath, int $anoLetivo): array
    {
        try {
            $conteudo = (string) file_get_contents($csvPath);
            $registros = $this->extrairRegistrosDoCsv($conteudo);
            $semMatricula = $this->getItensSemMatriculaNoAno($registros, $anoLetivo);

            return [
                'cpfs_extraidos' => count($registros),
                'ano_letivo' => $anoLetivo,
                'cpfs_nao_cadastrados' => $semMatricula,
            ];
        } catch (Throwable $e) {
            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => $anoLetivo,
                'cpfs_nao_cadastrados' => [],
                'erro' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, array{cpf: string, nome: string, data_nascimento: string}>
     */
    public function extrairRegistrosDoCsv(string $conteudo): array
    {
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
        $conteudo = $this->garantirUtf8ConteudoCsv($conteudo);
        $conteudo = str_replace(["\r\n", "\r"], "\n", $conteudo);
        $linhas = explode("\n", $conteudo);

        $idxHeader = null;
        $idxCpf = null;
        $idxNome = null;
        $idxDataNascimento = null;
        $idxEndereco = null;
        $idxUltimaAtualizacao = null;

        foreach ($linhas as $i => $linha) {
            $colunas = str_getcsv($linha, ';');
            if (count($colunas) < 2) {
                continue;
            }

            $mapaCabecalho = [];
            foreach ($colunas as $j => $coluna) {
                $mapaCabecalho[$this->normalizarCabecalho((string) $coluna)] = $j;
            }

            if (
                isset($mapaCabecalho['cpf/cns']) &&
                isset($mapaCabecalho['nome']) &&
                isset($mapaCabecalho['data de nascimento'])
            ) {
                $idxHeader = $i;
                $idxCpf = $mapaCabecalho['cpf/cns'];
                $idxNome = $mapaCabecalho['nome'];
                $idxDataNascimento = $mapaCabecalho['data de nascimento'];
                $idxEndereco = $mapaCabecalho['endereco'] ?? null;
                $idxUltimaAtualizacao = $mapaCabecalho['ultima atualizacao cadastral'] ?? null;
                // Layout padrão e-SUS: Endereço fica imediatamente antes de CPF/CNS (útil se o nome da coluna
                // variar codificação/acento e o mapa não achar "endereco", ou índice vier incorreto).
                if (($idxEndereco === null || $idxEndereco >= $idxCpf) && $idxCpf >= 1) {
                    $idxEndereco = $idxCpf - 1;
                }

                break;
            }
        }

        if ($idxHeader === null || $idxCpf === null || $idxNome === null || $idxDataNascimento === null) {
            return [];
        }

        $registros = [];
        $totalLinhas = count($linhas);
        for ($i = $idxHeader + 1; $i < $totalLinhas; $i++) {
            $linha = trim($linhas[$i] ?? '');
            if ($linha === '') {
                continue;
            }

            $colunas = str_getcsv($linha, ';');
            $nCol = count($colunas);

            $cpfCol = $idxCpf;
            $celulaCpfHeader = (string) ($colunas[$idxCpf] ?? '');
            if ($this->parseIdentificadorColunaCpfCnsCsv($celulaCpfHeader)['chave'] === '') {
                $detectada = $this->buscarIndiceColunaIdentificadorCpfOuCns($colunas);
                if ($detectada !== null) {
                    $cpfCol = $detectada;
                }
            }

            $deslocamento = $cpfCol - $idxCpf;
            $clamp = static fn (int $idx): int => max(0, min($nCol - 1, $idx));

            $nome = trim((string) ($colunas[$clamp($idxNome + $deslocamento)] ?? ''));
            $dataNascimento = trim((string) ($colunas[$clamp($idxDataNascimento + $deslocamento)] ?? ''));
            if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataNascimento)) {
                $dataNascimento = '';
            }

            $enderecoRelatorio = '';
            if ($idxEndereco !== null) {
                $enderecoRelatorio = $this->sanitizarTextoEndereco(trim((string) ($colunas[$clamp($idxEndereco + $deslocamento)] ?? '')));
            }

            $ultimaAtualizacao = '';
            if ($idxUltimaAtualizacao !== null) {
                $ultimaAtualizacao = trim((string) ($colunas[$clamp($idxUltimaAtualizacao + $deslocamento)] ?? ''));
                if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $ultimaAtualizacao)) {
                    $ultimaAtualizacao = '';
                }
            }

            $id = $this->parseIdentificadorColunaCpfCnsCsv((string) ($colunas[$clamp($cpfCol)] ?? ''));
            $chave = $id['chave'];
            if ($chave === '') {
                if ($nome === '' || $enderecoRelatorio === '') {
                    continue;
                }
                $chave = 'sem_doc:'.md5($nome.'|'.$dataNascimento.'|'.$enderecoRelatorio);
            }

            $novo = [
                'cpf' => $id['cpf_formatado'],
                'cns' => $id['cns'],
                'nome' => $nome,
                'data_nascimento' => $dataNascimento,
                'endereco_relatorio' => $enderecoRelatorio,
                'ultima_atualizacao_cadastral' => $ultimaAtualizacao,
            ];

            if (! isset($registros[$chave])) {
                $registros[$chave] = $novo;
            } else {
                $registros[$chave] = $this->mesclarRegistrosEsusCsv($registros[$chave], $novo);
            }
        }

        return $registros;
    }

    /**
     * @param  array<string, mixed>  $existente
     * @param  array<string, mixed>  $novo
     * @return array<string, mixed>
     */
    private function mesclarRegistrosEsusCsv(array $existente, array $novo): array
    {
        foreach (['nome', 'data_nascimento', 'cpf', 'cns', 'endereco_relatorio', 'ultima_atualizacao_cadastral'] as $campo) {
            $v = trim((string) ($existente[$campo] ?? ''));
            $n = trim((string) ($novo[$campo] ?? ''));
            if ($v === '' && $n !== '') {
                $existente[$campo] = $n;
            }
        }

        return $existente;
    }

    private function normalizarCabecalho(string $valor): string
    {
        return Str::of($valor)
            ->trim()
            ->lower()
            ->ascii()
            ->toString();
    }

    /**
     * CPF formatado (11 dígitos), CNS (15 dígitos) ou vazio; `chave` identifica o registro no mapa.
     *
     * @return array{cpf_formatado: string, cns: string, chave: string}
     */
    private function parseIdentificadorColunaCpfCnsCsv(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return ['cpf_formatado' => '', 'cns' => '', 'chave' => ''];
        }

        if (preg_match(self::CPF_PATTERN, $valor, $m)) {
            $fmt = $m[0];

            return ['cpf_formatado' => $fmt, 'cns' => '', 'chave' => $fmt];
        }

        $digitos = $this->extrairDigitosCpfCnsDoValorCsv($valor);
        if (strlen($digitos) === 11) {
            $fmt = substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-'.substr($digitos, 9, 2);

            return ['cpf_formatado' => $fmt, 'cns' => '', 'chave' => $fmt];
        }
        if (strlen($digitos) === 15) {
            return ['cpf_formatado' => '', 'cns' => $digitos, 'chave' => 'cns:'.$digitos];
        }

        return ['cpf_formatado' => '', 'cns' => '', 'chave' => ''];
    }

    /**
     * Suporta CPF/CNS numérico e notação científica exportada pelo Excel (ex.: 8,98005E+14).
     */
    private function extrairDigitosCpfCnsDoValorCsv(string $valor): string
    {
        $t = trim($valor);
        if ($t === '') {
            return '';
        }
        if (preg_match('/[eE]/', $t)) {
            $normalized = str_replace(',', '.', str_replace(' ', '', $t));
            if (! is_numeric($normalized)) {
                return '';
            }
            $f = (float) $normalized;
            if (! is_finite($f) || $f <= 0) {
                return '';
            }

            return sprintf('%.0f', $f);
        }

        return preg_replace('/\D+/', '', $t) ?? '';
    }
}
