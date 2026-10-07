<?php

namespace App\Http\Controllers;

use App\Http\Requests\FrequenciaSistemaPresencaRequest;
use App\Process;
use App\Services\Presenca\MonthlyAbsenceReportException;
use App\Services\Presenca\MonthlyAbsenceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FrequenciaSistemaPresencaController extends Controller
{
    public function index(): View
    {
        $this->breadcrumb('Frequência para o Sistema Presença', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::FREQUENCIA_SISTEMA_PRESENCA);

        return view('frequencia-sistema-presenca.index');
    }

    public function report(FrequenciaSistemaPresencaRequest $request, MonthlyAbsenceReportService $service): Response|RedirectResponse
    {
        $this->authorizeSchool((int) $request->input('ref_cod_escola'));

        try {
            $pdf = $service->generate($this->filters($request));
        } catch (MonthlyAbsenceReportException $exception) {
            return back()->withErrors($exception->getMessage())->withInput();
        }

        return response($pdf['body'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf['filename'] . '"',
        ]);
    }

    private function authorizeSchool(int $schoolId): void
    {
        $user = auth()->user();

        if ($user === null || !$user->isSchooling()) {
            return;
        }

        $allowed = $user->schools->pluck('cod_escola')->map(static fn ($id) => (int) $id)->all();

        if (!in_array($schoolId, $allowed, true)) {
            abort(403);
        }
    }

    /**
     * @return array{cod_escola:int,ano:int,meses:array<int>,ordenar:string,exibir_sem_faltas:bool,cod_serie:?int,cod_turma:?int}
     */
    private function filters(FrequenciaSistemaPresencaRequest $request): array
    {
        $meses = array_map('intval', (array) $request->input('meses', []));

        return [
            'cod_escola' => (int) $request->input('ref_cod_escola'),
            'ano' => (int) $request->input('ano'),
            'meses' => $meses,
            'ordenar' => (string) $request->input('ordenar', 'nome'),
            'exibir_sem_faltas' => (string) $request->input('exibir_sem_faltas', '0') === '1',
            'cod_serie' => $request->filled('ref_cod_serie') ? (int) $request->input('ref_cod_serie') : null,
            'cod_turma' => $request->filled('ref_cod_turma') ? (int) $request->input('ref_cod_turma') : null,
        ];
    }
}
