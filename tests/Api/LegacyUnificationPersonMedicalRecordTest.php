<?php

namespace Tests\Api;

use Database\Factories\LegacyStudentFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\LoginFirstUser;
use Tests\TestCase;

class LegacyUnificationPersonMedicalRecordTest extends TestCase
{
    use DatabaseTransactions;
    use LoginFirstUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginWithFirstUser();
    }

    public function test_unification_moves_medical_record_to_kept_student(): void
    {
        [$principal, $duplicado] = $this->alunosParaUnificar();

        DB::table('modules.ficha_medica_aluno')->insert([
            'ref_cod_aluno' => $duplicado->getKey(),
            'grupo_sanguineo' => 'AB',
        ]);

        $this->unificar($principal->ref_idpes, $duplicado->ref_idpes);

        $this->assertDatabaseMissing('pmieducar.aluno', [
            'cod_aluno' => $duplicado->getKey(),
        ]);

        $this->assertDatabaseHas('modules.ficha_medica_aluno', [
            'ref_cod_aluno' => $principal->getKey(),
            'grupo_sanguineo' => 'AB',
        ]);
    }

    public function test_unification_keeps_medical_record_of_main_student_when_both_exist(): void
    {
        [$principal, $duplicado] = $this->alunosParaUnificar();

        DB::table('modules.ficha_medica_aluno')->insert([
            [
                'ref_cod_aluno' => $principal->getKey(),
                'grupo_sanguineo' => 'O',
            ],
            [
                'ref_cod_aluno' => $duplicado->getKey(),
                'grupo_sanguineo' => 'AB',
            ],
        ]);

        $this->unificar($principal->ref_idpes, $duplicado->ref_idpes);

        $this->assertDatabaseMissing('pmieducar.aluno', [
            'cod_aluno' => $duplicado->getKey(),
        ]);

        $this->assertDatabaseMissing('modules.ficha_medica_aluno', [
            'ref_cod_aluno' => $duplicado->getKey(),
        ]);

        $this->assertDatabaseHas('modules.ficha_medica_aluno', [
            'ref_cod_aluno' => $principal->getKey(),
            'grupo_sanguineo' => 'O',
        ]);

        $this->assertSame(
            1,
            DB::table('modules.ficha_medica_aluno')->where('ref_cod_aluno', $principal->getKey())->count()
        );
    }

    /**
     * @return array{0: \App\Models\LegacyStudent, 1: \App\Models\LegacyStudent}
     */
    private function alunosParaUnificar(): array
    {
        $principal = LegacyStudentFactory::new()->create();
        $duplicado = LegacyStudentFactory::new()->inactive()->create();

        DB::table('pmieducar.aluno')
            ->where('cod_aluno', $duplicado->getKey())
            ->update([
                'ativo' => 0,
                'data_exclusao' => now(),
            ]);

        return [$principal, $duplicado];
    }

    private function unificar(int $idpesPrincipal, int $idpesDuplicado): void
    {
        $this->post('/intranet/educar_unifica_pessoa.php', [
            'tipoacao' => 'Novo',
            'pessoas' => collect([
                [
                    'idpes' => $idpesPrincipal,
                    'pessoa_principal' => true,
                ],
                [
                    'idpes' => $idpesDuplicado,
                    'pessoa_principal' => false,
                ],
            ]),
        ])->assertSuccessful()
            ->assertSee('Pessoas unificadas com sucesso.');
    }
}
