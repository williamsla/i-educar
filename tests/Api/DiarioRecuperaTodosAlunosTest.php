<?php

namespace Tests\Api;

use App\Models\LegacyEnrollment;
use Database\Factories\LegacyEnrollmentFactory;
use Database\Factories\LegacyRegistrationFactory;
use Database\Factories\LegacySchoolClassFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiarioRecuperaTodosAlunosTest extends TestCase
{
    use DatabaseTransactions;
    use DiarioApiRequestTestTrait;

    public function test_busca_todos_alunos()
    {
        /** @var LegacyEnrollment $enrollments */
        $enrollments = LegacyEnrollmentFactory::new()->create();

        $data = [
            'oper' => 'get',
            'resource' => 'todos-alunos',
            'instituicao_id' => $enrollments->schoolClass->ref_cod_instituicao,
            'escola' => $enrollments->schoolClass->ref_ref_cod_escola,
        ];

        $response = $this->getResource('/module/Api/Aluno', $data);

        $response->assertJsonStructure(
            [
                'alunos',
                'any_error_msg',
                'msgs',
                'oper',
                'resource',
            ]
        );
    }

    public function test_busca_todos_alunos_retorna_cpf()
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        $registration = LegacyRegistrationFactory::new()->withEnrollment($schoolClass)->create();
        $student = $registration->student;

        DB::table('cadastro.fisica')
            ->where('idpes', $student->ref_idpes)
            ->update(['cpf' => 52998224725]);

        $data = [
            'oper' => 'get',
            'resource' => 'todos-alunos',
            'instituicao_id' => $schoolClass->ref_cod_instituicao,
            'escola' => $schoolClass->ref_ref_cod_escola,
        ];

        $response = $this->getResource('/module/Api/Aluno', $data);

        $aluno = collect($response->json('alunos'))->first(function ($aluno) use ($student) {
            return (string) $aluno['aluno_id'] === (string) $student->cod_aluno;
        });

        $this->assertNotNull($aluno);
        $this->assertSame('529.982.247-25', $aluno['cpf']);
    }
}
