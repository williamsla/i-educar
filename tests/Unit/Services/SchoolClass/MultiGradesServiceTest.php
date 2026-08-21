<?php

namespace Tests\Unit\Services\SchoolClass;

use App\Models\LegacyEvaluationRule;
use App\Services\SchoolClass\MultiGradesService;
use Database\Factories\LegacyEvaluationRuleFactory;
use Database\Factories\LegacyEvaluationRuleGradeYearFactory;
use Database\Factories\LegacyGradeFactory;
use Database\Factories\LegacySchoolClassFactory;
use Database\Factories\LegacySchoolClassGradeFactory;
use Database\Factories\LegacySchoolGradeFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiGradesServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MultiGradesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MultiGradesService;
    }

    public function test_allows_saving_existing_multigrade_class_with_incompatible_retake_types(): void
    {
        [$schoolClass, $gradesPayload] = $this->createMultigradeClassWithIncompatibleRetakeTypes();

        $this->service->storeSchoolClassGrade($schoolClass, $gradesPayload);

        $this->assertEqualsCanonicalizing(
            array_column($gradesPayload, 'serie_id'),
            $schoolClass->multigrades()->pluck('serie_id')->all()
        );
    }

    public function test_rejects_new_multigrade_class_with_incompatible_retake_types(): void
    {
        $schoolClass = LegacySchoolClassFactory::new()->multiplesGrades()->create();
        $gradesPayload = $this->createIncompatibleGradesPayload($schoolClass);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('As séries selecionadas devem possuir o mesmo tipo de recuperação.');

        $this->service->storeSchoolClassGrade($schoolClass, $gradesPayload);
    }

    private function createMultigradeClassWithIncompatibleRetakeTypes(): array
    {
        $schoolClass = LegacySchoolClassFactory::new()->multiplesGrades()->create();
        $gradesPayload = $this->createIncompatibleGradesPayload($schoolClass);

        foreach ($gradesPayload as $grade) {
            LegacySchoolClassGradeFactory::new()->create([
                'escola_id' => $schoolClass->ref_ref_cod_escola,
                'serie_id' => $grade['serie_id'],
                'turma_id' => $schoolClass,
                'boletim_id' => $grade['boletim_id'],
                'boletim_diferenciado_id' => $grade['boletim_diferenciado_id'],
            ]);
        }

        return [$schoolClass, $gradesPayload];
    }

    private function createIncompatibleGradesPayload($schoolClass): array
    {
        $year = $schoolClass->ano;
        $schoolId = $schoolClass->ref_ref_cod_escola;

        $ruleByStage = LegacyEvaluationRuleFactory::new()->create([
            'tipo_presenca' => 1,
            'parecer_descritivo' => 0,
            'tipo_recuperacao_paralela' => LegacyEvaluationRule::PARALLEL_REMEDIAL_PER_STAGE,
        ]);
        $ruleBySpecificStage = LegacyEvaluationRuleFactory::new()->create([
            'tipo_presenca' => 1,
            'parecer_descritivo' => 0,
            'tipo_recuperacao_paralela' => LegacyEvaluationRule::PARALLEL_REMEDIAL_PER_SPECIFIC_STAGE,
        ]);

        $firstGrade = LegacyGradeFactory::new()->create(['ref_cod_curso' => $schoolClass->ref_cod_curso]);
        $secondGrade = LegacyGradeFactory::new()->create(['ref_cod_curso' => $schoolClass->ref_cod_curso]);

        LegacySchoolGradeFactory::new()->create([
            'ref_cod_escola' => $schoolId,
            'ref_cod_serie' => $firstGrade,
        ]);
        LegacySchoolGradeFactory::new()->create([
            'ref_cod_escola' => $schoolId,
            'ref_cod_serie' => $secondGrade,
        ]);

        LegacyEvaluationRuleGradeYearFactory::new()->create([
            'serie_id' => $firstGrade,
            'regra_avaliacao_id' => $ruleByStage,
            'regra_avaliacao_diferenciada_id' => null,
            'ano_letivo' => $year,
        ]);
        LegacyEvaluationRuleGradeYearFactory::new()->create([
            'serie_id' => $secondGrade,
            'regra_avaliacao_id' => $ruleBySpecificStage,
            'regra_avaliacao_diferenciada_id' => null,
            'ano_letivo' => $year,
        ]);

        return [
            [
                'escola_id' => $schoolId,
                'serie_id' => $firstGrade->getKey(),
                'turma_id' => $schoolClass->getKey(),
                'boletim_id' => 1,
                'boletim_diferenciado_id' => null,
            ],
            [
                'escola_id' => $schoolId,
                'serie_id' => $secondGrade->getKey(),
                'turma_id' => $schoolClass->getKey(),
                'boletim_id' => 1,
                'boletim_diferenciado_id' => null,
            ],
        ];
    }
}
