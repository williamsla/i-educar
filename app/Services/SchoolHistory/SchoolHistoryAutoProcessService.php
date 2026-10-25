<?php

namespace App\Services\SchoolHistory;

use App\User;
use App_Model_MatriculaSituacao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use iEducar\Modules\Educacenso\Model\ModalidadeCurso;
use ProcessamentoAutomaticoApiController;
use RuntimeException;
use Throwable;

/**
 * Processamento automático do histórico escolar.
 *
 * A fila (pmieducar.historico_processamento_fila) é alimentada por triggers no banco
 * sempre que a situação da matrícula muda — e também quando médias/faltas mudam em
 * matrículas que já possuem histórico. Este serviço consome a fila e processa ou
 * reprocessa o histórico usando a MESMA rotina da tela "Processamento de histórico
 * escolar", com "Usar do boletim" em situação, disciplinas, notas, faltas e frequência.
 */
class SchoolHistoryAutoProcessService
{
    public const TABLE = 'pmieducar.historico_processamento_fila';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PROCESSANDO = 'processando';

    /** Histórico criado ou reprocessado a partir do boletim */
    public const STATUS_PROCESSADO = 'processado';

    /** Só a situação do histórico existente foi atualizada (ex.: matrícula transferida) */
    public const STATUS_ATUALIZADO = 'atualizado';

    public const STATUS_IGNORADO = 'ignorado';

    public const STATUS_ERRO = 'erro';

    /** Chave do advisory lock (evita processar a mesma matrícula em paralelo) */
    private const LOCK_KEY = 7344121;

    /**
     * Situações em que o histórico é gerado/atualizado a partir do boletim.
     * Inclui Cursando (histórico parcial atualizado ao longo do ano) e Transferido
     * (o aluno sai com o histórico do que cursou até a transferência).
     */
    private const SITUACOES_PROCESSAVEIS = [
        App_Model_MatriculaSituacao::TRANSFERIDO,
        App_Model_MatriculaSituacao::APROVADO,
        App_Model_MatriculaSituacao::REPROVADO,
        App_Model_MatriculaSituacao::REPROVADO_POR_FALTAS,
        App_Model_MatriculaSituacao::EM_ANDAMENTO,
        App_Model_MatriculaSituacao::APROVADO_PELO_CONSELHO,
        App_Model_MatriculaSituacao::APROVADO_COM_DEPENDENCIA,
        App_Model_MatriculaSituacao::RECLASSIFICADO,
        App_Model_MatriculaSituacao::ABANDONO,
    ];

    /** @var array<string, bool> */
    private static array $queueAvailable = [];

    private static bool $running = false;

    public function isEnabled(): bool
    {
        return (bool) config('school_history.auto_process.enabled', true);
    }

    /**
     * A fila existe na conexão atual? (migration executada)
     */
    public function queueIsAvailable(): bool
    {
        $connection = DB::getDefaultConnection();

        if (!isset(self::$queueAvailable[$connection])) {
            try {
                $row = DB::selectOne("SELECT to_regclass('" . self::TABLE . "') IS NOT NULL AS existe");
                self::$queueAvailable[$connection] = (bool) ($row->existe ?? false);
            } catch (Throwable) {
                self::$queueAvailable[$connection] = false;
            }
        }

        return self::$queueAvailable[$connection];
    }

    public function hasPending(): bool
    {
        return DB::table(self::TABLE)->where('status', self::STATUS_PENDENTE)->exists();
    }

    /**
     * Coloca uma matrícula na fila manualmente (comando/backfill).
     */
    public function enqueue(int $matriculaId, string $origem = 'manual', ?int $userId = null): void
    {
        DB::statement(
            'INSERT INTO ' . self::TABLE . ' (ref_cod_matricula, origem, ref_usuario)
             VALUES (?, ?, ?)
             ON CONFLICT (ref_cod_matricula) WHERE status = \'pendente\' DO NOTHING',
            [$matriculaId, $origem, $userId]
        );
    }

