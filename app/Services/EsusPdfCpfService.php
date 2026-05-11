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
 * Cruzamento com o cadastro: por CPF quando informado; sem CPF, por CNS (cartão SUS em cadastro.fisica.sus).
 * Se não houver aluno por documento, tenta nome + data de nascimento (ambos na planilha; nome sem acento na comparação).
 */
class EsusPdfCpfService
{
    /**
     * Situação transferido.
     */
    private const MATRICULA_TRANSFERIDO = 4;

    /**
     * Considera transferências em matrículas cujo ano letivo seja >= (ano verificado - N).
     */
    private const ANOS_LOOKBACK_TRANSFERENCIA = 5;

    /**
     * Para exclusão da lista de pendentes: diferença mínima (em meses completos) entre a
     * "Última atualização cadastral" do CSV e a data de transferência (data_cancel).
     */
    private const MESES_DISTANCIA_TRANSFERENCIA_PARA_EXCLUIR_PENDENTE = 2;

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
     * Cartão SUS (CNS): remove tudo que não for dígito 0–9 antes de comparar com cadastro.fisica.sus.
     */
    private function normalizarCnsCartaoSus(mixed $valor): string
    {
        $s = preg_replace('/[^0-9]+/', '', (string) $valor) ?? '';

        return strlen($s) === 15 ? $s : '';
    }

    /**
     * CNS só entra nas consultas ao cadastro quando não há CPF na planilha (filtro por cartão SUS).
     */
    private function cnsParaConsultaCadastro(string $cpfNormalizado, string $cnsApenasDigitos): string
    {
        if ($cpfNormalizado !== '' && $cpfNormalizado !== null) {
            return '';
        }

        return $this->normalizarCnsCartaoSus($cnsApenasDigitos);
    }

