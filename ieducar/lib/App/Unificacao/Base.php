<?php

use App\Services\UnificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App_Unificacao_Base — v3.0
 *
 * Correções aplicadas nesta versão:
 * 1. Restaurados os arrays $chavesManterPrimeiroVinculo / $chavesManterTodosVinculos
 * 2. processaChavesManterTodosVinculos() executado ANTES de processaChavesManterPrimeiroVinculo()
 * 3. processaChavesManterPrimeiroVinculo() ordenado topologicamente
 * 4. Tabelas raiz (cadastro.fisica e cadastro.pessoa) SEMPRE por último
 * 5. DISABLE TRIGGER USER (não ALL) — não exige superuser
 */
class App_Unificacao_Base
{
    // ── Arrays de configuração (usados pelas subclasses) ─────────────────────

    /** Tabelas onde mantemos apenas UM vínculo (DELETE duplicado + UPDATE chave) */
    protected $chavesManterPrimeiroVinculo = [];

    /** Tabelas onde mantemos TODOS os vínculos (apenas UPDATE referência) */
    protected $chavesManterTodosVinculos = [];

    /** Tabelas onde os duplicados são deletados diretamente */
    protected $chavesDeletarDuplicados = [];

    /** Triggers que devem ser reabilitadas antes das operações */
    protected $triggersNecessarias = [];

    // ── Parâmetros de execução ────────────────────────────────────────────────

    protected $codigoUnificador;
    protected $codigosDuplicados;
    protected $codPessoaLogada;
    protected $db;
    protected $unificationId;

    /** @var UnificationService */
    protected $unificationService;

    // ── Constantes arquiteturais ──────────────────────────────────────────────