    /**
     * Consome até $limit itens pendentes da fila.
     *
     * @return array<string, int> quantidade por status final
     */
    public function processPending(int $limit): array
    {
        $resumo = [];

        if (self::$running || $limit < 1) {
            return $resumo;
        }

        self::$running = true;

        try {
            $this->requeueStale();

            foreach ($this->claim($limit) as $item) {
                $status = $this->processQueueItem($item);
                $resumo[$status] = ($resumo[$status] ?? 0) + 1;
            }
        } finally {
            self::$running = false;
        }

        return $resumo;
    }

    /**
     * Processa ou reprocessa o histórico de uma matrícula conforme a situação atual dela.
     *
     * @return array{status: string, mensagem: string}
     */
    public function processRegistration(int $matriculaId, ?int $userId = null): array
    {
        $matricula = DB::selectOne(
            'SELECT m.cod_matricula, m.ref_cod_aluno, m.ano, m.aprovado, m.ativo, m.dependencia,
                    m.ref_ref_cod_serie, m.ref_cod_curso, m.ref_ref_cod_escola,
                    e.ref_cod_instituicao
               FROM pmieducar.matricula m
               JOIN pmieducar.escola e ON e.cod_escola = m.ref_ref_cod_escola
              WHERE m.cod_matricula = ?',
            [$matriculaId]
        );

        if (!$matricula) {
            return $this->result(self::STATUS_IGNORADO, 'Matrícula não encontrada.');
        }

        if ((int) $matricula->ativo !== 1) {
            return $this->result(self::STATUS_IGNORADO, 'Matrícula inativa.');
        }

        $situacao = (int) $matricula->aprovado;
        $historico = $this->findHistorico($matricula);
        $temEnturmacao = $this->hasProcessableEnrollment($matriculaId);

        $podeCriar = config('school_history.auto_process.create_new', true)
            && ($situacao !== App_Model_MatriculaSituacao::EM_ANDAMENTO
                || config('school_history.auto_process.create_while_in_progress', true));

        $deveProcessar = in_array($situacao, self::SITUACOES_PROCESSAVEIS, true)
            && $temEnturmacao
            && ($historico !== null || $podeCriar);

        if ($deveProcessar) {
            return $this->runLegacyProcessing($matricula, $historico, $userId);
        }

        if ($historico !== null) {
            // Situação não processável pelo boletim (ex.: Falecido) ou sem enturmação:
            // mantém as notas e apenas sincroniza a situação do histórico.
            return $this->syncHistoricoSituacao($historico, $situacao, $userId);
        }

        return $this->result(
            self::STATUS_IGNORADO,
            sprintf(
                'Sem histórico e situação "%s" não gera histórico automaticamente%s.',
                $this->situacaoDescricao($situacao),
                $temEnturmacao ? '' : ' (matrícula sem enturmação)'
            )
        );
    }

    /**
     * Remove itens finalizados antigos.
     */
    public function prune(): int
    {
        $dias = (int) config('school_history.auto_process.keep_days', 90);

        if ($dias < 1) {
            return 0;
        }

        return DB::table(self::TABLE)
            ->whereNotIn('status', [self::STATUS_PENDENTE, self::STATUS_PROCESSANDO])
            ->where('created_at', '<', now()->subDays($dias))
            ->delete();
    }

    /* ------------------------------------------------------------------ */
    /* Fila                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Reserva itens pendentes de forma segura para execuções concorrentes.
     *
     * @return array<int, object>
     */
    private function claim(int $limit): array
    {
        return DB::select(
            'UPDATE ' . self::TABLE . '
                SET status = ?, tentativas = tentativas + 1, iniciado_em = now()
              WHERE id IN (
                    SELECT id FROM ' . self::TABLE . '
                     WHERE status = ?
                     ORDER BY id
                     LIMIT ?
                       FOR UPDATE SKIP LOCKED
              )
          RETURNING id, ref_cod_matricula, ref_usuario, origem, tentativas',
            [self::STATUS_PROCESSANDO, self::STATUS_PENDENTE, $limit]
        );
    }

