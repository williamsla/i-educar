<?php

namespace App\Http\Controllers;

use App\Services\FotoCelularService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FotoCelularController extends Controller
{
    public function __construct(private FotoCelularService $fotos)
    {
    }

    public function criar(Request $request)
    {
        $base = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');

        return response()->json(
            $this->fotos->criar((int) $request->user()->getAuthIdentifier(), $base)
        );
    }

    public function mostrar(string $token)
    {
        if (!$this->fotos->existe($token)) {
            return response()->view('foto-celular.expirado', [], 404);
        }

        return view('foto-celular.captura', [
            'expiraEmMinutos' => FotoCelularService::MINUTOS,
        ]);
    }

    public function receber(Request $request, string $token)
    {
        if (!$this->fotos->existe($token)) {
            return response()->json([
                'mensagem' => 'Este código expirou. Gere outro no computador.',
            ], 404);
        }

        $arquivo = $request->file('foto');

        if ($arquivo === null || !$this->fotos->receber($token, $arquivo)) {
            return response()->json([
                'mensagem' => 'Envie uma imagem JPG ou PNG de até 2MB. Se a foto já foi enviada, gere um novo código no computador.',
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, string $token)
    {
        $status = $this->fotos->status($token, (int) $request->user()->getAuthIdentifier());

        if ($status === null) {
            return response()->json([
                'mensagem' => 'O código expirou. Gere outro QR code.',
            ], 404);
        }

        return response()->json($status);
    }

    public function arquivo(Request $request, string $token)
    {
        $arquivo = $this->fotos->arquivo($token, (int) $request->user()->getAuthIdentifier());

        if ($arquivo === null) {
            return response()->json([
                'mensagem' => 'A foto ainda não chegou.',
            ], 404);
        }

        return Storage::response($arquivo['caminho'], $arquivo['nome'], [
            'Content-Type' => $arquivo['mime'],
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function descartar(Request $request, string $token)
    {
        $descartou = $this->fotos->descartar($token, (int) $request->user()->getAuthIdentifier());

        return response()->json(['ok' => $descartou], $descartou ? 200 : 404);
    }
}
