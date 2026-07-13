<?php

namespace Tests\Api;

use App\Models\Employee;
use App\Models\EmployeeAllocation;
use Database\Factories\EmployeeAllocationFactory;
use Database\Factories\EmployeeFactory;
use Database\Factories\LegacyEmployeeRoleFactory;
use Database\Factories\LegacyIndividualFactory;
use Database\Factories\LegacyInstitutionFactory;
use Database\Factories\LegacySchoolFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\LoginFirstUser;
use Tests\TestCase;

class LegacyUnificationPersonWithEmployeeAllocationTest extends TestCase
{
    use DatabaseTransactions;
    use LoginFirstUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginWithFirstUser();
    }

    /**
     * Cenário: pessoa principal sem servidor; duplicado com servidor + alocação.
     * Antes da correção, o UPDATE em servidor_alocacao falhava por FK composta.
     */
    public function test_unification_person_when_only_duplicate_is_employee_with_allocation(): void
    {
        $institution = LegacyInstitutionFactory::new()->current();
        $principal = LegacyIndividualFactory::new()->create();
        $duplicado = LegacyIndividualFactory::new()->create();

        $employee = EmployeeFactory::new()->create([
            'id' => $duplicado->getKey(),
            'institution_id' => $institution,
        ]);

        $role = LegacyEmployeeRoleFactory::new()->create([
            'ref_cod_servidor' => $employee->getKey(),
            'ref_ref_cod_instituicao' => $institution->getKey(),
        ]);

        $allocation = EmployeeAllocationFactory::new()->create([
            'ref_cod_servidor' => $employee->getKey(),
            'ref_ref_cod_instituicao' => $institution->getKey(),
            'ref_cod_escola' => LegacySchoolFactory::new()->create([
                'ref_cod_instituicao' => $institution->getKey(),
            ]),
            'ref_cod_servidor_funcao' => $role->getKey(),
            'data_saida' => null,
        ]);

        $payload = [
            'tipoacao' => 'Novo',
            'pessoas' => collect([
                [
                    'idpes' => $principal->getKey(),
                    'pessoa_principal' => true,
                ],
                [
                    'idpes' => $duplicado->getKey(),
                    'pessoa_principal' => false,
                ],
            ]),
        ];

        $this->post('/intranet/educar_unifica_pessoa.php', $payload)
            ->assertSuccessful()
            ->assertSee('Pessoas unificadas com sucesso.');

        $this->assertDatabaseHas(Employee::class, [
            'cod_servidor' => $principal->getKey(),
            'ref_cod_instituicao' => $institution->getKey(),
        ]);

        $this->assertDatabaseMissing(Employee::class, [
            'cod_servidor' => $duplicado->getKey(),
            'ref_cod_instituicao' => $institution->getKey(),
        ]);

        $this->assertDatabaseHas(EmployeeAllocation::class, [
            'cod_servidor_alocacao' => $allocation->getKey(),
            'ref_cod_servidor' => $principal->getKey(),
            'ref_ref_cod_instituicao' => $institution->getKey(),
        ]);
    }
}
