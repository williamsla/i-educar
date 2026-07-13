<?php

namespace Tests\Api;

use App\Models\LegacyStudent;
use App\Models\LogUnification;
use Database\Factories\LegacyStudentFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\LoginFirstUser;
use Tests\TestCase;

class LegacyUnificationStudentInactivePrincipalTest extends TestCase
{
    use DatabaseTransactions;
    use LoginFirstUser;

    private LegacyStudent $studentInactive;

    private LegacyStudent $studentActive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginWithFirstUser();

        $this->studentInactive = LegacyStudentFactory::new()->create();
        $this->studentActive = LegacyStudentFactory::new()->create();

        DB::table('pmieducar.aluno')
            ->where('cod_aluno', $this->studentInactive->getKey())
            ->update([
                'ativo' => 0,
                'data_exclusao' => now(),
            ]);
    }

    public function test_unification_with_inactive_principal_reactivates_principal(): void
    {
        $request = [
            'tipoacao' => 'Novo',
        ];

        $data = [
            'alunos' => collect([
                [
                    'codAluno' => $this->studentInactive->getKey(),
                    'aluno_principal' => true,
                ],
                [
                    'codAluno' => $this->studentActive->getKey(),
                    'aluno_principal' => false,
                ],
            ]),
        ];

        $payload = array_merge($request, $data);

        $this->post('/intranet/educar_unifica_aluno.php', $payload)
            ->assertRedirectContains(route('student-log-unification.index'));

        $log = LogUnification::query()
            ->where('main_id', $this->studentInactive->getKey())
            ->where('type', 'App\Models\Student')
            ->where('active', true)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($log->duplicates_id, [$this->studentActive->getKey()]);

        $this->assertDatabaseHas($this->studentInactive, [
            'cod_aluno' => $this->studentInactive->getKey(),
            'ativo' => 1,
            'data_exclusao' => null,
            'ref_usuario_exc' => null,
        ])->assertDatabaseHas($this->studentActive, [
            'cod_aluno' => $this->studentActive->getKey(),
            'ativo' => 0,
        ]);
    }
}
