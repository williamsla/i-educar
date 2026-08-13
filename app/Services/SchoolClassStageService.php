<?php

namespace App\Services;

use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassStage;
use App\Rules\CheckGradesAndAbsencesInStageExists;
use App\Rules\CheckGradesAndAbsencesInStageIDiarioExists;
use Illuminate\Support\Facades\DB;

class SchoolClassStageService
{
    public function store(
        LegacySchoolClass $schoolClass,
        array $startDates,
        array $endDates,
        array $schoolDays,
        int $stageId
    ) {
        [$startDates, $endDates, $schoolDays] = $this->normalizeStages($startDates, $endDates, $schoolDays);

        $this->validate($schoolClass, $startDates);
        $schoolClass->schoolClassStages()->delete();

        $schoolClassStage = $this->buildSchoolClassStages($schoolClass, $startDates, $endDates, $schoolDays, $stageId);

        foreach ($schoolClassStage as $stage) {
            $this->storeStage($stage);
        }
    }

    public function validate(LegacySchoolClass $schoolClass, array $startDates)
    {
        validator(
            ['params' => [
                'schoolClass' => $schoolClass,
                'startDates' => $startDates,
            ],
            ],
            [
                'params' => [
                    new CheckGradesAndAbsencesInStageExists,
                    new CheckGradesAndAbsencesInStageIDiarioExists,
                ],
            ]
        )->validate();
    }

    /**
     * Reindexa as etapas preenchidas em sequência (1, 2, 3...), ignorando
     * índices esparsos do formulário (ex.: 0, 1, 4, 5) e linhas vazias.
     */
    private function normalizeStages(array $startDates, array $endDates, array $schoolDays): array
    {
        $normalizedStartDates = [];
        $normalizedEndDates = [];
        $normalizedSchoolDays = [];

        foreach ($startDates as $key => $startDate) {
            $endDate = $endDates[$key] ?? null;

            if (blank($startDate) || blank($endDate)) {
                continue;
            }

            $normalizedStartDates[] = $startDate;
            $normalizedEndDates[] = $endDate;
            $normalizedSchoolDays[] = $schoolDays[$key] ?? null;
        }

        return [$normalizedStartDates, $normalizedEndDates, $normalizedSchoolDays];
    }

    private function buildSchoolClassStages(
        LegacySchoolClass $schoolClass,
        array $startDates,
        array $endDates,
        array $schoolDays,
        int $stageId
    ) {
        $schoolClassStage = [];

        foreach ($startDates as $index => $startDate) {
            $schoolDaysCount = $schoolDays[$index] ?? null;

            $schoolClassStage[] = [
                'sequencial' => $index + 1,
                'ref_cod_turma' => $schoolClass->cod_turma,
                'ref_cod_modulo' => $stageId,
                'data_inicio' => dataToBanco($startDate),
                'data_fim' => dataToBanco($endDates[$index]),
                'dias_letivos' => $schoolDaysCount === '' ? null : $schoolDaysCount,
            ];
        }

        return $schoolClassStage;
    }

    public function storeStage(array $stage)
    {
        $legacySchoolClassStage = new LegacySchoolClassStage;
        $legacySchoolClassStage->fill($stage);
        $legacySchoolClassStage->save();
    }

    /**
     * Etapas que ainda têm nota ou falta na turma, mesmo que já não existam no calendário.
     *
     * @return array<int, int>
     */
    public function getReleasedStageNumbers(int $schoolClassId): array
    {
        $componentAbsences = DB::table('modules.falta_componente_curricular as fcc')
            ->join('modules.falta_aluno as fa', 'fa.id', '=', 'fcc.falta_aluno_id')
            ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'fa.matricula_id')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->where('mt.ref_cod_turma', $schoolClassId)
            ->where('m.ativo', 1)
            ->where('fcc.quantidade', '>', 0)
            ->distinct()
            ->pluck('fcc.etapa');

        $generalAbsences = DB::table('modules.falta_geral as fg')
            ->join('modules.falta_aluno as fa', 'fa.id', '=', 'fg.falta_aluno_id')
            ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'fa.matricula_id')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->where('mt.ref_cod_turma', $schoolClassId)
            ->where('m.ativo', 1)
            ->where('fg.quantidade', '>', 0)
            ->distinct()
            ->pluck('fg.etapa');

        $scores = DB::table('modules.nota_componente_curricular as ncc')
            ->join('modules.nota_aluno as na', 'na.id', '=', 'ncc.nota_aluno_id')
            ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'na.matricula_id')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->where('mt.ref_cod_turma', $schoolClassId)
            ->where('m.ativo', 1)
            ->distinct()
            ->pluck('ncc.etapa');

        return collect()
            ->merge($componentAbsences)
            ->merge($generalAbsences)
            ->merge($scores)
            ->filter(fn ($etapa) => is_numeric($etapa) && (int) $etapa > 0)
            ->map(fn ($etapa) => (int) $etapa)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Etapas que impedem reduzir o calendário: as que sairão da sequência e as que ainda têm lançamento.
     *
     * @return array<int, int>
     */
    public function getStagesBlockedOnReduction(LegacySchoolClass $schoolClass, int $newStagesCount): array
    {
        $existingSequencials = $schoolClass->stages()
            ->pluck('sequencial')
            ->map(fn ($sequencial) => (int) $sequencial);

        return $existingSequencials
            ->merge($this->getReleasedStageNumbers($schoolClass->cod_turma))
            ->unique()
            ->filter(fn ($etapa) => $etapa > $newStagesCount)
            ->sort()
            ->values()
            ->all();
    }
}
