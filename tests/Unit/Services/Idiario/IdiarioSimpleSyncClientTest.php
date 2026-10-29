<?php

namespace Tests\Unit\Services\Idiario;

use App\Services\Idiario\IdiarioSimpleSyncClient;
use App\Services\Idiario\IdiarioSync;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdiarioSimpleSyncClientTest extends TestCase
{
    public function test_monta_a_url_da_sincronizacao_simples(): void
    {
        $this->assertSame(
            'https://idiario.exemplo.gov.br/api/v2/synchronizations',
            IdiarioSimpleSyncClient::endpoint('https://idiario.exemplo.gov.br/', '/api/v2/synchronizations')
        );
        $this->assertSame(
            'https://idiario.exemplo.gov.br/api/v2/synchronizations/current',
            IdiarioSimpleSyncClient::endpoint('https://idiario.exemplo.gov.br/api/v1', '/api/v2/synchronizations/current')
        );
    }

    public function test_envia_a_chave_de_acesso_somente_no_header(): void
    {
        config()->set('legacy.config', ['url_diario_professor' => 'https://idiario.exemplo.gov.br']);
        config()->set('legacy.apis.access_key', 'segredo');

        Http::fake([
            'https://idiario.exemplo.gov.br/*' => Http::response([
                'id' => 9,
                'status' => 'started',
                'done_percentage' => 0,
                'time_running' => 0,
                'started_at' => null,
                'full_synchronization' => false,
            ], 200),
        ]);

        $payload = (new IdiarioSimpleSyncClient())->start();

        $this->assertSame('started', $payload['status']);
        $this->assertFalse($payload['full_synchronization']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://idiario.exemplo.gov.br/api/v2/synchronizations'
                && $request->method() === 'POST'
                && $request->hasHeader('token', 'segredo')
                && !str_contains($request->url(), 'access_key');
        });
    }

    public function test_nao_esta_configurado_sem_url_ou_chave(): void
    {
        config()->set('legacy.config', ['url_diario_professor' => '']);
        config()->set('legacy.apis.access_key', '');

        $this->assertFalse(IdiarioSimpleSyncClient::configured());
    }

    public function test_reconhece_as_telas_da_secretaria_que_precisam_de_sync(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/intranet/educar_matricula_cad.php';
        $_SERVER['REQUEST_URI'] = '/intranet/educar_matricula_cad.php';
        $this->assertTrue(IdiarioSync::currentPageNeedsSync());

        $_SERVER['SCRIPT_NAME'] = '/module/index.php';
        $_SERVER['REQUEST_URI'] = '/module/Cadastro/aluno?id=10';
        $this->assertTrue(IdiarioSync::currentPageNeedsSync());

        $_SERVER['SCRIPT_NAME'] = '/module/index.php';
        $_SERVER['REQUEST_URI'] = '/module/ComponenteCurricular/edit?id=3';
        $this->assertTrue(IdiarioSync::currentPageNeedsSync());

        $_SERVER['SCRIPT_NAME'] = '/intranet/educar_index.php';
        $_SERVER['REQUEST_URI'] = '/intranet/educar_index.php';
        $this->assertFalse(IdiarioSync::currentPageNeedsSync());
    }
}
