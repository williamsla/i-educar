<?php

namespace App\Http\Controllers;

use App\Models\LegacyInstitution;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class VerificarCpfEsusExportController extends Controller
{
    private const SESSION_KEY = 'verificar_cpf_esus_export';

    public const FILTRO_NAO_ENCONTRADOS = 'nao_encontrados';

    public const FILTRO_ENCONTRADOS = 'encontrados';

    public const FILTRO_AMBOS = 'ambos';

    /**
     * Armazena na sessão o resultado da última verificação (para exportação).
     *
     * @param  list<array<string, mixed>>  $itensSemMatricula
     * @param  list<array<string, mixed>>  $itensComMatricula
     */
    public static function armazenarParaExportacao(
        int $cpfsExtraidos,
        int $anoLetivo,
        array $itensSemMatricula,
        bool $excluirSomenteCnsSemCpfNoArquivo = false,
        array $itensComMatricula = [],
        string $filtroPadrao = self::FILTRO_NAO_ENCONTRADOS,
        string $tipoFonte = 'esus'
    ): void {
        Session::put(self::SESSION_KEY, [
            'verificado_em' => now()->toIso8601String(),
            'cpfs_extraidos' => $cpfsExtraidos,
            'ano_letivo' => $anoLetivo,
            'itens' => $itensSemMatricula, // compatibilidade
            'itens_sem_matricula' => $itensSemMatricula,
            'itens_com_matricula' => $itensComMatricula,
            'excluir_sem_cpf_somente_cns' => $excluirSomenteCnsSemCpfNoArquivo,
            'filtro_padrao' => self::normalizarFiltro($filtroPadrao),
            'tipo_fonte' => $tipoFonte === 'cadastro_cidadao' ? 'cadastro_cidadao' : 'esus',
        ]);
    }

    public static function limparExportacao(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function normalizarFiltro(mixed $filtro): string
    {
        return match ($filtro) {
            self::FILTRO_ENCONTRADOS, 'com_matricula' => self::FILTRO_ENCONTRADOS,
            self::FILTRO_AMBOS => self::FILTRO_AMBOS,
            default => self::FILTRO_NAO_ENCONTRADOS,
        };
    }

    /**
     * Cabeçalho + tabela para impressão / Salvar como PDF.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $payload = Session::get(self::SESSION_KEY);

        $sem = $payload['itens_sem_matricula'] ?? $payload['itens'] ?? null;
        $com = $payload['itens_com_matricula'] ?? [];

        if ((! is_array($sem) || $sem === []) && (! is_array($com) || $com === [])) {
            return redirect()->to('/intranet/educar_verificar_cpf_esus.php')
                ->with('error', 'Não há dados para exportar. Execute uma verificação em que existam cidadãos para listar.');
        }

        $anoLetivo = (int) ($payload['ano_letivo'] ?? date('Y'));
        $verificadoEm = Carbon::parse($payload['verificado_em'] ?? now());

        $instituicao = LegacyInstitution::query()->value('nm_instituicao')
            ?? config('legacy.app.template.vars.instituicao')
            ?? config('app.name');

        $filtro = self::normalizarFiltro(
            $request->query('filtro', $payload['filtro_padrao'] ?? self::FILTRO_NAO_ENCONTRADOS)
        );

        $itensSem = is_array($sem) ? $sem : [];
        $itensCom = is_array($com) ? $com : [];

        $itens = match ($filtro) {
            self::FILTRO_ENCONTRADOS => $itensCom,
            self::FILTRO_AMBOS => array_merge(
                array_map(static fn (array $r) => $r + ['situacao_matricula' => 'encontrado'], $itensCom),
                array_map(static fn (array $r) => $r + ['situacao_matricula' => 'nao_encontrado'], $itensSem),
            ),
            default => $itensSem,
        };

        $itens = self::ordenarItensPorDataNascimentoDescNomeAsc($itens);
        $resumoPorAnoNascimento = self::resumoQuantidadePorAnoNascimento($itens);

        $titulo = match ($filtro) {
            self::FILTRO_ENCONTRADOS => 'Relatório eSUS — com matrícula ativa no ano letivo '.$anoLetivo,
            self::FILTRO_AMBOS => 'Relatório eSUS — com e sem matrícula ativa no ano letivo '.$anoLetivo,
            default => 'Relatório — sem matrícula ativa no ano letivo '.$anoLetivo,
        };

        $tipoFonte = ($payload['tipo_fonte'] ?? 'esus') === 'cadastro_cidadao'
            ? 'cadastro_cidadao'
            : 'esus';

        return view('reports.verificar-cpf-esus-export', [
            'titulo' => $titulo,
            'instituicao' => $instituicao,
            'verificado_em' => $verificadoEm,
            'ano_letivo' => $anoLetivo,
            'cpfs_extraidos' => (int) ($payload['cpfs_extraidos'] ?? 0),
            'itens' => $itens,
            'resumo_por_ano_nascimento' => $resumoPorAnoNascimento,
            'excluir_sem_cpf_somente_cns' => (bool) ($payload['excluir_sem_cpf_somente_cns'] ?? false),
            'filtro' => $filtro,
            'total_encontrados' => count($itensCom),
            'total_nao_encontrados' => count($itensSem),
            'mostrar_coluna_situacao' => $filtro === self::FILTRO_AMBOS,
            'mostrar_coluna_ultimo_atendimento' => $tipoFonte !== 'cadastro_cidadao',
            'tipo_fonte' => $tipoFonte,
            'export_base_url' => url('/relatorios/verificar-cpf-esus/exportar'),
        ]);
    }

    /**
     * Ordena por data de nascimento (DD/MM/AAAA) decrescente e, em empate, por nome crescente.
     * Sem data válida ficam por último (nome crescente entre eles).
     *
     * @param  list<array<string, mixed>>  $itens
     * @return list<array<string, mixed>>
     */
    private static function ordenarItensPorDataNascimentoDescNomeAsc(array $itens): array
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
            $nome = mb_strtolower(trim((string) ($row['nome'] ?? '')), 'UTF-8');
            $comTimestamp[] = ['row' => $row, 'ts' => $ts, 'nome' => $nome, 'idx' => $i];
        }

        usort($comTimestamp, function (array $a, array $b): int {
            if ($a['ts'] === null && $b['ts'] === null) {
                $porNome = $a['nome'] <=> $b['nome'];

                return $porNome !== 0 ? $porNome : ($a['idx'] <=> $b['idx']);
            }
            if ($a['ts'] === null) {
                return 1;
            }
            if ($b['ts'] === null) {
                return -1;
            }
            if ($a['ts'] !== $b['ts']) {
                return $b['ts'] <=> $a['ts']; // nascimento decrescente (mais novos primeiro)
            }
            $porNome = $a['nome'] <=> $b['nome'];

            return $porNome !== 0 ? $porNome : ($a['idx'] <=> $b['idx']);
        });

        return array_map(fn (array $x) => $x['row'], $comTimestamp);
    }

    /**
     * Conta registros por ano de nascimento (a partir de DD/MM/AAAA); sem data válida em `sem_data`.
     *
     * @param  list<array<string, mixed>>  $itens
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

        krsort($anos, SORT_NUMERIC); // anos mais recentes primeiro (alinha à lista)

        return ['anos' => $anos, 'sem_data' => $semData];
    }
}