    /**
     * Matrícula ativa no ano por CPF/CNS e, se informado, por idpes resolvidos com nome + data de nascimento.
     *
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function possuiMatriculaAtivaNoAnoPorCpfOuCns(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        int $anoLetivo,
        array $idpesFallbackNomeData = []
    ): bool {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns && $idpesFallbackNomeData === []) {
            return false;
        }

        return DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->where(function ($q) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos, $idpesFallbackNomeData) {
                if ($temCpf || $temCns) {
                    $q->where(function ($doc) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                        if ($temCpf && $temCns) {
                            $doc->where(function ($w) use ($cpfNormalizado, $cnsApenasDigitos) {
                                $w->where('f.cpf', $cpfNormalizado)
                                    ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                            });
                        } elseif ($temCpf) {
                            $doc->where('f.cpf', $cpfNormalizado);
                        } else {
                            $doc->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                        }
                    });
                }
                if ($idpesFallbackNomeData !== []) {
                    if ($temCpf || $temCns) {
                        $q->orWhereIn('f.idpes', $idpesFallbackNomeData);
                    } else {
                        $q->whereIn('f.idpes', $idpesFallbackNomeData);
                    }
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
            $cnsDigitos = $this->normalizarCnsCartaoSus($dados['cns'] ?? '');
            if ($cpfNormalizado === '' && $cnsDigitos === '') {
                $lista[] = $this->normalizarLinhaItem($dados);

                continue;
            }
            $cnsDb = $this->cnsParaConsultaCadastro($cpfNormalizado, $cnsDigitos);
            $idpesFallback = $this->resolverIdpesFallbackNomeEDataSeDocumentoNaoLocalizaAluno(
                $cpfNormalizado,
                $cnsDb,
                trim((string) ($dados['nome'] ?? '')),
                (string) ($dados['data_nascimento'] ?? '')
            );
            if (! $this->possuiMatriculaAtivaNoAnoPorCpfOuCns($cpfNormalizado, $cnsDb, $anoLetivo, $idpesFallback)) {
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
     * Regras após "sem matrícula ativa no ano" (lista de pendentes no relatório):
     * - "Última atualização cadastral" do e-SUS anterior à data da matrícula mais recente no i-Educar: não exibir.
     * - Aluno cadastrado cuja última matrícula (mais recente) está em série do 9º ano: não exibir.
     * - Aluno cadastrado cuja última matrícula é transferência e há pelo menos N meses entre
     *   a data da transferência e a "Última atualização cadastral" do CSV: não exibir.
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
            $cnsDigitos = $this->normalizarCnsCartaoSus($dados['cns'] ?? '');
            if ($cpfNormalizado === '' && $cnsDigitos === '') {
                $filtrados[] = $dados;

                continue;
            }

            $ultimaEsus = $this->parseDataBr((string) ($dados['ultima_atualizacao_cadastral'] ?? ''));

            $cnsDb = $this->cnsParaConsultaCadastro($cpfNormalizado, $cnsDigitos);
            $idpesFallback = $this->resolverIdpesFallbackNomeEDataSeDocumentoNaoLocalizaAluno(
                $cpfNormalizado,
                $cnsDb,
                trim((string) ($dados['nome'] ?? '')),
                (string) ($dados['data_nascimento'] ?? '')
            );
            if (! $this->existeAlunoAtivoPorCpfCnsOuIdpesFallback($cpfNormalizado, $cnsDb, $idpesFallback)) {
                $filtrados[] = $dados;

                continue;
            }

            if ($this->descartarAlunoSeAtualizacaoCadastralEsusAnteriorUltimaMatricula(
                $ultimaEsus,
                $cpfNormalizado,
                $cnsDb,
                $idpesFallback
            )) {
                continue;
            }

            if ($this->ultimaMatriculaSerieIndicaNonoFundamental($cpfNormalizado, $cnsDb, $idpesFallback)) {
                continue;
            }

            if ($this->excluirPendentePorTransferenciaComDistanciaCadastral(
                $cpfNormalizado,
                $cnsDb,
                $ultimaEsus,
                $anoLetivo,
                $idpesFallback
            )) {
                continue;
            }

            $filtrados[] = $dados;
        }

        return $filtrados;
    }

    /**
     * Data mais recente entre data_matricula e data_cadastro das matrículas do aluno identificado.
     *
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function obterDataMaisRecenteMatriculaDoAluno(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData = []
    ): ?Carbon {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns && $idpesFallbackNomeData === []) {
            return null;
        }

        $valor = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as al', 'al.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->where(function ($w) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos, $idpesFallbackNomeData) {
                if ($temCpf || $temCns) {
                    $w->where(function ($doc) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                        if ($temCpf && $temCns) {
                            $doc->where(function ($x) use ($cpfNormalizado, $cnsApenasDigitos) {
                                $x->where('f.cpf', $cpfNormalizado)
                                    ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                            });
                        } elseif ($temCpf) {
                            $doc->where('f.cpf', $cpfNormalizado);
                        } else {
                            $doc->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                        }
                    });
                }
                if ($idpesFallbackNomeData !== []) {
                    if ($temCpf || $temCns) {
                        $w->orWhereIn('f.idpes', $idpesFallbackNomeData);
                    } else {
                        $w->whereIn('f.idpes', $idpesFallbackNomeData);
                    }
                }
            })
            ->where('al.ativo', 1)
            ->selectRaw('max(coalesce(m.data_matricula, m.data_cadastro)) as data_ref')
            ->value('data_ref');

        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Última atualização cadastral do e-SUS estritamente anterior à última matrícula no sistema: fora da lista.
     *
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function descartarAlunoSeAtualizacaoCadastralEsusAnteriorUltimaMatricula(
        ?Carbon $ultimaAtualizacaoEsus,
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData = []
    ): bool {
        if ($ultimaAtualizacaoEsus === null) {
            return false;
        }
        $dataUltimaMatricula = $this->obterDataMaisRecenteMatriculaDoAluno(
            $cpfNormalizado,
            $cnsApenasDigitos,
            $idpesFallbackNomeData
        );
        if ($dataUltimaMatricula === null) {
            return false;
        }

        return $ultimaAtualizacaoEsus->copy()->startOfDay()->lt($dataUltimaMatricula->copy()->startOfDay());
    }

    private function existeAlunoAtivoPorCpfOuCns(string $cpfNormalizado, string $cnsApenasDigitos): bool
    {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns) {
            return false;
        }

        $q = DB::table('pmieducar.aluno as al')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->where('al.ativo', 1)
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
            });

        return $q->exists();
    }

    /**
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function existeAlunoAtivoPorCpfCnsOuIdpesFallback(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData
    ): bool {
        if ($this->existeAlunoAtivoPorCpfOuCns($cpfNormalizado, $cnsApenasDigitos)) {
            return true;
        }

        return $idpesFallbackNomeData !== [] && $this->existeAlunoAtivoPorIdpes($idpesFallbackNomeData);
    }

    /**
     * @param  list<int>  $idpesList
     */
    private function existeAlunoAtivoPorIdpes(array $idpesList): bool
    {
        if ($idpesList === []) {
            return false;
        }

        return DB::table('pmieducar.aluno as al')
            ->where('al.ativo', 1)
            ->whereIn('al.ref_idpes', $idpesList)
            ->exists();
    }