    /**
     * Tabelas raiz que SEMPRE devem ser as últimas a ser processadas,
     * independentemente da posição no array.
     */
    private const TABELAS_RAIZ_PROTEGIDAS = [
        'cadastro.fisica',
        'cadastro.pessoa',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(
        $codigoUnificador,
        $codigosDuplicados,
        $codPessoaLogada,
        clsBanco $db,
        $unificationId
    ) {
        $this->codigoUnificador   = $codigoUnificador;
        $this->codigosDuplicados  = $codigosDuplicados;
        $this->codPessoaLogada    = $codPessoaLogada;
        $this->db                 = $db;
        $this->unificationId      = $unificationId;
        $this->unificationService = new UnificationService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PONTO DE ENTRADA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Executa a unificação completa.
     *
     * Fluxo garantido:
     *   1. Validar parâmetros
     *   2. Desabilitar triggers de usuário
     *   3. Habilitar triggers necessárias
     *   4. Deletar registros de sistemas auxiliares (chavesDeletarDuplicados)
     *   5. FASE 1 — UPDATE GLOBAL: atualizar TODOS os vínculos FK
     *   6. FASE 2 — DELETE+UPDATE ORDENADO TOPOLOGICAMENTE
     *   7. Reabilitar triggers
     */
    public function unifica(): void
    {
        try {
            $this->validaParametros();
            $this->desabilitaTodasTriggers();
            $this->habilitaTriggersNecessarias();

            $this->processaChavesDeletarDuplicados();

            // FASE 1 — ATUALIZA TODOS OS VÍNCULOS FK
            $this->processaChavesManterTodosVinculos();

            // FASE 2 — DELETE + UPDATE POR TABELA EM ORDEM TOPOLÓGICA
            $this->processaChavesManterPrimeiroVinculo();

            $this->habilitaTodasTriggers();
        } catch (CoreExt_Exception $e) {
            throw new CoreExt_Exception(
                'Não foi possível realizar este processo de unificação. ' .
                'Por favor, entre em contato com o suporte. ' . $e->getMessage()
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 0 — DELETAR DUPLICADOS DIRETOS
    // ─────────────────────────────────────────────────────────────────────────

    protected function processaChavesDeletarDuplicados(): void
    {
        $stringCodigosDuplicados = implode(',', $this->codigosDuplicados);

        foreach ($this->chavesDeletarDuplicados as $value) {
            $oldKeys = explode(',', $stringCodigosDuplicados);
            $this->storeLogOldDataByKeys($oldKeys, $value['tabela'], $value['coluna']);

            try {
                $this->db->Consulta(
                    "SELECT 1 FROM {$value['tabela']} WHERE {$value['coluna']} IN ({$stringCodigosDuplicados})"
                );

                if ($this->db->ProximoRegistro()) {
                    $this->db->Consulta(
                        "DELETE FROM {$value['tabela']}
                         WHERE {$value['coluna']} IN ({$stringCodigosDuplicados})"
                    );
                } else {
                    $this->db->Consulta(
                        "UPDATE {$value['tabela']}
                         SET {$value['coluna']} = {$this->codigoUnificador}
                         WHERE {$value['coluna']} IN ({$stringCodigosDuplicados})"
                    );
                }
            } catch (\Exception $e) {
                throw new \Exception(
                    'Erro ao deletar registros duplicados. ' .
                    'Por favor, entre em contato com suporte. ' . $e->getMessage()
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 1 — UPDATE GLOBAL (chavesManterTodosVinculos)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Atualiza TODAS as FK de chavesManterTodosVinculos, redirecionando duplicados
     * para o codigoUnificador. Nenhum DELETE aqui.
     */
    protected function processaChavesManterTodosVinculos(): void
    {
        $stringCodigosDuplicados = implode(',', $this->codigosDuplicados);

        foreach ($this->chavesManterTodosVinculos as $value) {
            $oldKeys = explode(',', $stringCodigosDuplicados);
            $this->storeLogOldDataByKeys($oldKeys, $value['tabela'], $value['coluna']);

            $addSql = $this->buildSqlExtraBeforeUnification($value['tabela']);

            if (Schema::hasTable($value['tabela'])) {
                $this->db->Consulta(
                    "UPDATE {$value['tabela']}
                     SET {$value['coluna']} = {$this->codigoUnificador}
                     {$addSql}
                     WHERE {$value['coluna']} IN ({$stringCodigosDuplicados})"
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 2 — DELETE + UPDATE POR TABELA EM ORDEM TOPOLÓGICA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Processa chavesManterPrimeiroVinculo em ordem topológica automaticamente
     * descoberta via information_schema do PostgreSQL.
     */
    protected function processaChavesManterPrimeiroVinculo(): void
    {
        $chavesConsultar       = array_merge($this->codigosDuplicados, [$this->codigoUnificador]);
        $chavesConsultarString = implode(',', $chavesConsultar);

        // Separar tabelas raiz protegidas das demais
        [$tabelasProtegidas, $tabelasNormais] = $this->separarTabelasRaiz($this->chavesManterPrimeiroVinculo);

        // Ordenar tabelas normais pelo grafo de FK do PostgreSQL
        $tabelasOrdenadas = $this->ordenarPorDependenciaFKAutodescoberta($tabelasNormais);

        // Processar em ordem: tabelas normais (folhas primeiro) → tabelas raiz (por último)
        $todasEmOrdem = array_merge($tabelasOrdenadas, $tabelasProtegidas);

        foreach ($todasEmOrdem as $value) {
            $oldKeys = explode(',', $chavesConsultarString);
            $this->storeLogOldDataByKeys($oldKeys, $value['tabela'], $value['coluna']);

            if (!Schema::hasTable($value['tabela'])) {
                continue;
            }

            // DELETE: remove os registros duplicados, mantendo o principal
            $this->db->Consulta(
                "DELETE FROM {$value['tabela']}
                 WHERE {$value['coluna']} <> (
                     SELECT {$value['coluna']}
                     FROM {$value['tabela']}
                     WHERE {$value['coluna']} IN ({$chavesConsultarString})
                     ORDER BY {$value['coluna']} = {$this->codigoUnificador} DESC
                     LIMIT 1
                 )
                 AND {$value['coluna']} IN ({$chavesConsultarString})"
            );

            // UPDATE: renomeia o registro sobrevivente para codigoUnificador
            $this->db->Consulta(
                "UPDATE {$value['tabela']}
                 SET {$value['coluna']} = {$this->codigoUnificador}
                 WHERE {$value['coluna']} IN ({$chavesConsultarString})"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ORDENAÇÃO TOPOLÓGICA — AUTO-DISCOVERY VIA information_schema
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Separa as tabelas em [protegidas (raiz), normais].
     */
    private function separarTabelasRaiz(array $tabelas): array
    {
        $protegidas = [];
        $normais    = [];

        foreach ($tabelas as $value) {
            if (in_array($value['tabela'], self::TABELAS_RAIZ_PROTEGIDAS, true)) {
                $protegidas[] = $value;
            } else {
                $normais[] = $value;
            }
        }

        return [$protegidas, $normais];
    }

    /**
     * Ordena as tabelas de acordo com o grafo de dependências FK do PostgreSQL.
     */
    protected function ordenarPorDependenciaFKAutodescoberta(array $tabelas): array
    {
        if (count($tabelas) <= 1) {
            return $tabelas;
        }

        $nomesDasTabelas = array_column($tabelas, 'tabela');
        $dependencias    = $this->descobrirDependenciasFKViaPg($nomesDasTabelas);

        return $this->ordenacaoTopologicaKahn($tabelas, $dependencias);
    }

    /**
     * Consulta o PostgreSQL via information_schema para descobrir as dependências FK.
     */
    protected function descobrirDependenciasFKViaPg(array $tabelas): array
    {
        $dependencias = array_fill_keys($tabelas, []);

        if (count($tabelas) < 2) {
            return $dependencias;
        }

        $condicoes = implode(',', array_map(function ($tabela) {
            [$schema, $table] = $this->parsearNomeTabela($tabela);
            return "('{$schema}', '{$table}')";
        }, $tabelas));

        $sql = "
            SELECT
                tc.table_schema  || '.' || tc.table_name  AS tabela_filha,
                ccu.table_schema || '.' || ccu.table_name AS tabela_pai
            FROM information_schema.table_constraints       AS tc
            JOIN information_schema.key_column_usage        AS kcu
                ON  tc.constraint_name = kcu.constraint_name
                AND tc.table_schema    = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
                ON  ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND  (tc.table_schema,  tc.table_name)  IN ({$condicoes})
            AND  (ccu.table_schema, ccu.table_name) IN ({$condicoes})
        ";

        try {
            $rows = DB::select($sql);
            foreach ($rows as $row) {
                if (
                    isset($dependencias[$row->tabela_filha])
                    && $row->tabela_filha !== $row->tabela_pai
                    && !in_array($row->tabela_pai, $dependencias[$row->tabela_filha], true)
                ) {
                    $dependencias[$row->tabela_filha][] = $row->tabela_pai;
                }
            }
        } catch (\Exception $e) {
            // Se falhar, retorna dependências vazias
        }

        return $dependencias;
    }

    /**
     * Algoritmo de Kahn para ordenação topológica.
     */
    protected function ordenacaoTopologicaKahn(array $tabelas, array $dependencias): array
    {
        $mapa = [];
        foreach ($tabelas as $item) {
            $mapa[$item['tabela']] = $item;
        }

        $inDegree = array_fill_keys(array_keys($mapa), 0);

        foreach ($dependencias as $filho => $pais) {
            foreach ($pais as $pai) {
                if (isset($inDegree[$pai])) {
                    $inDegree[$pai]++;
                }
            }
        }

        $fila = array_keys(array_filter($inDegree, fn($g) => $g === 0));
        sort($fila);

        $resultado = [];

        while (!empty($fila)) {
            $atual = array_shift($fila);

            if (isset($mapa[$atual])) {
                $resultado[] = $mapa[$atual];
            }

            foreach ($dependencias[$atual] ?? [] as $pai) {
                if (!isset($inDegree[$pai])) {
                    continue;
                }
                $inDegree[$pai]--;
                if ($inDegree[$pai] === 0) {
                    $fila[] = $pai;
                    sort($fila);
                }
            }
        }

        $jaInseridos  = array_column($resultado, 'tabela');
        $naoOrdenados = array_diff(array_keys($mapa), $jaInseridos);

        foreach ($naoOrdenados as $tabela) {
            $resultado[] = $mapa[$tabela];
        }

        return $resultado;
    }

    /**
     * Converte 'schema.tabela' em ['schema', 'tabela'].
     */
    private function parsearNomeTabela(string $nomeCompleto): array
    {
        $partes = explode('.', $nomeCompleto, 2);
        return count($partes) === 2 ? [$partes[0], $partes[1]] : ['public', $partes[0]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRIGGERS
    // ─────────────────────────────────────────────────────────────────────────

    protected function tabelasEnvolvidas(): array
    {
        $todasChaves  = array_merge($this->chavesManterPrimeiroVinculo, $this->chavesManterTodosVinculos);
        $todasTabelas = [];

        foreach ($todasChaves as $value) {
            $todasTabelas[$value['tabela']] = $value['tabela'];
        }

        return array_values($todasTabelas);
    }

    protected function habilitaTriggersNecessarias(): void
    {
        foreach ($this->triggersNecessarias as $triggerNecessaria) {
            $this->db->Consulta(
                "ALTER TABLE {$triggerNecessaria['tabela']} ENABLE TRIGGER {$triggerNecessaria['trigger']}"
            );
        }
    }

    protected function desabilitaTodasTriggers(): void
    {
        foreach ($this->tabelasEnvolvidas() as $tabela) {
            $this->db->Consulta("ALTER TABLE IF EXISTS {$tabela} DISABLE TRIGGER USER");
        }
    }

    protected function habilitaTodasTriggers(): void
    {
        foreach ($this->tabelasEnvolvidas() as $tabela) {
            if (Schema::hasTable($tabela)) {
                $this->db->Consulta("ALTER TABLE {$tabela} ENABLE TRIGGER USER");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALIDAÇÃO
    // ─────────────────────────────────────────────────────────────────────────

    protected function validaParametros(): void
    {
        if ($this->codigoUnificador != (int) $this->codigoUnificador) {
            throw new CoreExt_Exception('Parâmetro 1 deve ser o código unificador');
        }

        if (!is_array($this->codigosDuplicados) || !count($this->codigosDuplicados)) {
            throw new CoreExt_Exception('Parâmetro 2 deve ser um array de códigos duplicados');
        }

        if ($this->codPessoaLogada != (int) $this->codPessoaLogada) {
            throw new CoreExt_Exception('Parâmetro 3 deve ser um inteiro');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOG E UTILITÁRIOS
    // ─────────────────────────────────────────────────────────────────────────

    private function storeLogOldDataByKeys(array $oldKeys, string $table, string $columnKey): void
    {
        foreach ($oldKeys as $key) {
            $data = $this->getOldData($table, $columnKey, $key);

            if ($data->isEmpty()) {
                continue;
            }

            $this->unificationService->storeLogOldData(
                $this->unificationId,
                $table,
                [$columnKey => $key],
                $data->toArray()
            );
        }
    }

    private function getOldData(string $table, string $key, $value): \Illuminate\Support\Collection
    {
        if (Schema::hasTable($table)) {
            return DB::table($table)->whereIn($key, [$value])->get();
        }

        return collect();
    }

    private function buildSqlExtraBeforeUnification(string $tableName): string
    {
        $addSql = '';

        if ($tableName === 'pmieducar.servidor_afastamento') {
            $addSql .= ', sequencial = (
                SELECT COALESCE(MAX(sequencial) + 1, 1)
                FROM pmieducar.servidor_afastamento
                WHERE ref_cod_servidor = ' . $this->codigoUnificador . '
            )';
        }

        return $addSql;
    }
}