    private function processQueueItem(object $item): string
    {
        $matriculaId = (int) $item->ref_cod_matricula;
        $userId = $item->ref_usuario ? (int) $item->ref_usuario : null;

        if (!$this->acquireLock($matriculaId)) {
            // Outra execução está processando esta matrícula agora: tenta de novo depois
            $this->requeue($item, 'Matrícula em processamento por outra execução.', false);

            return self::STATUS_PENDENTE;
        }

        try {
            $resultado = $this->processRegistration($matriculaId, $userId);
            $this->finish($item, $resultado['status'], $resultado['mensagem']);

            if ($resultado['status'] === self::STATUS_ERRO) {
                Log::warning('Histórico automático: falha ao processar matrícula ' . $matriculaId, $resultado);
            }

            return $resultado['status'];
        } catch (Throwable $e) {
            Log::error('Histórico automático: exceção ao processar matrícula ' . $matriculaId, [
                'exception' => $e,
            ]);

            $this->safeRollBack();
            $this->requeue($item, $e->getMessage(), true);

            return self::STATUS_ERRO;
        } finally {
            $this->releaseLock($matriculaId);
        }
    }

    private function finish(object $item, string $status, string $mensagem): void
    {
        DB::table(self::TABLE)->where('id', $item->id)->update([
            'status' => $status,
            'mensagem' => mb_substr($mensagem, 0, 4000),
            'processado_em' => now(),
        ]);
    }

    /**
     * Finaliza o item atual como erro e, se ainda houver tentativas, cria um novo
     * item pendente para a matrícula (sem duplicar se já houver um pendente).
     */
    private function requeue(object $item, string $mensagem, bool $countAttempt): void
    {
        $maxTentativas = max(1, (int) config('school_history.auto_process.max_attempts', 3));
        $tentativas = (int) $item->tentativas - ($countAttempt ? 0 : 1);
        $reenfileirar = $tentativas < $maxTentativas;

        $this->finish(
            $item,
            $reenfileirar && !$countAttempt ? self::STATUS_IGNORADO : self::STATUS_ERRO,
            $mensagem . ($reenfileirar ? ' (reenfileirado)' : '')
        );

        if ($reenfileirar) {
            DB::statement(
                'INSERT INTO ' . self::TABLE . ' (ref_cod_matricula, origem, ref_usuario, tentativas)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (ref_cod_matricula) WHERE status = \'pendente\' DO NOTHING',
                [$item->ref_cod_matricula, $item->origem, $item->ref_usuario, $tentativas]
            );
        }
    }

    /**
     * Itens que ficaram "processando" (ex.: processo PHP morreu) voltam para a fila.
     */
    private function requeueStale(): void
    {
        $minutos = max(1, (int) config('school_history.auto_process.stale_minutes', 15));

        $travados = DB::select(
            'SELECT id, ref_cod_matricula, ref_usuario, origem, tentativas
               FROM ' . self::TABLE . '
              WHERE status = ? AND iniciado_em < now() - make_interval(mins => ?::int)',
            [self::STATUS_PROCESSANDO, $minutos]
        );

        foreach ($travados as $item) {
            $this->requeue($item, 'Processamento interrompido.', true);
        }
    }

    private function acquireLock(int $matriculaId): bool
    {
        $row = DB::selectOne('SELECT pg_try_advisory_lock(?, ?) AS ok', [self::LOCK_KEY, $matriculaId]);

        return (bool) ($row->ok ?? false);
    }

    private function releaseLock(int $matriculaId): void
    {
        try {
            DB::selectOne('SELECT pg_advisory_unlock(?, ?)', [self::LOCK_KEY, $matriculaId]);
        } catch (Throwable) {
            // conexão perdida: o lock é liberado junto com a sessão
        }
    }

