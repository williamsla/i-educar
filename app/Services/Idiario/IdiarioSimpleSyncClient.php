<?php

namespace App\Services\Idiario;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class IdiarioSimpleSyncClient
{
    /**
     * @return array{id:int|null,status:string|null,done_percentage:int|null,time_running:int|null,started_at:string|null,full_synchronization:bool}
     */
    public function start(): array
    {
        return $this->request('post', '/api/v2/synchronizations');
    }

    /**
     * @return array{id:int|null,status:string|null,done_percentage:int|null,time_running:int|null,started_at:string|null,full_synchronization:bool}
     */
    public function current(): array
    {
        return $this->request('get', '/api/v2/synchronizations/current');
    }

    public static function endpoint(string $baseUrl, string $path): string
    {
        $base = rtrim(trim($baseUrl), '/');
        $base = (string) preg_replace('#/api(?:/v\d+)?$#', '', $base);

        return $base . $path;
    }

    public static function configured(): bool
    {
        return self::baseUrl() !== '' && self::token() !== '';
    }

    /**
     * @return array{id:int|null,status:string|null,done_percentage:int|null,time_running:int|null,started_at:string|null,full_synchronization:bool}
     */
    private function request(string $method, string $path): array
    {
        $baseUrl = self::baseUrl();
        $token = self::token();

        if ($baseUrl === '' || $token === '') {
            throw new IdiarioSimpleSyncException('Configure a URL do Diário do Professor e a chave de acesso.');
        }

        try {
            $response = Http::withHeaders([
                'token' => $token,
                'Accept' => 'application/json',
            ])->timeout(8)->connectTimeout(3)->send($method, self::endpoint($baseUrl, $path));
        } catch (ConnectionException $exception) {
            throw new IdiarioSimpleSyncException('Não foi possível conectar ao i-Diário.', 0, $exception);
        }

        if ($response->failed()) {
            throw new IdiarioSimpleSyncException('Não foi possível atualizar o i-Diário.');
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new IdiarioSimpleSyncException('Não foi possível atualizar o i-Diário.');
        }

        return [
            'id' => isset($body['id']) ? (int) $body['id'] : null,
            'status' => isset($body['status']) ? (string) $body['status'] : null,
            'done_percentage' => isset($body['done_percentage']) ? (int) $body['done_percentage'] : null,
            'time_running' => isset($body['time_running']) ? (int) $body['time_running'] : null,
            'started_at' => isset($body['started_at']) ? (string) $body['started_at'] : null,
            'full_synchronization' => (bool) ($body['full_synchronization'] ?? false),
        ];
    }

    private static function baseUrl(): string
    {
        $config = config('legacy.config');

        if (is_array($config)) {
            return trim((string) ($config['url_diario_professor'] ?? ''));
        }

        if (is_object($config)) {
            return trim((string) ($config->url_diario_professor ?? ''));
        }

        return '';
    }

    private static function token(): string
    {
        return trim((string) config('legacy.apis.access_key'));
    }
}
