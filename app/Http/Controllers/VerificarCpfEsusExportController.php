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

        return view('reports.verificar-cpf-esus-export', [
            'titulo' => 'Relatório eSUS — sem matrícula ativa no ano letivo '.$anoLetivo,
            'instituicao' => $instituicao,
            'verificado_em' => $verificadoEm,
            'ano_letivo' => $anoLetivo,
            'cpfs_extraidos' => (int) ($payload['cpfs_extraidos'] ?? 0),
            'itens' => $payload['itens'],
        ]);
    }
}
