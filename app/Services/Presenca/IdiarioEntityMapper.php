<?php

namespace App\Services\Presenca;

use Illuminate\Support\Facades\DB;
use Throwable;

class IdiarioEntityMapper
{
    public function gradeId(int $codSerie): ?int
    {
        $this->assertConfigured();

        try {
            $row = DB::connection('idiario')->selectOne(
                'SELECT id FROM public.grades WHERE api_code = ? ORDER BY id DESC LIMIT 1',
                [(string) $codSerie]
            );
        } catch (Throwable) {
            throw new MonthlyAbsenceReportException(
                'Não foi possível consultar a série no i-Diário. Deixe o filtro de série em branco para gerar o relatório da escola inteira.'
            );
        }

        return isset($row->id) ? (int) $row->id : null;
    }

    public function classroomId(int $codTurma, int $ano): ?int
    {
        $this->assertConfigured();

        try {
            $row = DB::connection('idiario')->selectOne(
                'SELECT id FROM public.classrooms WHERE api_code = ? AND year = ? ORDER BY id DESC LIMIT 1',
                [(string) $codTurma, $ano]
            );

            if (isset($row->id)) {
                return (int) $row->id;
            }
        } catch (Throwable) {
            // A coluna year pode não existir nesta base; tenta só pelo código da turma.
        }

        try {
            $row = DB::connection('idiario')->selectOne(
                'SELECT id FROM public.classrooms WHERE api_code = ? ORDER BY id DESC LIMIT 1',
                [(string) $codTurma]
            );
        } catch (Throwable) {
            throw new MonthlyAbsenceReportException(
                'Não foi possível consultar a turma no i-Diário. Deixe o filtro de turma em branco para gerar o relatório da escola inteira.'
            );
        }

        return isset($row->id) ? (int) $row->id : null;
    }

    private function assertConfigured(): void
    {
        $cfg = config('database.connections.idiario');
        $temUrl = !empty($cfg['url']);
        $temHost = !empty($cfg['host']) && !empty($cfg['database']) && !empty($cfg['username']);

        if ($temUrl || $temHost) {
            return;
        }

        throw new MonthlyAbsenceReportException(
            'O filtro de série ou turma exige a conexão com o banco do i-Diário. Deixe série e turma em branco para gerar o relatório da escola inteira.'
        );
    }
}
