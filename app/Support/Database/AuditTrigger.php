<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait AuditTrigger
{
    /**
     * Return not audited tables.
     *
     * @return array
     */
    public function getSkippedTables()
    {
        return config('audit.skip', [
            'ieducar_audit',
            'auditoria',
            'auditoria_geral',
            'acesso',
            'deficiencia_excluidos',
            'area_conhecimento_excluidos',
            'componente_curricular_ano_escolar_excluidos',
            'componente_curricular_turma_excluidos',
            'professor_turma_excluidos',
            'regra_avaliacao_recuperacao_excluidos',
            'regra_avaliacao_serie_ano_excluidos',
            'aluno_excluidos',
            'disciplina_dependencia_excluidos',
            'dispensa_disciplina_excluidos',
            'escola_serie_disciplina_excluidos',
            'matricula_turma_excluidos',
            'migrations',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
            'reports_counts',
            'notifications',
            'notification_type',
            'jobs',
            'failed_jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'sessions',
            'password_resets',
            'personal_access_tokens',
            'historico_processamento_fila',
            'idiario_sync_states',
            'log_unifications',
            'log_unification_old_data',
        ]);
    }

    /**
     * Return audited tables.
     *
     * @return array
     */
    public function getAuditedTables()
    {
        $tables = DB::select('SELECT table_schema, table_name FROM information_schema.tables WHERE table_type = \'BASE TABLE\' AND table_schema IN (\'cadastro\', \'modules\', \'pmieducar\', \'portal\', \'public\', \'relatorio\');');

        $skipped = array_flip(array_map('strtolower', $this->getSkippedTables()));

        $return = [];
        foreach ($tables as $table) {
            $name = strtolower($table->table_name);
            $qualified = strtolower($table->table_schema . '.' . $table->table_name);

            // A lista antiga misturava nome puro (acesso) e schema.tabela
            // (portal.acesso). Comparar só o nome fazia o skip não pegar.
            if (isset($skipped[$name]) || isset($skipped[$qualified])) {
                continue;
            }

            $return[] = $table->table_schema . '.' . $table->table_name;
        }

        return $return;
    }

    /**
     * Return create audit trigger SQL to the table.
     *
     * @param string $table
     * @return string
     */
    public function getSqlForCreateAuditTrigger($table)
    {
        $trigger = Str::slug($table, '_') . '_audit';

        return <<<SQL
create trigger {$trigger}
after insert or update or delete on {$table}
for each row execute procedure public.audit();
SQL;
    }

    /**
     * Return drop audit trigger SQL to the table.
     *
     * @param string $table
     * @return string
     */
    public function getSqlForDropAuditTrigger($table)
    {
        $trigger = Str::slug($table, '_') . '_audit';

        return <<<SQL
drop trigger if exists {$trigger} on {$table};
SQL;
    }

    /**
     * Create audit trigger for table.
     *
     * @param string $table
     * @return void
     */
    public function createAuditTrigger($table)
    {
        DB::unprepared(
            $this->getSqlForCreateAuditTrigger($table)
        );
    }

    /**
     * Drop audit trigger from table.
     *
     * @param string $table
     * @return void
     */
    public function dropAuditTrigger($table)
    {
        DB::unprepared(
            $this->getSqlForDropAuditTrigger($table)
        );
    }

    /**
     * Create all audit triggers.
     *
     * @return void
     */
    public function createAuditTriggers()
    {
        foreach ($this->getAuditedTables() as $table) {
            $this->dropAuditTrigger($table);
            $this->createAuditTrigger($table);
        }
    }

    /**
     * Drop all audit triggers.
     *
     * @return void
     */
    public function dropAuditTriggers()
    {
        foreach ($this->getAuditedTables() as $table) {
            $this->dropAuditTrigger($table);
        }
    }
}
