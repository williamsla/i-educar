<?php

use App\Services\UnificationService;
use Illuminate\Database\QueryException;
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
 * 6. Antes de excluir um duplicado, FKs RESTRICT/NO ACTION de tabelas filhas
 *    são reapontadas para o registro mantido, ou removidas se houver conflito
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

    /** @var array<string, array<int, array<string, mixed>>> */
    private $cacheFksRestritivas = [];

    /** @var array<string, array<int, array<int, string>>> */
    private $cacheIndicesUnicos = [];

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

            // Reaponta filhos do registro duplicado (ex.: ficha médica) antes do DELETE.
            $this->realocarDependenciasAntesDaExclusao(
                $value['tabela'],
                $value['coluna'],
                $chavesConsultarString
            );

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

    // ─────────────────────────────────────────────────────────────────────────
    // DEPENDÊNCIAS FORA DA LISTA (ON DELETE RESTRICT / NO ACTION)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Reaponta para o registro mantido os filhos das linhas que serão excluídas.
     * Se a chave única do filho já existir no registro mantido, o vínculo do
     * duplicado é removido. Cobre tabelas fora da lista, como a ficha médica.
     */
    private function realocarDependenciasAntesDaExclusao(string $tabela, string $coluna, string $chavesConsultarString): void
    {
        $colunaSql = $this->quoteIdent($coluna);
        $tabelaSql = $this->qualificarTabela($tabela);
        $codigo = (int) $this->codigoUnificador;

        $sobrevivente = DB::selectOne(
            "SELECT * FROM {$tabelaSql}
             WHERE {$colunaSql} IN ({$chavesConsultarString})
             ORDER BY {$colunaSql} = {$codigo} DESC
             LIMIT 1"
        );

        if ($sobrevivente === null) {
            return;
        }

        $excluidos = DB::select(
            "SELECT * FROM {$tabelaSql}
             WHERE {$colunaSql} <> ?
               AND {$colunaSql} IN ({$chavesConsultarString})",
            [$sobrevivente->{$coluna}]
        );

        if ($excluidos === []) {
            return;
        }

        $fks = $this->buscarFksRestritivas($tabela);

        foreach ($excluidos as $excluido) {
            foreach ($fks as $fk) {
                $valoresAntigos = [];
                $valoresNovos = [];
                $semReferencia = false;

                foreach ($fk['parent_columns'] as $colunaPai) {
                    $antigo = $excluido->{$colunaPai} ?? null;
                    $novo = $sobrevivente->{$colunaPai} ?? null;

                    if ($antigo === null) {
                        $semReferencia = true;
                        break;
                    }

                    $valoresAntigos[] = $antigo;
                    $valoresNovos[] = $novo;
                }

                if ($semReferencia || $this->valoresIguais($valoresAntigos, $valoresNovos)) {
                    continue;
                }

                $this->realocarFilhos($fk, $valoresAntigos, $valoresNovos, 0);
            }
        }
    }

    /**
     * @param  array{child_table: string, child_columns: array<int, string>, parent_columns: array<int, string>}  $fk
     * @param  array<int, mixed>  $valoresAntigos
     * @param  array<int, mixed>  $valoresNovos
     */
    private function realocarFilhos(array $fk, array $valoresAntigos, array $valoresNovos, int $profundidade): void
    {
        if ($profundidade > 12) {
            throw new CoreExt_Exception('Não foi possível reorganizar os vínculos dependentes durante a unificação.');
        }

        $this->removerLinhasQueConflitamNaUnicidade($fk, $valoresAntigos, $valoresNovos, $profundidade);

        $alias = 'alvo';
        [$whereSql, $whereBindings] = $this->whereColunas($alias, $fk['child_columns'], $valoresAntigos);

        $setSql = [];
        $setBindings = [];

        foreach ($fk['child_columns'] as $indice => $colunaFilha) {
            $setSql[] = $this->quoteIdent($colunaFilha) . ' = ?';
            $setBindings[] = $valoresNovos[$indice];
        }

        $tabela = $this->qualificarTabela($fk['child_table']);
        $sql = 'UPDATE ' . $tabela . ' AS ' . $alias . ' SET ' . implode(', ', $setSql) . ' WHERE ' . $whereSql;

        try {
            DB::transaction(function () use ($sql, $setBindings, $whereBindings) {
                DB::update($sql, array_merge($setBindings, $whereBindings));
            });
        } catch (QueryException $e) {
            $state = $this->sqlState($e);

            if (in_array($state, ['23505', '23502'], true)) {
                $this->excluirOnde($fk['child_table'], $alias, $whereSql, $whereBindings, $profundidade);

                return;
            }

            // CASCADE / SET NULL / SET DEFAULT ainda são resolvidos pelo DELETE do pai.
            if (in_array($fk['delete_rule'], ['c', 'n', 'd'], true)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Remove só as linhas do duplicado que, depois do reapontamento, bateriam
     * numa chave única já ocupada pelo registro mantido.
     *
     * @param  array{child_table: string, child_columns: array<int, string>, parent_columns: array<int, string>}  $fk
     * @param  array<int, mixed>  $valoresAntigos
     * @param  array<int, mixed>  $valoresNovos
     */
    private function removerLinhasQueConflitamNaUnicidade(array $fk, array $valoresAntigos, array $valoresNovos, int $profundidade): void
    {
        foreach ($this->buscarColunasDeIndicesUnicos($fk['child_table']) as $colunasUnicas) {
            if (array_intersect($colunasUnicas, $fk['child_columns']) === []) {
                continue;
            }

            $alias = 'antigo';
            $atual = 'atual';
            [$whereAntigo, $bindingsAntigo] = $this->whereColunas($alias, $fk['child_columns'], $valoresAntigos);

            $exists = ["{$atual}.ctid <> {$alias}.ctid"];
            $bindingsExists = [];

            foreach ($colunasUnicas as $colunaUnica) {
                $posicao = array_search($colunaUnica, $fk['child_columns'], true);
                $colunaSql = $this->quoteIdent($colunaUnica);

                if ($posicao === false) {
                    $exists[] = "{$atual}.{$colunaSql} = {$alias}.{$colunaSql}";
                    continue;
                }

                $exists[] = "{$atual}.{$colunaSql} = ?";
                $bindingsExists[] = $valoresNovos[$posicao];
            }

            $where = $whereAntigo . ' AND EXISTS (SELECT 1 FROM ' . $this->qualificarTabela($fk['child_table']) . ' AS ' . $atual . ' WHERE ' . implode(' AND ', $exists) . ')';
            $bindings = array_merge($bindingsAntigo, $bindingsExists);

            $conflito = DB::selectOne(
                'SELECT 1 AS existe FROM ' . $this->qualificarTabela($fk['child_table']) . ' AS ' . $alias . ' WHERE ' . $where . ' LIMIT 1',
                $bindings
            );

            if ($conflito) {
                $this->excluirOnde($fk['child_table'], $alias, $where, $bindings, $profundidade);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function excluirOnde(string $tabela, string $alias, string $whereSql, array $bindings, int $profundidade): void
    {
        if ($profundidade > 12) {
            throw new CoreExt_Exception('Não foi possível reorganizar os vínculos dependentes durante a unificação.');
        }

        foreach ($this->buscarFksRestritivas($tabela) as $fk) {
            $filho = 'dep' . ($profundidade + 1);
            $ligacao = $this->igualdadeFk($fk, $filho, $alias);
            $whereFilho = 'EXISTS (SELECT 1 FROM ' . $this->qualificarTabela($tabela) . ' AS ' . $alias . ' WHERE ' . $ligacao . ' AND (' . $whereSql . '))';

            if ($fk['child_table'] === $tabela) {
                for ($tentativa = 0; $tentativa < 12; $tentativa++) {
                    $apagados = DB::delete(
                        'DELETE FROM ' . $this->qualificarTabela($tabela) . ' AS ' . $filho . ' WHERE ' . $whereFilho,
                        $bindings
                    );

                    if ($apagados === 0) {
                        break;
                    }
                }

                continue;
            }

            if (!in_array($fk['delete_rule'], ['a', 'r'], true)) {
                continue;
            }

            $this->excluirOnde($fk['child_table'], $filho, $whereFilho, $bindings, $profundidade + 1);
        }

        DB::delete(
            'DELETE FROM ' . $this->qualificarTabela($tabela) . ' AS ' . $alias . ' WHERE ' . $whereSql,
            $bindings
        );
    }

    /**
     * @return array<int, array{child_table: string, child_columns: array<int, string>, parent_columns: array<int, string>, delete_rule: string}>
     */
    private function buscarFksRestritivas(string $tabela): array
    {
        if (isset($this->cacheFksRestritivas[$tabela])) {
            return $this->cacheFksRestritivas[$tabela];
        }

        [$schema, $table] = $this->parsearNomeTabela($tabela);

        $rows = DB::select(
            "SELECT
                nc.nspname AS child_schema,
                c.relname AS child_name,
                con.confdeltype AS delete_rule,
                (
                    SELECT string_agg(a.attname, ',' ORDER BY u.ord)
                    FROM unnest(con.conkey) WITH ORDINALITY AS u(attnum, ord)
                    JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = u.attnum
                ) AS child_columns,
                (
                    SELECT string_agg(a.attname, ',' ORDER BY u.ord)
                    FROM unnest(con.confkey) WITH ORDINALITY AS u(attnum, ord)
                    JOIN pg_attribute a ON a.attrelid = p.oid AND a.attnum = u.attnum
                ) AS parent_columns
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace nc ON nc.oid = c.relnamespace
             JOIN pg_class p ON p.oid = con.confrelid
             JOIN pg_namespace np ON np.oid = p.relnamespace
             WHERE con.contype = 'f'
               AND np.nspname = ?
               AND p.relname = ?",
            [$schema, $table]
        );

        $fks = [];

        foreach ($rows as $row) {
            if ($row->child_columns === null || $row->parent_columns === null) {
                continue;
            }

            $fks[] = [
                'child_table' => $row->child_schema . '.' . $row->child_name,
                'child_columns' => explode(',', $row->child_columns),
                'parent_columns' => explode(',', $row->parent_columns),
                'delete_rule' => $row->delete_rule,
            ];
        }

        return $this->cacheFksRestritivas[$tabela] = $fks;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function buscarColunasDeIndicesUnicos(string $tabela): array
    {
        if (isset($this->cacheIndicesUnicos[$tabela])) {
            return $this->cacheIndicesUnicos[$tabela];
        }

        [$schema, $table] = $this->parsearNomeTabela($tabela);

        $rows = DB::select(
            "SELECT i.indexrelid::text AS indice, a.attname
             FROM pg_index i
             JOIN pg_class t ON t.oid = i.indrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             JOIN LATERAL unnest(i.indkey::smallint[]) WITH ORDINALITY AS u(attnum, ord) ON u.attnum > 0
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = u.attnum
             WHERE i.indisunique
               AND i.indpred IS NULL
               AND n.nspname = ?
               AND t.relname = ?
               AND NOT EXISTS (
                   SELECT 1 FROM unnest(i.indkey::smallint[]) AS k(attnum) WHERE k.attnum = 0
               )
             ORDER BY i.indexrelid, u.ord",
            [$schema, $table]
        );

        $indices = [];

        foreach ($rows as $row) {
            $indices[$row->indice][] = $row->attname;
        }

        return $this->cacheIndicesUnicos[$tabela] = array_values($indices);
    }

    /**
     * @param  array{child_table: string, child_columns: array<int, string>, parent_columns: array<int, string>}  $fk
     */
    private function igualdadeFk(array $fk, string $filhoAlias, string $paiAlias): string
    {
        $partes = [];

        foreach ($fk['child_columns'] as $indice => $colunaFilha) {
            $partes[] = $filhoAlias . '.' . $this->quoteIdent($colunaFilha)
                . ' = ' . $paiAlias . '.' . $this->quoteIdent($fk['parent_columns'][$indice]);
        }

        return implode(' AND ', $partes);
    }

    /**
     * @param  array<int, string>  $colunas
     * @param  array<int, mixed>  $valores
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function whereColunas(string $alias, array $colunas, array $valores): array
    {
        $partes = [];
        $bindings = [];

        foreach ($colunas as $indice => $coluna) {
            $qualificada = $alias . '.' . $this->quoteIdent($coluna);

            if ($valores[$indice] === null) {
                $partes[] = $qualificada . ' IS NULL';
                continue;
            }

            $partes[] = $qualificada . ' = ?';
            $bindings[] = $valores[$indice];
        }

        return [implode(' AND ', $partes), $bindings];
    }

    /**
     * @param  array<int, mixed>  $antigos
     * @param  array<int, mixed>  $novos
     */
    private function valoresIguais(array $antigos, array $novos): bool
    {
        if (count($antigos) !== count($novos)) {
            return false;
        }

        foreach ($antigos as $indice => $valor) {
            if ((string) $valor !== (string) $novos[$indice]) {
                return false;
            }
        }

        return true;
    }

    private function qualificarTabela(string $tabela): string
    {
        [$schema, $table] = $this->parsearNomeTabela($tabela);

        return $this->quoteIdent($schema) . '.' . $this->quoteIdent($table);
    }

    private function quoteIdent(string $ident): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $ident)) {
            throw new CoreExt_Exception('Identificador SQL inesperado durante a unificação.');
        }

        return '"' . $ident . '"';
    }

    private function sqlState(QueryException $e): string
    {
        $state = (string) $e->getCode();

        if ($state !== '') {
            return $state;
        }

        return (string) ($e->errorInfo[0] ?? '');
    }
}