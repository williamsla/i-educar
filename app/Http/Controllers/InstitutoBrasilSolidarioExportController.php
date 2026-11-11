<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitutoBrasilSolidarioExportRequest;
use App\Process;
use App\Services\InstitutoBrasilSolidario\CursoGrupos;
use App\Services\InstitutoBrasilSolidario\InstitutoBrasilSolidarioExportService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstitutoBrasilSolidarioExportController extends Controller
{
    public function index(): View
    {
        $this->breadcrumb('Instituto Brasil Solidário', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::INSTITUTO_BRASIL_SOLIDARIO_EXPORT);

        return view('instituto-brasil-solidario-export.index');
    }

    public function cursos(Request $request)
    {
        $instituicaoId = (int) $request->input('ref_cod_instituicao');

        if ($instituicaoId <= 0) {
            return response()->json([]);
        }

        $cursos = DB::table('pmieducar.curso')
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->orderBy('nm_curso')
            ->get(['cod_curso as id', 'nm_curso as nome']);

        return response()->json($cursos);
    }

    public function export(
        InstitutoBrasilSolidarioExportRequest $request,
        InstitutoBrasilSolidarioExportService $exportService,
    ): BinaryFileResponse|RedirectResponse {
        try {
            return $exportService->download($this->filters($request));
        } catch (\RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $schoolIds = null;

        if ($request->user()->isSchooling()) {
            $schoolIds = $request->user()->schools->pluck('cod_escola')->all();
        }

        return [
            'ano' => (int) $request->input('ano'),
            'institution_id' => (int) $request->input('ref_cod_instituicao'),
            'school_id' => $request->filled('ref_cod_escola') ? (int) $request->input('ref_cod_escola') : null,
            'school_ids' => $schoolIds,
            'cursos' => CursoGrupos::mapa($request),
        ];
    }
}
