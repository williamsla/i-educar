<?php

namespace App\Http\Controllers;

use App\Http\Requests\SgpExportRequest;
use App\Process;
use App\Services\SgpExport\SgpExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SgpExportController extends Controller
{
    public function index(): View
    {
        $this->breadcrumb('SGP', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::SGP_EXPORT);

        return view('sgp-export.index', [
            'types' => SgpExportService::types(),
        ]);
    }

    public function export(SgpExportRequest $request, SgpExportService $exportService): BinaryFileResponse|StreamedResponse
    {
        return $exportService->download($this->filters($request));
    }

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
            'tipo' => $request->input('tipo'),
        ];
    }
}