    /* ------------------------------------------------------------------ */
    /* Processamento                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{status: string, mensagem: string}
     */
    private function runLegacyProcessing(object $matricula, ?object $historico, ?int $userId): array
    {
        $this->ensureAuthenticatedUser($userId, $matricula);

        $params = $this->buildParams($matricula, $historico);
        $queryParams = [
            'emitir_carga_disciplinas' => config('school_history.auto_process.defaults.sum_components_workload', true) ? 1 : null,
        ];

        $resultado = $this->runInLegacyEnvironment(function () use ($params, $queryParams) {
            require_once base_path('ieducar/modules/HistoricoEscolar/Views/ProcessamentoAutomaticoApiController.php');

            return (new ProcessamentoAutomaticoApiController)->processarHistorico($params, $queryParams);
        });

        $mensagens = collect($resultado['mensagens'])
            ->pluck('msg')
            ->filter()
            ->map(fn ($msg) => strip_tags((string) $msg))
            ->unique()
            ->implode(' | ');

        return $this->result(
            $resultado['sucesso'] ? self::STATUS_PROCESSADO : self::STATUS_ERRO,
            $mensagens ?: ($resultado['sucesso'] ? 'Histórico processado.' : 'Falha sem mensagem.')
        );
    }

    /**
     * Mesmos parâmetros enviados pela tela de processamento, usando o boletim
     * como fonte e preservando os dados digitados no histórico existente.
     */
    private function buildParams(object $matricula, ?object $historico): array
    {
        $defaults = config('school_history.auto_process.defaults', []);

        $params = [
            'instituicao_id' => $historico->ref_cod_instituicao ?? $matricula->ref_cod_instituicao,
            'matricula_id' => $matricula->cod_matricula,
            'situacao' => 'buscar-matricula',
            'percentual_frequencia' => 'buscar-boletim',
            'disciplinas' => 'buscar-boletim',
            'notas' => 'buscar-boletim',
            'faltas' => 'buscar-boletim',
            'media_area_conhecimento' => !empty($defaults['media_area_conhecimento']) ? 1 : 0,
            'processar_media_geral' => !empty($defaults['processar_media_geral']) ? 1 : 0,
        ];

        if ($historico !== null) {
            return $params + [
                'dias_letivos' => is_numeric($historico->dias_letivos) ? (int) $historico->dias_letivos : $this->diasLetivos($matricula),
                'grade_curso_id' => $historico->historico_grade_curso_id ?: $this->gradeCurso($matricula),
                'extra_curricular' => (int) ($historico->extra_curricular ?? 0) === 1 ? 1 : 0,
                'observacao' => $historico->observacao,
                'registro' => $historico->registro,
                'livro' => $historico->livro,
                'folha' => $historico->folha,
                'dependencia' => $historico->dependencia ? 't' : 'f',
                'posicao' => $historico->posicao ?? $this->posicao($matricula),
            ];
        }

        return $params + [
            'dias_letivos' => $this->diasLetivos($matricula),
            'grade_curso_id' => $this->gradeCurso($matricula),
            'extra_curricular' => 0,
            'observacao' => $this->observacaoPadraoSerie($matricula),
            'registro' => null,
            'livro' => null,
            'folha' => null,
            'dependencia' => $matricula->dependencia ? 't' : 'f',
            'posicao' => $this->posicao($matricula),
        ];
    }

    /**
     * Executa o código legado nas mesmas condições da LegacyController
     * (diretório intranet, sem saída, sem converter avisos em exceções).
     */
    private function runInLegacyEnvironment(callable $callback): mixed
    {
        $cwd = getcwd();
        $errorReporting = error_reporting();
        $bufferLevel = ob_get_level();

        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_WARNING & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        ob_start();

        try {
            chdir(base_path('ieducar/intranet'));

            return $callback();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            error_reporting($errorReporting);

            if ($cwd !== false) {
                chdir($cwd);
            }
        }
    }

