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

    /**
     * frequencia_da_matricula usa a carga horária da série (800) e hora-aula 0,75.
     * 80 faltas: 100 - (80 * 0,75 * 100 / 800) = 92,5%, acima dos 75%.
     */
    public function test_component_absences_do_not_reprove_when_frequency_stays_above_minimum()
    {
        $evaluationRule = LegacyEvaluationRuleFactory::new()->progressaoContinuadaNotaConceitual()->create([
            'tipo_progressao' => RegraAvaliacao_Model_TipoProgressao::NAO_CONTINUADA_MEDIA_PRESENCA,
            'tipo_presenca' => RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE,
            'porcentagem_presenca' => 75,
        ]);

        $enrollment = $this->getCommonFakeData($evaluationRule);
        $schoolClass = $enrollment->schoolClass;
        $school = $schoolClass->school;
        $registration = $enrollment->registration;

        $this->createStages($school, 2);
        $this->createDisciplines($schoolClass, 4);

        foreach ($schoolClass->refresh()->disciplines as $discipline) {
            $response = $this->postAbsence($enrollment, $discipline->id, 1, 20);
            $this->assertEquals('Cursando', $response->situacao);
        }

        $this->assertEquals(App_Model_MatriculaSituacao::EM_ANDAMENTO, $registration->refresh()->aprovado);
    }

    /**
     * Com carga horária da série em 400, 160 faltas e hora-aula 0,75:
     * 100 - (160 * 0,75 * 100 / 400) = 70%, abaixo dos 75%.
     */
    public function test_component_absences_reprove_when_frequency_is_below_minimum()
    {
        $evaluationRule = LegacyEvaluationRuleFactory::new()->progressaoContinuadaNotaConceitual()->create([
            'tipo_progressao' => RegraAvaliacao_Model_TipoProgressao::NAO_CONTINUADA_MEDIA_PRESENCA,
            'tipo_presenca' => RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE,
            'porcentagem_presenca' => 75,
        ]);

        $enrollment = $this->getCommonFakeData($evaluationRule);
        $schoolClass = $enrollment->schoolClass;
        $school = $schoolClass->school;
        $registration = $enrollment->registration;
        $schoolClass->grade->update(['carga_horaria' => 400]);

        $this->createStages($school, 2);
        $this->createDisciplines($schoolClass, 4);

        $response = null;

        foreach ($schoolClass->refresh()->disciplines as $discipline) {
            $response = $this->postAbsence($enrollment, $discipline->id, 1, 40);
        }

        $this->assertEquals('Reprovado por faltas', $response->situacao);
        $this->assertEquals(App_Model_MatriculaSituacao::REPROVADO_POR_FALTAS, $registration->refresh()->aprovado);
    }
}
