<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\XssByPass;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class XssByPassTest extends TestCase
{
    public function test_define_cabecalho_em_resposta_html(): void
    {
        $middleware = new XssByPass;
        $response = $middleware->handle(Request::create('/exportacao-sgp'), function () {
            return new Response('ok');
        });

        $this->assertSame('0', $response->headers->get('X-XSS-Protection'));
    }

    public function test_define_cabecalho_em_download_de_arquivo(): void
    {
        $middleware = new XssByPass;
        $path = tempnam(sys_get_temp_dir(), 'sgp_');
        file_put_contents($path, 'xlsx');

        $response = $middleware->handle(Request::create('/exportacao-sgp', 'POST'), function () use ($path) {
            return new BinaryFileResponse($path);
        });

        $this->assertSame('0', $response->headers->get('X-XSS-Protection'));
        @unlink($path);
    }
}
