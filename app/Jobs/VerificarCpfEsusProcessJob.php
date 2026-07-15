<?php

namespace App\Jobs;

use App\Services\EsusPdfCpfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerificarCpfEsusProcessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        private readonly string $token,
        private readonly string $databaseConnection,
        private readonly string $storagePath,
        private readonly string $tipoFonte,
        private readonly string $extensao,
        private readonly int $anoLetivo,
        private readonly bool $excluirSomenteCnsSemCpf,
        private readonly int $userId,
    ) {}

    public function handle(EsusPdfCpfService $service): void
    {
        DB::setDefaultConnection($this->databaseConnection);
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->atualizarStatus([
            'status' => 'processing',
            'mensagem' => 'Processando arquivo…',
        ]);

        // Mesmo caminho usado no upload: storage/app/{storagePath}
        // (o disco "local" deste projeto aponta para storage/app/public — não usar).
        $absolutePath = $this->caminhoAbsolutoArquivo();
        if (! is_file($absolutePath)) {
            Log::warning('VerificarCpfEsus: arquivo temporário ausente no worker', [
                'token' => $this->token,
                'path' => $absolutePath,
            ]);
            $this->atualizarStatus([
                'status' => 'failed',
                'sucesso' => false,
                'mensagem' => 'Arquivo temporário não encontrado. Envie o arquivo novamente.',
                'resultado' => [
                    'cpfs_extraidos' => 0,
                    'ano_letivo' => $this->anoLetivo,
                    'cpfs_nao_cadastrados' => [],
                    'erro' => 'Arquivo temporário não encontrado.',
                ],
            ]);

            return;
        }

        try {
            if ($this->tipoFonte === 'cadastro_cidadao') {
                $resultado = $service->processarXlsxCadastroCidadao(
                    $absolutePath,
                    $this->anoLetivo,
                    $this->excluirSomenteCnsSemCpf
                );
            } elseif ($this->extensao === 'csv') {
                $resultado = $service->processarCsv(
                    $absolutePath,
                    $this->anoLetivo,
                    $this->excluirSomenteCnsSemCpf
                );
            } else {
                $resultado = $service->processarPdf(
                    $absolutePath,
                    $this->anoLetivo,
                    $this->excluirSomenteCnsSemCpf
                );
            }

            $resultado['tipo_fonte'] = $this->tipoFonte;

            if (! empty($resultado['erro'])) {
                Log::warning('VerificarCpfEsus: falha no processamento', [
                    'token' => $this->token,
                    'erro' => $resultado['erro'],
                ]);
                $this->atualizarStatus([
                    'status' => 'failed',
                    'sucesso' => false,
                    'mensagem' => 'Erro ao processar o arquivo: '.$resultado['erro'],
                    'resultado' => $resultado,
                ]);

                return;
            }

            $nSem = count($resultado['cpfs_nao_cadastrados'] ?? []);
            $nCom = count($resultado['cpfs_com_matricula'] ?? []);
            $ano = (int) ($resultado['ano_letivo'] ?? $this->anoLetivo);
            $mensagem = sprintf(
                'Foram lidos %d CPF(s) no arquivo. %d com matrícula ativa e %d sem matrícula ativa em %d. Veja o resumo e use Exportar relatório em PDF.',
                (int) $resultado['cpfs_extraidos'],
                $nCom,
                $nSem,
                $ano
            );

            $this->atualizarStatus([
                'status' => 'done',
                'sucesso' => true,
                'mensagem' => $mensagem,
                'resultado' => $resultado,
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::error('VerificarCpfEsus: exceção no worker', [
                'token' => $this->token,
                'message' => $e->getMessage(),
            ]);
            $this->atualizarStatus([
                'status' => 'failed',
                'sucesso' => false,
                'mensagem' => 'Erro ao processar o arquivo: '.$e->getMessage(),
                'resultado' => [
                    'cpfs_extraidos' => 0,
                    'ano_letivo' => $this->anoLetivo,
                    'cpfs_nao_cadastrados' => [],
                    'erro' => $e->getMessage(),
                ],
            ]);
        } finally {
            $this->removerArquivoTemporario();
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('VerificarCpfEsus: job failed()', [
            'token' => $this->token,
            'message' => $exception?->getMessage(),
        ]);
        $this->atualizarStatus([
            'status' => 'failed',
            'sucesso' => false,
            'mensagem' => 'Erro ao processar o arquivo: '.($exception?->getMessage() ?: 'falha na fila'),
            'resultado' => [
                'cpfs_extraidos' => 0,
                'ano_letivo' => $this->anoLetivo,
                'cpfs_nao_cadastrados' => [],
                'erro' => $exception?->getMessage() ?: 'falha na fila',
            ],
        ]);
        $this->removerArquivoTemporario();
    }

    private function caminhoAbsolutoArquivo(): string
    {
        return storage_path('app/'.$this->storagePath);
    }

    private function removerArquivoTemporario(): void
    {
        $path = $this->caminhoAbsolutoArquivo();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function atualizarStatus(array $dados): void
    {
        self::gravarStatus($this->token, $dados, $this->userId);
    }

    /**
     * Status no volume storage (compartilhado entre FPM e worker) + Cache opcional.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function gravarStatus(string $token, array $dados, ?int $userId = null): array
    {
        $atual = self::lerStatus($token) ?? [];
        $payload = array_merge($atual, $dados, [
            'token' => $token,
            'atualizado_em' => now()->toIso8601String(),
        ]);

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        $path = self::statusPath($token);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        try {
            Cache::put(self::cacheKey($token), $payload, now()->addHours(2));
        } catch (Throwable $e) {
            Log::warning('VerificarCpfEsus: falha ao gravar status no Cache (usando arquivo)', [
                'token' => $token,
                'message' => $e->getMessage(),
            ]);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function lerStatus(string $token): ?array
    {
        $path = self::statusPath($token);
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        try {
            $fromCache = Cache::get(self::cacheKey($token));
            if (is_array($fromCache)) {
                return $fromCache;
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }

    public static function statusPath(string $token): string
    {
        return storage_path('app/temp/verificar-cpf-esus/'.$token.'.status.json');
    }

    public static function cacheKey(string $token): string
    {
        return 'verificar_cpf_esus:job:'.$token;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            $this->databaseConnection,
            'verificar-cpf-esus',
        ];
    }
}
