<?php

namespace Tests\Api;

use App_Model_MatriculaSituacao;
use Database\Factories\LegacyEvaluationRuleFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RegraAvaliacao_Model_TipoPresenca;
use RegraAvaliacao_Model_TipoProgressao;
use Tests\TestCase;

class ConceptualScoreAbsenceTest extends TestCase
{
    use DatabaseTransactions;
    use DiarioApiFakeDataTestTrait;
    use DiarioApiRequestTestTrait;

    /**
     * Nota conceitual com progressão por média e presença: frequência já abaixo
     * do mínimo reprova por faltas antes da última etapa.
     */
    public function test_absence_below_minimum_attendance_reproves_before_last_stage()
    {
        $evaluationRule = LegacyEvaluationRuleFactory::new()->progressaoContinuadaNotaConceitual()->create([
            'tipo_progressao' => RegraAvaliacao_Model_TipoProgressao::NAO_CONTINUADA_MEDIA_PRESENCA,
            'tipo_presenca' => RegraAvaliacao_Model_TipoPresenca::GERAL,
            'porcentagem_presenca' => 75,
        ]);

        $enrollment = $this->getCommonFakeData($evaluationRule);
        $schoolClass = $enrollment->schoolClass;
        $school = $schoolClass->school;
        $registration = $enrollment->registration;

        $this->createStages($school, 2);
        $this->createDisciplines($schoolClass, 1);

        $discipline = $schoolClass->refresh()->disciplines()->first();

        $response = $this->postAbsence($enrollment, $discipline->id, 1, 83);

        $this->assertEquals('Reprovado por faltas', $response->situacao);
        $this->assertEquals(App_Model_MatriculaSituacao::REPROVADO_POR_FALTAS, $registration->refresh()->aprovado);
    }
}
