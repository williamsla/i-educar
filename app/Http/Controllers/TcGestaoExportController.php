<?php

namespace App\Http\Controllers;

use App\Process;
use App\Services\TcGestao\TcGestaoExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TcGestaoExportController extends Controller
{
    public function index(): View
    {
        $this->breadcrumb('TC Gestão Pública', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::TC_GESTAO_PUBLICA_EXPORT);

        return view('tc-gestao-export.index');
    }

    public function export(Request $request, TcGestaoExportService $service)
    {
        $this->breadcrumb('TC Gestão Pública', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
        $this->menu(Process::TC_GESTAO_PUBLICA_EXPORT);

        $ano = (int) $request->input('ano');
        $mes = (int) $request->input('mes');

        if ($ano < 2000 || $mes < 1 || $mes > 12) {
            return back()->withErrors('Informe ano e mês de referência válidos.');
        }

        try {
            $result = $service->export($ano, $mes);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors('Falha na exportação: ' . $e->getMessage());
        }

        return view('tc-gestao-export.result', [
            'zipUrl' => $result['zipUrl'],
            'txtUrl' => $result['txtUrl'],
        ]);
    }
}
