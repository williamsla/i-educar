<?php

namespace App\Services\Idiario;

use App\Models\IdiarioSyncState;
use App\Setting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IdiarioSync
{
    public const NOTICE = 'O i-Diário está sendo atualizado.';

    private const FAILURE = 'Não foi possível atualizar o i-Diário.';

    private static bool $requestedThisCycle = false;

    private static ?bool $tableReady = null;

    public static function currentPageNeedsSync(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        $scripts = [
            'educar_turma_cad.php',
            'educar_matricula_cad.php',
            'educar_matricula_turma_cad.php',
            'educar_matricula_turma_turno_cad.php',
            'educar_matricula_etapa_turma_cad.php',
            'educar_matricula_reclassificar_cad.php',
            'educar_matricula_abandono_cad.php',
            'educar_matricula_formando_cad.php',
            'educar_servidor_cad.php',
            'educar_servidor_vinculo_turma_cad.php',
            'educar_servidor_alocacao_cad.php',
            'educar_servidor_substituicao_cad.php',
            'educar_calendario_ano_letivo_cad.php',
            'educar_calendario_dia_cad.php',
            'educar_escola_ano_letivo_cad.php',
            'educar_componentes_serie_cad.php',
            'educar_escola_serie_cad.php',
            'educar_serie_cad.php',
            'educar_escola_cad.php',
        ];

        if (in_array($script, $scripts, true)) {
            return true;
        }

        return str_contains($uri, '/module/Cadastro/aluno')
            || str_contains($uri, '/module/ComponenteCurricular/edit');
    }

    public static function request(): bool
    {
        if (self::$requestedThisCycle) {
            return true;
        }

        self::ensureConfig();

        if (!IdiarioSimpleSyncClient::configured() || !self::tableReady()) {
            return false;
        }

        try {
            $state = IdiarioSyncState::query()->first() ?? new IdiarioSyncState();
            $state->requested_at = now();
            $state->status = IdiarioSyncState::QUEUED;
            $state->done_percentage = null;
            $state->last_error = null;
            $state->dismissed_at = null;
            $state->finished_at = null;
            $state->diary_started_at = null;
            $state->sync_again = false;
            $state->save();
        } catch (QueryException $exception) {
            self::$tableReady = false;
            report($exception);

            return false;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        self::$requestedThisCycle = true;

        try {
            if (!app()->runningInConsole()) {
                session()->flash('idiario', self::NOTICE);
            }
        } catch (Throwable) {
        }

        $requestedAt = $state->requested_at->toIso8601String();
        $pending = \App\Jobs\RequestIdiarioSimpleSyncJob::dispatch($requestedAt);

        if (config('queue.default') === 'sync') {
            $pending->afterResponse();
        } else {
            $pending->delay(now()->addSeconds(20));
        }

        return true;
    }

    public static function send(string $requestedAt): void
    {
        self::ensureConfig();

        if (!IdiarioSimpleSyncClient::configured()) {
            return;
        }

        $state = self::state();

        if ($state === null || $state->requested_at === null) {
            return;
        }

        $jobAt = Carbon::parse($requestedAt);

        if ($state->requested_at->greaterThan($jobAt->copy()->addSecond())) {
            return;
        }

        try {
            $payload = (new IdiarioSimpleSyncClient())->start();
            self::applyRemote($state, $payload, true);
        } catch (IdiarioSimpleSyncException $exception) {
            Log::warning('Falha ao pedir sincronização simples ao i-Diário.', [
                'message' => $exception->getMessage(),
            ]);
            $state->status = IdiarioSyncState::FAILED;
            $state->last_error = self::FAILURE;
            $state->finished_at = now();
            $state->save();
        }
    }

    /**
     * @return array{visible:bool,status:?string,message:?string,percentage:?int,icon:?string,requested_at:?string}
     */
    public static function snapshot(bool $refresh = false): array
    {
        try {
            $state = self::state();

            if ($state === null) {
                return self::hidden();
            }

            if ($refresh) {
                self::refresh($state);
                $state->refresh();
            }

            return self::present($state);
        } catch (Throwable $exception) {
            report($exception);

            return self::hidden();
        }
    }

    public static function dismiss(): void
    {
        $state = self::state();

        if ($state === null || $state->status !== IdiarioSyncState::FAILED) {
            return;
        }

        $state->status = IdiarioSyncState::IDLE;
        $state->dismissed_at = now();
        $state->last_error = null;
        $state->save();
    }

    private static function refresh(IdiarioSyncState $state): void
    {
        if (!in_array($state->status, [IdiarioSyncState::QUEUED, IdiarioSyncState::SYNCING], true) && !$state->sync_again) {
            self::expireCompleted($state);

            return;
        }

        if ($state->status === IdiarioSyncState::QUEUED && $state->requested_at !== null && $state->requested_at->greaterThan(now()->subSeconds(2))) {
            return;
        }

        if ($state->last_checked_at !== null && $state->last_checked_at->greaterThan(now()->subSeconds(5))) {
            return;
        }

        self::ensureConfig();

        if (!IdiarioSimpleSyncClient::configured()) {
            return;
        }

        try {
            $payload = (new IdiarioSimpleSyncClient())->current();
        } catch (IdiarioSimpleSyncException $exception) {
            Log::warning('Falha ao consultar a sincronização simples do i-Diário.', [
                'message' => $exception->getMessage(),
            ]);
            $state->last_checked_at = now();
            $state->save();

            return;
        }

        $state->last_checked_at = now();

        if ($state->status === IdiarioSyncState::QUEUED && !in_array($payload['status'], ['started', 'enqueued'], true)) {
            $state->save();

            return;
        }

        self::applyRemote($state, $payload, false);
    }

    /**
     * @param  array{id:int|null,status:string|null,done_percentage:int|null,time_running:int|null,started_at:string|null,full_synchronization:bool}  $payload
     */
    private static function applyRemote(IdiarioSyncState $state, array $payload, bool $fromStart): void
    {
        $remoteStatus = $payload['status'];
        $diaryStartedAt = $payload['started_at'] ? Carbon::parse($payload['started_at']) : $state->diary_started_at;

        if ($remoteStatus === 'started' || $remoteStatus === 'enqueued') {
            $state->status = IdiarioSyncState::SYNCING;
            $state->done_percentage = $payload['done_percentage'];
            $state->diary_started_at = $diaryStartedAt;
            $state->last_error = null;
            $state->finished_at = null;
            $state->last_checked_at = now();

            if ($fromStart && $diaryStartedAt !== null && $state->requested_at !== null && $state->requested_at->greaterThan($diaryStartedAt->copy()->addSeconds(2))) {
                $state->sync_again = true;
            }

            $state->save();

            return;
        }

        if ($remoteStatus === 'error') {
            $state->status = IdiarioSyncState::FAILED;
            $state->last_error = self::FAILURE;
            $state->finished_at = now();
            $state->sync_again = false;
            $state->last_checked_at = now();
            $state->save();

            return;
        }

        if ($remoteStatus === 'completed') {
            if ($state->sync_again) {
                $state->sync_again = false;
                $state->status = IdiarioSyncState::QUEUED;
                $state->requested_at = now();
                $state->last_checked_at = now();
                $state->save();
                self::send($state->requested_at->toIso8601String());

                return;
            }

            $state->status = IdiarioSyncState::COMPLETED;
            $state->done_percentage = 100;
            $state->last_error = null;
            $state->finished_at = $state->finished_at ?? now();
            $state->last_checked_at = now();
            $state->save();

            return;
        }

        if ($fromStart) {
            $state->status = IdiarioSyncState::FAILED;
            $state->last_error = self::FAILURE;
            $state->finished_at = now();
            $state->last_checked_at = now();
            $state->save();
        }
    }

    private static function expireCompleted(IdiarioSyncState $state): void
    {
        if ($state->status !== IdiarioSyncState::COMPLETED || $state->finished_at === null) {
            return;
        }

        if ($state->finished_at->greaterThan(now()->subSeconds(15))) {
            return;
        }

        $state->status = IdiarioSyncState::IDLE;
        $state->save();
    }

    /**
     * @return array{visible:bool,status:?string,message:?string,percentage:?int,icon:?string,requested_at:?string}
     */
    private static function present(IdiarioSyncState $state): array
    {
        self::expireCompleted($state);

        if ($state->status === IdiarioSyncState::IDLE) {
            return self::hidden();
        }

        $percentage = $state->done_percentage;

        return match ($state->status) {
            IdiarioSyncState::QUEUED => [
                'visible' => true,
                'status' => IdiarioSyncState::QUEUED,
                'message' => 'Alteração registrada. O i-Diário será atualizado em instantes.',
                'percentage' => null,
                'icon' => 'fa-clock-o',
                'requested_at' => $state->requested_at?->toIso8601String(),
            ],
            IdiarioSyncState::SYNCING => [
                'visible' => true,
                'status' => IdiarioSyncState::SYNCING,
                'message' => 'Atualizando o i-Diário…',
                'percentage' => $percentage,
                'icon' => 'fa-refresh',
                'requested_at' => $state->requested_at?->toIso8601String(),
            ],
            IdiarioSyncState::COMPLETED => [
                'visible' => true,
                'status' => IdiarioSyncState::COMPLETED,
                'message' => 'i-Diário atualizado.',
                'percentage' => 100,
                'icon' => 'fa-check',
                'requested_at' => $state->requested_at?->toIso8601String(),
            ],
            IdiarioSyncState::FAILED => [
                'visible' => true,
                'status' => IdiarioSyncState::FAILED,
                'message' => $state->last_error ?: self::FAILURE,
                'percentage' => null,
                'icon' => 'fa-exclamation-triangle',
                'requested_at' => $state->requested_at?->toIso8601String(),
            ],
            default => self::hidden(),
        };
    }

    /**
     * @return array{visible:bool,status:?string,message:?string,percentage:?int,icon:?string,requested_at:?string}
     */
    private static function hidden(): array
    {
        return [
            'visible' => false,
            'status' => null,
            'message' => null,
            'percentage' => null,
            'icon' => null,
            'requested_at' => null,
        ];
    }

    private static function state(): ?IdiarioSyncState
    {
        if (!self::tableReady()) {
            return null;
        }

        return IdiarioSyncState::query()->orderBy('id')->first();
    }

    private static function tableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            self::$tableReady = Schema::hasTable('idiario_sync_states');
        } catch (Throwable) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }

    private static function ensureConfig(): void
    {
        $config = config('legacy.config');
        $url = '';

        if (is_array($config)) {
            $url = trim((string) ($config['url_diario_professor'] ?? ''));
        } elseif (is_object($config)) {
            $url = trim((string) ($config->url_diario_professor ?? ''));
        }

        $token = trim((string) config('legacy.apis.access_key'));

        if ($url !== '' && $token !== '') {
            return;
        }

        try {
            if ($token === '') {
                $stored = Setting::query()->where('key', 'legacy.apis.access_key')->value('value');

                if (is_string($stored) && trim($stored) !== '') {
                    config()->set('legacy.apis.access_key', trim($stored));
                }
            }

            if ($url === '') {
                $storedUrl = DB::table('pmieducar.configuracoes_gerais as cg')
                    ->join('pmieducar.instituicao as i', 'cod_instituicao', '=', 'ref_cod_instituicao')
                    ->where('i.ativo', 1)
                    ->value('cg.url_diario_professor');

                if (is_string($storedUrl) && trim($storedUrl) !== '') {
                    $current = is_array($config) ? $config : [];
                    $current['url_diario_professor'] = trim($storedUrl);
                    config()->set('legacy.config', $current);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
