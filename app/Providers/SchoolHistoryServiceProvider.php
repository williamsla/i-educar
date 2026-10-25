<?php

namespace App\Providers;

use App\Services\SchoolHistory\SchoolHistoryAutoProcessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Consome a fila de processamento automático do histórico ao final das requisições.
 *
 * Roda em "terminating", ou seja, depois que a resposta já foi enviada (PHP-FPM),
 * então não atrasa a tela do professor nem a sincronização do i-Diário.
 *
 * Vale para QUALQUER requisição, inclusive as da API usadas pelo i-Diário
 * (autenticadas por access_key, sem usuário logado). Assim, a própria requisição
 * que altera nota/falta/situação já deixa o histórico atualizado — sem cron e
 * sem processamento manual.
 */
class SchoolHistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchoolHistoryAutoProcessService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app->terminating(function () {
            $tinhaUsuario = false;

            try {
                if (!config('school_history.auto_process.enabled', true)
                    || !config('school_history.auto_process.on_request_terminate', true)) {
                    return;
                }

                $service = $this->app->make(SchoolHistoryAutoProcessService::class);

                if (!$service->queueIsAvailable() || !$service->hasPending()) {
                    return;
                }

                // A resposta já foi enviada: não interromper se o cliente desconectar
                ignore_user_abort(true);

                $tinhaUsuario = Auth::check();
                $lote = max(1, (int) config('school_history.auto_process.request_batch_size', 5));
                $limiteSegundos = max(1, (int) config('school_history.auto_process.request_time_budget', 25));
                $prazo = microtime(true) + $limiteSegundos;

                @set_time_limit($limiteSegundos + 30);

                do {
                    $resumo = $service->processPending($lote);
                    $avancou = array_sum($resumo) - ($resumo[SchoolHistoryAutoProcessService::STATUS_PENDENTE] ?? 0) > 0;
                } while ($avancou && microtime(true) < $prazo && $service->hasPending());
            } catch (Throwable $e) {
                Log::error('Histórico automático: erro ao consumir a fila no fim da requisição', [
                    'exception' => $e,
                ]);
            } finally {
                // Se o serviço autenticou um usuário só para gravar o histórico, desfaz
                if (!$tinhaUsuario && Auth::check()) {
                    try {
                        Auth::forgetUser();
                    } catch (Throwable) {
                        // ignora
                    }
                }
            }
        });
    }
}
