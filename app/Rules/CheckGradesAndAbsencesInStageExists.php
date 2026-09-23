<?php

namespace App\Rules;

use App\Services\iDiarioService;
use App\Services\SchoolClassStageService;
use Dotenv\Exception\ValidationException;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckGradesAndAbsencesInStageExists implements Rule
{
    /** @var array<int, int> */
    private array $stagesWithScores = [];

    /** @var array<int, int> */
    private array $stagesWithAbsences = [];

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $turmaId = $value['schoolClass']->cod_turma;
        $anoTurma = $value['schoolClass']->ano;
        $etapasCount = count($value['startDates']);
        $etapasCountAntigo = $value['schoolClass']->stages()->count();

        if ($etapasCount < $etapasCountAntigo) {
            $etapas = app(SchoolClassStageService::class)
                ->getStagesBlockedOnReduction($value['schoolClass'], $etapasCount);

            if ($etapas === []) {
                return true;
            }

            $this->stagesWithAbsences = $this->stageNumbers(
                DB::table('modules.falta_componente_curricular as fcc')
                    ->join('modules.falta_aluno as fa', 'fa.id', '=', 'fcc.falta_aluno_id')
                    ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'fa.matricula_id')
                    ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
                    ->whereIn('fcc.etapa', $etapas)
                    ->where('mt.ref_cod_turma', $turmaId)
                    ->where('mt.ativo', 1)
                    ->where('m.ativo', 1)
                    ->where('fcc.quantidade', '>', 0)
                    ->distinct()
                    ->pluck('fcc.etapa')
            );

            $this->stagesWithAbsences = array_values(array_unique(array_merge(
                $this->stagesWithAbsences,
                $this->stageNumbers(
                    DB::table('modules.falta_geral as fg')
                        ->join('modules.falta_aluno as fa', 'fa.id', '=', 'fg.falta_aluno_id')
                        ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'fa.matricula_id')
                        ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
                        ->whereIn('fg.etapa', $etapas)
                        ->where('mt.ref_cod_turma', $turmaId)
                        ->where('mt.ativo', 1)
                        ->where('m.ativo', 1)
                        ->where('fg.quantidade', '>', 0)
                        ->distinct()
                        ->pluck('fg.etapa')
                )
            )));

            $this->stagesWithScores = $this->stageNumbers(
                DB::table('modules.nota_componente_curricular as ncc')
                    ->join('modules.nota_aluno as na', 'na.id', '=', 'ncc.nota_aluno_id')
                    ->join('pmieducar.matricula as m', 'm.cod_matricula', '=', 'na.matricula_id')
                    ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
                    ->whereIn('ncc.etapa', $etapas)
                    ->where('mt.ref_cod_turma', $turmaId)
                    ->where('mt.ativo', 1)
                    ->where('m.ativo', 1)
                    ->where(function ($query) {
                        app(SchoolClassStageService::class)->whereHasLaunchedScore($query);
                    })
                    ->distinct()
                    ->pluck('ncc.etapa')
            );

            sort($this->stagesWithAbsences);
            sort($this->stagesWithScores);

            if ($this->stagesWithScores !== [] || $this->stagesWithAbsences !== []) {
                return false;
            }

            // Caso não exista token e URL de integração com o i-Diário, não irá
            // validar se há lançamentos nas etapas removidas

            $checkReleases = config('legacy.config.url_novo_educacao')
                && config('legacy.config.token_novo_educacao');

            if (!$checkReleases) {
                return true;
            }

            $iDiarioService = app(iDiarioService::class);

            foreach ($etapas as $etapa) {
                if ($iDiarioService->getStepActivityByClassroom($turmaId, $anoTurma, $etapa)) {
                    throw new ValidationException('Não foi possível remover uma das etapas pois existem notas ou faltas lançadas no diário online.');
                }
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $parts = [];

        if ($this->stagesWithScores !== []) {
            $parts[] = 'notas na(s) etapa(s) ' . $this->formatStages($this->stagesWithScores);
        }

        if ($this->stagesWithAbsences !== []) {
            $parts[] = 'faltas na(s) etapa(s) ' . $this->formatStages($this->stagesWithAbsences);
        }

        if ($parts === []) {
            return 'Não foi possível remover uma das etapas pois existem notas ou faltas lançadas.';
        }

        return 'Não foi possível remover etapas pois ainda existem ' . implode(' e ', $parts) . '.';
    }

    /**
     * @param Collection<int, mixed> $stages
     * @return array<int, int>
     */
    private function stageNumbers($stages): array
    {
        return collect($stages)
            ->filter(fn ($stage) => is_numeric($stage))
            ->map(fn ($stage) => (int) $stage)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $stages
     */
    private function formatStages(array $stages): string
    {
        return implode(', ', array_map(fn (int $stage) => $stage . 'ª', $stages));
    }
}