    /**
     * @return array{status: string, mensagem: string}
     */
    private function syncHistoricoSituacao(object $historico, int $situacao, ?int $userId): array
    {
        if ((int) $historico->aprovado === $situacao) {
            return $this->result(self::STATUS_IGNORADO, 'Histórico já está com a situação atual da matrícula.');
        }

        DB::table('pmieducar.historico_escolar')
            ->where('ref_cod_aluno', $historico->ref_cod_aluno)
            ->where('sequencial', $historico->sequencial)
            ->update([
                'aprovado' => $situacao,
                'ref_usuario_exc' => Auth::id() ?? $userId,
                'data_exclusao' => now(),
            ]);

        return $this->result(
            self::STATUS_ATUALIZADO,
            sprintf('Situação do histórico atualizada para "%s".', $this->situacaoDescricao($situacao))
        );
    }

    /* ------------------------------------------------------------------ */
    /* Dados auxiliares                                                    */
    /* ------------------------------------------------------------------ */

    private function findHistorico(object $matricula): ?object
    {
        return DB::selectOne(
            'SELECT * FROM pmieducar.historico_escolar
              WHERE ref_cod_aluno = ? AND ano = ? AND ref_cod_matricula = ? AND ativo = 1
              ORDER BY sequencial DESC
              LIMIT 1',
            [$matricula->ref_cod_aluno, $matricula->ano, $matricula->cod_matricula]
        );
    }

