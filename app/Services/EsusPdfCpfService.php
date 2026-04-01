<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrai CPFs e dados correlatos de PDFs do relatório "Acompanhamento de cidadãos vinculados" do eSUS
 * e verifica quais não possuem matrícula ativa no ano letivo informado (pmieducar.matricula).
 */
class EsusPdfCpfService
{
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
            $dataNasc = $this->extrairDataNascimento($after);

            $registros[$cpf] = [
                'cpf' => $cpf,
                'nome' => $nome,
                'data_nascimento' => $dataNasc,
            ];
        }

        return $registros;
    }

    private function extrairDataNascimento(string $after): string
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

        if (preg_match_all('/\b(\d{2}\/\d{2}\/\d{4})\b/', $rest, $dm)) {
            $melhor = '';
            $melhorTs = null;
            foreach ($dm[1] as $d) {
                if (! $this->dataPlausivelNascimento($d)) {
                    continue;
                }
                $ts = strtotime(str_replace('/', '-', $d));
                if ($melhorTs === null || $ts < $melhorTs) {
                    $melhorTs = $ts;
                    $melhor = $d;
                }
            }

            return $melhor;
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
        if ($cpfNormalizado === '' || $cpfNormalizado === null) {
            return false;
        }

        return DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->where('f.cpf', $cpfNormalizado)
            ->where('m.ano', $anoLetivo)
            ->where('m.ativo', 1)
            ->where('a.ativo', 1)
            ->exists();
    }

    /**
     * @param  array<string, array{cpf: string, nome: string, data_nascimento: string}>  $registrosPorCpf
     * @return list<array{cpf: string, nome: string, data_nascimento: string}>
     */
    public function getItensSemMatriculaNoAno(array $registrosPorCpf, int $anoLetivo): array
    {
        $lista = [];

        foreach ($registrosPorCpf as $cpfFormatado => $dados) {
            $cpfNormalizado = idFederal2int($cpfFormatado);
            if ($cpfNormalizado === '' || $cpfNormalizado === null) {
                continue;
            }
            if (! $this->possuiMatriculaAtivaNoAno($cpfNormalizado, $anoLetivo)) {
                $lista[] = $dados;
            }
        }

        return $lista;
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
}
