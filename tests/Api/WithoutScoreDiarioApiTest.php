<?php

namespace Tests\Api;

use App\Models\LegacyEvaluationRule;
use App_Model_MatriculaSituacao;
use Database\Factories\LegacyEvaluationRuleFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Database\Factories\LegacyUserFactory;
use RegraAvaliacao_Model_TipoParecerDescritivo;
use RegraAvaliacao_Model_TipoPresenca;
use RegraAvaliacao_Model_TipoProgressao;
use Tests\TestCase;

class WithoutScoreDiarioApiTest extends TestCase
{
    use DatabaseTransactions;
    use DiarioApiFakeDataTestTrait;
    use DiarioApiRequestTestTrait;

    /**
     * @var LegacyEvaluationRule
     */
    private $evaluationRule;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluationRule = LegacyEvaluationRuleFactory::new()->withoutScore()->create();
    }

    /**
     * O aluno deve ser aprovado ao lançar todas as faltas
     */
    public function test_post_absence_should_returns_approved()
    {
        $enrollment = $this->getCommonFakeData($this->evaluationRule);
        $schoolClass = $enrollment->schoolClass;
        $school = $schoolClass->school;
        $registration = $enrollment->registration;

        $this->createStages($school, 1);
        $this->createDisciplines($schoolClass, 1);

        $discipline = $schoolClass->refresh()->disciplines()->first();

        $response = $this->postAbsence($enrollment, $discipline->id, 1, 10);

        $this->assertEquals('Aprovado', $response->situacao);
        $this->assertEquals(App_Model_MatriculaSituacao::APROVADO, $registration->refresh()->aprovado);
    }

    /**
     * O aluno deve continuar cursando quando não forem lançadas as faltas de todas as etapas
     */
    public function test_post_a_part_of_absence_should_returns_studying()
    {
        $enrollment = $this->getCommonFakeData($this->evaluationRule);
        $schoolClass = $enrollment->schoolClass;
        $school = $schoolClass->school;
        $registration = $enrollment->registration;

        $this->createStages($school, 2);
        $this->createDisciplines($schoolClass, 1);

        $discipline = $schoolClass->refresh()->disciplines()->first();

        $response = $this->postAbsence($enrollment, $discipline->id, 1, 10);

        $this->assertEquals('Cursando', $response->situacao);
        $this->assertEquals(App_Model_MatriculaSituacao::EM_ANDAMENTO, $registration->refresh()->aprovado);
    }

    /**
     * Sem nota e com progressão por média e presença, frequência já abaixo do
     * mínimo reprova por faltas antes da última etapa.
     */
    public function test_absence_below_minimum_attendance_reproves_before_last_stage()
    {
        $evaluationRule = LegacyEvaluationRuleFactory::new()->withoutScore()->create([
            'tipo_progressao' => RegraAvaliacao_Model_TipoProgressao::NAO_CONTINUADA_MEDIA_PRESENCA,
            'tipo_presenca' => RegraAvaliacao_Model_TipoPresenca::GERAL,
            'parecer_descritivo' => RegraAvaliacao_Model_TipoParecerDescritivo::ETAPA_GERAL,
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

        $response = $this->postGeneralOpinion($enrollment, 1, 'Vem vindo com pouca frequência a escola.');
        $response = $this->postGeneralOpinion($enrollment, 2, 'Tem comparecido as aulas com pouca frequência.');

        $this->assertEquals('Reprovado por faltas', $response->situacao);
        $this->assertEquals(App_Model_MatriculaSituacao::REPROVADO_POR_FALTAS, $registration->refresh()->aprovado);
    }

    private function postGeneralOpinion($enrollment, $stage, string $opinion)
    {
        $this->cleanGlobals();

        $schoolClass = $enrollment->schoolClass;
        $data = [
            'resource' => 'parecer',
            'oper' => 'post',
            'instituicao_id' => $schoolClass->school->institution->id,
            'escola_id' => $schoolClass->school_id,
            'curso_id' => $schoolClass->course_id,
            'serie_id' => $schoolClass->grade_id,
            'turma_id' => $schoolClass->id,
            'ano_escolar' => $schoolClass->year,
            'etapa' => $stage,
            'matricula_id' => $enrollment->registration->id,
            'att_value' => $opinion,
            'access_key' => env('API_ACCESS_KEY'),
            'secret_key' => env('API_SECRET_KEY'),
        ];

        $_GET = $data;

        $response = $this->actingAs(LegacyUserFactory::new()->admin()->make())
            ->get('/module/Avaliacao/diarioApi?' . http_build_query($data));

        return json_decode($response->content());
    }
}
