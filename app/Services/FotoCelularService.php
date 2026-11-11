<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FotoCelularService
{
    public const MINUTOS = 10;

    public const TAMANHO_MAXIMO = 2 * 1024 * 1024;

    public function criar(int $usuarioId, string $urlBase): array
    {
        $token = Str::lower(Str::random(40));

        Cache::put($this->chave($token), [
            'usuario_id' => $usuarioId,
            'pronto' => false,
            'caminho' => null,
            'nome' => null,
            'mime' => null,
        ], now()->addMinutes(self::MINUTOS));

        return [
            'token' => $token,
            'url' => rtrim($urlBase, '/') . '/foto-celular/' . $token,
            'expira_em_minutos' => self::MINUTOS,
        ];
    }

    public function existe(string $token): bool
    {
        return $this->dados($token) !== null;
    }

    public function receber(string $token, UploadedFile $arquivo): bool
    {
        $dados = $this->dados($token);

        if ($dados === null || $dados['pronto'] || $arquivo->getSize() > self::TAMANHO_MAXIMO) {
            return false;
        }

        $imagem = @getimagesize($arquivo->getRealPath());

        if ($imagem === false) {
            return false;
        }

        $extensao = match ($imagem[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            default => null,
        };

        if ($extensao === null) {
            return false;
        }

        $nomeArquivo = $token . '.' . $extensao;
        $caminho = Storage::putFileAs('foto-celular', $arquivo, $nomeArquivo);

        if ($caminho === false) {
            return false;
        }

        $dados['pronto'] = true;
        $dados['caminho'] = $caminho;
        $dados['nome'] = 'foto-aluno.' . $extensao;
        $dados['mime'] = $extensao === 'png' ? 'image/png' : 'image/jpeg';

        Cache::put($this->chave($token), $dados, now()->addMinutes(self::MINUTOS));

        return true;
    }

    public function status(string $token, int $usuarioId): ?array
    {
        $dados = $this->doUsuario($token, $usuarioId);

        if ($dados === null) {
            return null;
        }

        return ['pronto' => (bool) $dados['pronto']];
    }

    public function arquivo(string $token, int $usuarioId): ?array
    {
        $dados = $this->doUsuario($token, $usuarioId);

        if ($dados === null || !$dados['pronto'] || empty($dados['caminho'])) {
            return null;
        }

        if (!Storage::exists($dados['caminho'])) {
            return null;
        }

        return $dados;
    }

    public function descartar(string $token, int $usuarioId): bool
    {
        $dados = $this->doUsuario($token, $usuarioId);

        if ($dados === null) {
            return false;
        }

        if (!empty($dados['caminho'])) {
            Storage::delete($dados['caminho']);
        }

        Cache::forget($this->chave($token));

        return true;
    }

    private function doUsuario(string $token, int $usuarioId): ?array
    {
        $dados = $this->dados($token);

        if ($dados === null || (int) $dados['usuario_id'] !== $usuarioId) {
            return null;
        }

        return $dados;
    }

    private function dados(string $token): ?array
    {
        if (!preg_match('/^[a-z0-9]{40}$/', $token)) {
            return null;
        }

        $dados = Cache::get($this->chave($token));

        return is_array($dados) ? $dados : null;
    }

    private function chave(string $token): string
    {
        return 'foto-celular:' . $token;
    }
}
