<?php

namespace Tests\Api;

use App\Models\LegacyIndividual;
use App\Models\LogUnification;
use Database\Factories\LegacyIndividualFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\LoginFirstUser;
use Tests\TestCase;

class LegacyUnificationPersonInactivePrincipalTest extends TestCase
{
    use DatabaseTransactions;
    use LoginFirstUser;

    private LegacyIndividual $individualInactive;

    private LegacyIndividual $individualActive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginWithFirstUser();

        $this->individualInactive = LegacyIndividualFactory::new()->create();
        $this->individualActive = LegacyIndividualFactory::new()->create();

        DB::table('cadastro.fisica')
            ->where('idpes', $this->individualInactive->getKey())
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
            'pessoas' => collect([
                [
                    'idpes' => $this->individualInactive->getKey(),
                    'pessoa_principal' => true,
                ],
                [
                    'idpes' => $this->individualActive->getKey(),
                    'pessoa_principal' => false,
                ],
            ]),
        ];

        $payload = array_merge($request, $data);

        $this->post('/intranet/educar_unifica_pessoa.php', $payload)
            ->assertSuccessful()
            ->assertSee('Pessoas unificadas com sucesso.');

        $log = LogUnification::query()
            ->where('main_id', $this->individualInactive->getKey())
            ->where('type', 'App\Models\Individual')
            ->where('active', true)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($log->duplicates_id, [$this->individualActive->getKey()]);

        $this->assertDatabaseHas($this->individualInactive, [
            'idpes' => $this->individualInactive->getKey(),
            'ativo' => 1,
            'data_exclusao' => null,
            'ref_usuario_exc' => null,
        ]);

        $this->assertDatabaseMissing($this->individualActive->getTable(), [
            'idpes' => $this->individualActive->getKey(),
        ]);
    }
}
