<?php

namespace App\Http\Controllers;

use App\Models\LegacyInstitution;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class VerificarCpfEsusExportController extends Controller
{
    private const SESSION_KEY = 'verificar_cpf_esus_export';

    /**
     * Armazena na sessão o resultado da última verificação (para exportação).
     *
     * @param  list<array{cpf: string, nome: string, data_nascimento: string}>  $itens
     */
    public static function armazenarParaExportacao(int $cpfsExtraidos, int $anoLetivo, array $itens): void
    {
        Session::put(self::SESSION_KEY, [
            'verificado_em' => now()->toIso8601String(),
            'cpfs_extraidos' => $cpfsExtraidos,
            'ano_letivo' => $anoLetivo,
            'itens' => $itens,
        ]);
    }

    public static function limparExportacao(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Cabeçalho + tabela para impressão / Salvar como PDF.
     */
    public function __invoke(): View|RedirectResponse
    {
        $payload = Session::get(self::SESSION_KEY);

        if (empty($payload['itens']) || ! is_array($payload['itens'])) {
            return redirect()->to('/intranet/educar_verificar_cpf_esus.php')
                ->with('error', 'Não há dados para exportar. Execute uma verificação em que existam cidadãos sem matrícula ativa no ano escolhido.');
        }

        $anoLetivo = (int) ($payload['ano_letivo'] ?? date('Y'));
        $verificadoEm = Carbon::parse($payload['verificado_em'] ?? now());

        $instituicao = LegacyInstitution::query()->value('nm_instituicao')
            ?? config('legacy.app.template.vars.instituicao')
            ?? config('app.name');

        $itens = self::ordenarItensPorDataNascimentoAsc($payload['itens']);
        $resumoPorAnoNascimento = self::resumoQuantidadePorAnoNascimento($itens);

        return view('reports.verificar-cpf-esus-export', [
            'titulo' => 'Relatório eSUS — sem matrícula ativa no ano letivo '.$anoLetivo,
            'instituicao' => $instituicao,
            'verificado_em' => $verificadoEm,
            'ano_letivo' => $anoLetivo,
            'cpfs_extraidos' => (int) ($payload['cpfs_extraidos'] ?? 0),
            'itens' => $itens,
            'resumo_por_ano_nascimento' => $resumoPorAnoNascimento,
        ]);
    }

    /**
     * Ordena por data de nascimento (DD/MM/AAAA) crescente; sem data válida ficam por último.
     *
     * @param  list<array{cpf: string, nome: string, data_nascimento: string}>  $itens
     * @return list<array{cpf: string, nome: string, data_nascimento: string}>
     */
    private static function ordenarItensPorDataNascimentoAsc(array $itens): array
    {
        $comTimestamp = [];
        foreach ($itens as $i => $row) {
            $data = trim((string) ($row['data_nascimento'] ?? ''));
            $ts = null;
            if ($data !== '' && $data !== '—') {
                try {
                    $ts = Carbon::createFromFormat('d/m/Y', $data)->startOfDay()->timestamp;
                } catch (\Throwable) {
                    $ts = null;
                }
            }
            $comTimestamp[] = ['row' => $row, 'ts' => $ts, 'idx' => $i];
        }

        usort($comTimestamp, function (array $a, array $b): int {
            if ($a['ts'] === null && $b['ts'] === null) {
                return $a['idx'] <=> $b['idx'];
            }
            if ($a['ts'] === null) {
                return 1;
            }
            if ($b['ts'] === null) {
                return -1;
            }
            if ($a['ts'] !== $b['ts']) {
                return $a['ts'] <=> $b['ts'];
            }

            return $a['idx'] <=> $b['idx'];
        });

        return array_map(fn (array $x) => $x['row'], $comTimestamp);
    }

    /**
     * Conta registros por ano de nascimento (a partir de DD/MM/AAAA); sem data válida em `sem_data`.
     *
     * @param  list<array{cpf: string, nome: string, data_nascimento: string}>  $itens
     * @return array{anos: array<int, int>, sem_data: int}
     */
    private static function resumoQuantidadePorAnoNascimento(array $itens): array
    {
        $anos = [];
        $semData = 0;

        foreach ($itens as $row) {
            $data = trim((string) ($row['data_nascimento'] ?? ''));
            if ($data === '' || $data === '—') {
                $semData++;

                continue;
            }
            try {
                $ano = (int) Carbon::createFromFormat('d/m/Y', $data)->format('Y');
                $anos[$ano] = ($anos[$ano] ?? 0) + 1;
            } catch (\Throwable) {
                $semData++;
            }
        }

        ksort($anos, SORT_NUMERIC);

        return ['anos' => $anos, 'sem_data' => $semData];
    }
}