    /**
     * Nome para comparação auxiliar (minúsculas, espaços colapsados, sem acentos — alinhado ao uso de unaccent no SQL).
     */
    private function normalizarNomeComparacaoCadastro(string $nome): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
        $ascii = Str::ascii(trim($collapsed));

        return mb_strtolower($ascii, 'UTF-8');
    }

    /**
     * Aluno ativo cujo nome (pessoa) e data de nascimento (física) coincidem com a planilha.
     * Comparação de nome ignora acentos (unaccent no PostgreSQL) na planilha e no cadastro.
     *
     * @return list<int>
     */
    private function buscarIdpesAlunoAtivoPorNomeEDataNascimento(string $nomePlanilha, Carbon $dataNascimento): array
    {
        $nomeCsv = trim((string) (preg_replace('/\s+/u', ' ', trim($nomePlanilha)) ?? ''));
        if ($nomeCsv === '') {
            return [];
        }

        return DB::table('cadastro.fisica as f')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'f.idpes')
            ->join('pmieducar.aluno as al', 'al.ref_idpes', '=', 'f.idpes')
            ->where('al.ativo', 1)
            ->whereRaw(
                "lower(regexp_replace(trim(both ' ' from unaccent(trim(p.nome))), '\\s+', ' ', 'g')) = lower(regexp_replace(trim(both ' ' from unaccent(?)), '\\s+', ' ', 'g'))",
                [$nomeCsv]
            )
            ->whereDate('f.data_nasc', $dataNascimento->format('Y-m-d'))
            ->pluck('f.idpes')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Só usa nome + nascimento quando não existe aluno ativo identificado por CPF/CNS.
     *
     * @return list<int>
     */
    private function resolverIdpesFallbackNomeEDataSeDocumentoNaoLocalizaAluno(
        string $cpfNormalizado,
        string $cnsConsultaDb,
        string $nomePlanilha,
        string $dataNascimentoBr
    ): array {
        if ($this->existeAlunoAtivoPorCpfOuCns($cpfNormalizado, $cnsConsultaDb)) {
            return [];
        }
        $data = $this->parseDataBr($dataNascimentoBr);
        if ($data === null) {
            return [];
        }

        return $this->buscarIdpesAlunoAtivoPorNomeEDataNascimento($nomePlanilha, $data);
    }

    /**
     * Última matrícula do aluno (ano e cod_matricula descendentes) com série vinculada:
     * indica 9º ano do fundamental (etapa ou nome da série).
     *
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function ultimaMatriculaSerieIndicaNonoFundamental(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData = []
    ): bool {
        $row = $this->obterUltimaMatriculaComSerie($cpfNormalizado, $cnsApenasDigitos, $idpesFallbackNomeData);
        if ($row === null) {
            return false;
        }

        $etapa = isset($row->etapa_curso) ? (int) $row->etapa_curso : 0;
        $nmSerie = isset($row->nm_serie) ? (string) $row->nm_serie : '';

        return $this->serieEhNonoFundamental($nmSerie, $etapa);
    }

    /**
     * @param  list<int>  $idpesFallbackNomeData
     * @return object{aprovado: int|string, data_cancel: mixed, ano: int|string, nm_serie: mixed, etapa_curso: mixed}|null
     */
    private function obterUltimaMatriculaComSerie(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData = []
    ): ?object {
        $temCpf = $cpfNormalizado !== '' && $cpfNormalizado !== null;
        $temCns = strlen($cnsApenasDigitos) === 15;
        if (! $temCpf && ! $temCns && $idpesFallbackNomeData === []) {
            return null;
        }

        $row = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as al', 'al.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->leftJoin('pmieducar.serie as s', 's.cod_serie', '=', 'm.ref_ref_cod_serie')
            ->where(function ($w) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos, $idpesFallbackNomeData) {
                if ($temCpf || $temCns) {
                    $w->where(function ($doc) use ($temCpf, $temCns, $cpfNormalizado, $cnsApenasDigitos) {
                        if ($temCpf && $temCns) {
                            $doc->where(function ($x) use ($cpfNormalizado, $cnsApenasDigitos) {
                                $x->where('f.cpf', $cpfNormalizado)
                                    ->orWhereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                            });
                        } elseif ($temCpf) {
                            $doc->where('f.cpf', $cpfNormalizado);
                        } else {
                            $doc->whereRaw("regexp_replace(coalesce(f.sus::text, ''), '[^0-9]', '', 'g') = ?", [$cnsApenasDigitos]);
                        }
                    });
                }
                if ($idpesFallbackNomeData !== []) {
                    if ($temCpf || $temCns) {
                        $w->orWhereIn('f.idpes', $idpesFallbackNomeData);
                    } else {
                        $w->whereIn('f.idpes', $idpesFallbackNomeData);
                    }
                }
            })
            ->where('al.ativo', 1)
            ->whereNotNull('m.ref_ref_cod_serie')
            ->orderByDesc('m.ano')
            ->orderByDesc('m.cod_matricula')
            ->select(['m.aprovado', 'm.data_cancel', 'm.ano', 's.nm_serie', 's.etapa_curso'])
            ->first();

        return $row;
    }

    private function serieEhNonoFundamental(string $nmSerie, int $etapaCurso): bool
    {
        if ($etapaCurso === 9) {
            return true;
        }
        $nm = mb_strtolower(trim($nmSerie), 'UTF-8');
        foreach (['nono', '9º', '9 ano', '9°'] as $frag) {
            if ($frag !== '' && mb_strpos($nm, mb_strtolower($frag, 'UTF-8'), 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Última matrícula é transferência e |última atualização cadastral − data_cancel| ≥ N meses: fora da lista de pendentes.
     */
    /**
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function excluirPendentePorTransferenciaComDistanciaCadastral(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        ?Carbon $ultimaAtualizacaoEsus,
        int $anoLetivo,
        array $idpesFallbackNomeData = []
    ): bool {
        if ($ultimaAtualizacaoEsus === null) {
            return false;
        }

        $anoMin = $anoLetivo - self::ANOS_LOOKBACK_TRANSFERENCIA;
        $row = $this->obterUltimaMatriculaComSerie($cpfNormalizado, $cnsApenasDigitos, $idpesFallbackNomeData);
        if ($row === null || (int) $row->aprovado !== self::MATRICULA_TRANSFERIDO) {
            return false;
        }
        if ((int) ($row->ano ?? 0) < $anoMin) {
            return false;
        }

        $dataCancel = $this->parseDataCancelMatricula($row->data_cancel ?? null);
        if ($dataCancel === null) {
            return false;
        }

        $meses = (int) $ultimaAtualizacaoEsus->copy()->startOfDay()->diffInMonths($dataCancel->copy()->startOfDay());

        return $meses >= self::MESES_DISTANCIA_TRANSFERENCIA_PARA_EXCLUIR_PENDENTE;
    }

    private function parseDataCancelMatricula(mixed $dataCancel): ?Carbon
    {
        if ($dataCancel === null || $dataCancel === '') {
            return null;
        }
        if ($dataCancel instanceof Carbon) {
            return $dataCancel->copy()->startOfDay();
        }
        $s = trim((string) $dataCancel);
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s)->startOfDay();
        } catch (Throwable) {
            return null;
        }
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
            $cnsDigitos = $this->normalizarCnsCartaoSus($dados['cns'] ?? '');
            $cnsDb = $this->cnsParaConsultaCadastro($cpfNormalizado, $cnsDigitos);
            $idpesFallback = $this->resolverIdpesFallbackNomeEDataSeDocumentoNaoLocalizaAluno(
                $cpfNormalizado,
                $cnsDb,
                trim((string) ($dados['nome'] ?? '')),
                (string) ($dados['data_nascimento'] ?? '')
            );
            $cadastro = ($cpfNormalizado !== '' || $cnsDigitos !== '')
                ? $this->obterEnderecoCadastroPorCpfOuCns($cpfNormalizado, $cnsDb, $idpesFallback)
                : '';
            $cadastro = $this->sanitizarTextoEndereco($cadastro);
            $relatorio = $this->sanitizarTextoEndereco((string) ($dados['endereco_relatorio'] ?? ''));
            if ($this->enderecoPossuiConteudoUtil($cadastro)) {
                $itens[$i]['endereco'] = $this->removerCepDoEnderecoExibicao($cadastro);
            } elseif ($this->relatorioEnderecoConsideradoPreenchido($relatorio)) {
                $itens[$i]['endereco'] = $this->removerCepDoEnderecoExibicao($relatorio);
            } else {
                $itens[$i]['endereco'] = '—';
            }
            $itens[$i]['ultima_atualizacao_cadastral'] = trim((string) ($dados['ultima_atualizacao_cadastral'] ?? ''));
            if ($itens[$i]['ultima_atualizacao_cadastral'] === '') {
                $itens[$i]['ultima_atualizacao_cadastral'] = '—';
            }
        }

        return $itens;
    }

    /**
     * Remove CEP do texto de endereço (cadastro ou e-SUS), inclusive após "|" ou no final da linha.
     */
    private function removerCepDoEnderecoExibicao(string $endereco): string
    {
        if ($endereco === '' || $endereco === '—') {
            return $endereco;
        }

        $t = $endereco;
        $t = preg_replace('/\s*\|\s*\d{5}-?\d{3}\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/\s*[—\-]\s*\d{5}-?\d{3}\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/\b\d{5}-?\d{3}\s*$/u', '', $t) ?? $t;
        $t = $this->sanitizarTextoEndereco($t);
        $t = preg_replace('/\s*[\|—\-]\s*$/u', '', $t) ?? $t;

        return $this->sanitizarTextoEndereco($t);
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
     *
     * @param  list<int>  $idpesFallbackNomeData
     */
    private function obterEnderecoCadastroPorCpfOuCns(
        string $cpfNormalizado,
        string $cnsApenasDigitos,
        array $idpesFallbackNomeData = []
    ): string {
        $linha = $this->obterEnderecoCadastroSomenteDocumento($cpfNormalizado, $cnsApenasDigitos);
        if ($this->enderecoPossuiConteudoUtil($linha)) {
            return $linha;
        }
        if ($idpesFallbackNomeData === []) {
            return '';
        }

        $ids = array_values(array_map('intval', $idpesFallbackNomeData));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $row = DB::selectOne(
            "select trim(both ' ' from concat_ws(' — ',
                nullif(trim(a.address), ''),
                nullif(trim(cast(a.number as varchar)), ''),
                nullif(trim(a.neighborhood), ''),
                nullif(trim(a.city), '')
            )) as linha
            from cadastro.fisica f
            inner join pmieducar.aluno al on al.ref_idpes = f.idpes and al.ativo = 1
            left join person_has_place php on php.person_id = f.idpes
            left join addresses a on a.id = php.place_id
            where f.idpes in ({$placeholders})
            order by php.type asc nulls last
            limit 1",
            $ids
        );

        $linha = is_object($row) ? (string) ($row->linha ?? '') : '';
        $linha = $this->sanitizarTextoEndereco($linha);

        return $this->enderecoPossuiConteudoUtil($linha) ? $linha : '';
    }

    private function obterEnderecoCadastroSomenteDocumento(string $cpfNormalizado, string $cnsApenasDigitos): string
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
                nullif(trim(a.city), '')
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
            if ($chave !== '' && $nome === '') {
                continue;
            }
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
        $cns = $this->normalizarCnsCartaoSus($digitos);
        if ($cns !== '') {
            return ['cpf_formatado' => '', 'cns' => $cns, 'chave' => 'cns:'.$cns];
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
