<?php

namespace App\Http\Controllers;

use App\Http\Requests\MecGestaoPresenteExportRequest;
use App\Process;
use App\Services\SgpExport\MecGestaoPresenteExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MecGestaoPresenteExportController extends Controller
{
    public function index(): View
    {
        $this->breadcrumb('MEC Gestão Presente na Escola', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::MEC_GESTAO_PRESENTE_EXPORT);

        return view('mec-gestao-presente-export.index');
    }

    public function export(MecGestaoPresenteExportRequest $request, MecGestaoPresenteExportService $exportService): BinaryFileResponse
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
        ];
    }
}
