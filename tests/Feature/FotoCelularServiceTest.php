<?php

namespace Tests\Feature;

use App\Services\FotoCelularService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoCelularServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Cache::flush();
    }

    public function test_foto_do_celular_fica_disponivel_somente_para_quem_gerou_o_codigo(): void
    {
        $service = app(FotoCelularService::class);
        $criado = $service->criar(15, 'https://escola.test');

        $this->get('/foto-celular/' . $criado['token'])
            ->assertOk()
            ->assertSee('Foto do aluno');

        $foto = UploadedFile::fake()->image('aluno.jpg', 120, 160);

        $this->post('/foto-celular/' . $criado['token'], ['foto' => $foto])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue($service->status($criado['token'], 15)['pronto']);
        $this->assertNull($service->status($criado['token'], 99));

        $arquivo = $service->arquivo($criado['token'], 15);
        $this->assertNotNull($arquivo);
        $this->assertNull($service->arquivo($criado['token'], 99));
        Storage::assertExists($arquivo['caminho']);

        $this->post('/foto-celular/' . $criado['token'], ['foto' => $foto])
            ->assertStatus(422);

        $this->assertTrue($service->descartar($criado['token'], 15));
        $this->assertFalse($service->existe($criado['token']));
        Storage::assertMissing($arquivo['caminho']);
    }

    public function test_codigo_inexistente_informa_que_expirou(): void
    {
        $this->get('/foto-celular/' . str_repeat('a', 40))
            ->assertNotFound()
            ->assertSee('Código expirado');
    }
}