    /**
     * Enturmação ativa, ou encerrada por transferência/remanejamento/reclassificação/
     * abandono/falecimento (mesma regra usada pelo boletim em IedFinder::getMatricula).
     */
    private function hasProcessableEnrollment(int $matriculaId): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 AS ok
               FROM pmieducar.matricula_turma mt
              WHERE mt.ref_cod_matricula = ?
                AND (mt.ativo = 1 OR mt.transferido OR mt.remanejado OR mt.reclassificado
                     OR mt.abandono OR mt.falecido)
              LIMIT 1',
            [$matriculaId]
        );
    }

    /**
     * Soma dos dias letivos das etapas da turma (mesma regra do boletim),
     * depois dias letivos da série, depois o padrão configurado.
     */
    private function diasLetivos(object $matricula): int
    {
        $dias = DB::selectOne(
            'SELECT CASE WHEN c.padrao_ano_escolar = 1 THEN (
                        SELECT COALESCE(SUM(anm.dias_letivos), 0)
                          FROM pmieducar.ano_letivo_modulo anm
                         WHERE anm.ref_ref_cod_escola = t.ref_ref_cod_escola AND anm.ref_ano = t.ano
                    ) ELSE (
                        SELECT COALESCE(SUM(tm.dias_letivos), 0)
                          FROM pmieducar.turma_modulo tm
                         WHERE tm.ref_cod_turma = t.cod_turma
                    ) END AS dias
               FROM pmieducar.matricula_turma mt
               JOIN pmieducar.turma t ON t.cod_turma = mt.ref_cod_turma
               JOIN pmieducar.curso c ON c.cod_curso = t.ref_cod_curso
              WHERE mt.ref_cod_matricula = ?
              ORDER BY mt.ativo DESC, mt.sequencial DESC
              LIMIT 1',
            [$matricula->cod_matricula]
        );

        if ($dias && (int) $dias->dias > 0) {
            return (int) $dias->dias;
        }

        $diasSerie = DB::table('pmieducar.serie')
            ->where('cod_serie', $matricula->ref_ref_cod_serie)
            ->value('dias_letivos');

        if ((int) $diasSerie > 0) {
            return (int) $diasSerie;
        }

        return (int) config('school_history.auto_process.defaults.dias_letivos', 200);
    }

    /**
     * Usa a grade mais utilizada nos históricos da mesma série/curso da instituição;
     * senão EJA para cursos de modalidade EJA; senão o padrão configurado.
     */
    private function gradeCurso(object $matricula): int
    {
        $grade = DB::selectOne(
            'SELECT he.historico_grade_curso_id AS id
               FROM pmieducar.historico_escolar he
               JOIN pmieducar.serie s ON s.cod_serie = ?
               JOIN pmieducar.curso c ON c.cod_curso = ?
               JOIN pmieducar.historico_grade_curso hgc ON hgc.id = he.historico_grade_curso_id AND hgc.ativo = 1
              WHERE he.ativo = 1
                AND he.ref_cod_instituicao = ?
                AND he.nm_serie = s.nm_serie
                AND he.nm_curso = c.nm_curso
              GROUP BY he.historico_grade_curso_id
              ORDER BY COUNT(*) DESC
              LIMIT 1',
            [$matricula->ref_ref_cod_serie, $matricula->ref_cod_curso, $matricula->ref_cod_instituicao]
        );

        if ($grade && $grade->id) {
            return (int) $grade->id;
        }

        $modalidade = DB::table('pmieducar.curso')
            ->where('cod_curso', $matricula->ref_cod_curso)
            ->value('modalidade_curso');

        if ((int) $modalidade === ModalidadeCurso::EJA
            && DB::table('pmieducar.historico_grade_curso')->where('id', 3)->where('ativo', 1)->exists()) {
            return 3;
        }

        return (int) config('school_history.auto_process.defaults.grade_curso_id', 2);
    }

    private function observacaoPadraoSerie(object $matricula): string
    {
        return (string) DB::table('pmieducar.serie')
            ->where('cod_serie', $matricula->ref_ref_cod_serie)
            ->value('observacao_historico');
    }

    /**
     * Quando a instituição controla a posição dos históricos, usa a etapa da série
     * (ex.: 1º ano -> 1), que é o que a tela pede para informar.
     */
    private function posicao(object $matricula): ?int
    {
        $controla = DB::table('pmieducar.instituicao')
            ->where('cod_instituicao', $matricula->ref_cod_instituicao)
            ->value('controlar_posicao_historicos');

        if (!$controla) {
            return null;
        }

        $etapa = DB::table('pmieducar.serie')
            ->where('cod_serie', $matricula->ref_ref_cod_serie)
            ->value('etapa_curso');

        return is_numeric($etapa) ? (int) $etapa : null;
    }

    /**
     * O processamento legado grava Auth::id() como usuário do histórico.
     * Fora de uma requisição autenticada (comando agendado), autentica o usuário
     * que fez a alteração ou um usuário administrativo.
     */
    private function ensureAuthenticatedUser(?int $userId, object $matricula): void
    {
        if (Auth::check()) {
            return;
        }

        $candidatos = array_filter([
            $userId,
            config('school_history.auto_process.user_id'),
        ]);

        foreach ($candidatos as $candidato) {
            $user = User::query()->where('cod_usuario', (int) $candidato)->first();

            if ($user) {
                Auth::setUser($user);

                return;
            }
        }

        $adminId = DB::table('pmieducar.usuario as u')
            ->join('pmieducar.tipo_usuario as t', 't.cod_tipo_usuario', '=', 'u.ref_cod_tipo_usuario')
            ->where('u.ativo', 1)
            ->whereIn('t.nivel', [1, 2])
            ->orderBy('t.nivel')
            ->orderBy('u.cod_usuario')
            ->value('u.cod_usuario');

        $user = $adminId ? User::query()->where('cod_usuario', $adminId)->first() : null;

        if (!$user) {
            throw new RuntimeException(
                'Nenhum usuário disponível para registrar o processamento automático do histórico. '
                . 'Defina HISTORICO_AUTO_USUARIO_ID no .env.'
            );
        }

        Auth::setUser($user);
    }

    private function situacaoDescricao(int $situacao): string
    {
        try {
            return App_Model_MatriculaSituacao::getInstance()->getValues()[$situacao] ?? (string) $situacao;
        } catch (Throwable) {
            return (string) $situacao;
        }
    }

    private function safeRollBack(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } catch (Throwable) {
            // ignora
        }
    }

    /**
     * @return array{status: string, mensagem: string}
     */
    private function result(string $status, string $mensagem): array
    {
        return ['status' => $status, 'mensagem' => $mensagem];
    }
}
