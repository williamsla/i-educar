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
use Illuminate\Support\Facades\Storage;
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

        $this->atualizarStatus([
            'status' => 'processing',
            'mensagem' => 'Processando arquivo…',
        ]);

        $absolutePath = Storage::disk('local')->path($this->storagePath);
        if (! is_file($absolutePath)) {
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

            if (! empty($resultado['erro'])) {
                $this->atualizarStatus([
                    'status' => 'failed',
                    'sucesso' => false,
                    'mensagem' => 'Erro ao processar o arquivo: '.$resultado['erro'],
                    'resultado' => $resultado,
                ]);

                return;
            }

            $n = count($resultado['cpfs_nao_cadastrados'] ?? []);
            $ano = (int) ($resultado['ano_letivo'] ?? $this->anoLetivo);
            if ($n === 0) {
                $mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no arquivo. Todos possuem matrícula ativa em %d.',
                    (int) $resultado['cpfs_extraidos'],
                    $ano
                );
            } else {
                $mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no arquivo. %d não possuem matrícula ativa em %d. Veja o resumo e use Exportar relatório em PDF para a lista completa.',
                    (int) $resultado['cpfs_extraidos'],
                    $n,
                    $ano
                );
            }

            $this->atualizarStatus([
                'status' => 'done',
                'sucesso' => true,
                'mensagem' => $mensagem,
                'resultado' => $resultado,
            ]);
        } catch (Throwable $e) {
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
            Storage::disk('local')->delete($this->storagePath);
        }
    }

    public function failed(?Throwable $exception = null): void
    {
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
        Storage::disk('local')->delete($this->storagePath);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function atualizarStatus(array $dados): void
    {
        $atual = Cache::get(self::cacheKey($this->token), []);
        Cache::put(
            self::cacheKey($this->token),
            array_merge(is_array($atual) ? $atual : [], $dados, [
                'token' => $this->token,
                'user_id' => $this->userId,
                'atualizado_em' => now()->toIso8601String(),
            ]),
            now()->addHours(2)
        );
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
