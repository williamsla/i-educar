<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // CREATE INDEX CONCURRENTLY em ieducar_audit entra em deadlock com
        // sessões que já estão gravando auditoria. A tentativa interrompida
        // deixa o índice inválido; só removemos esse resto.
        if ($this->indexState('public', 'ieducar_audit_professor_turma_exclusao_idx') === 'invalid') {
            DB::statement('DROP INDEX IF EXISTS public.ieducar_audit_professor_turma_exclusao_idx');
        }

        $this->createIndexIfNeeded(
            'modules',
            'professor_turma_excluidos_servidor_idx',
            <<<'SQL'
                CREATE INDEX professor_turma_excluidos_servidor_idx
                    ON modules.professor_turma_excluidos (servidor_id, instituicao_id)
            SQL
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS modules.professor_turma_excluidos_servidor_idx');
    }

    private function createIndexIfNeeded(string $schema, string $name, string $sql): void
    {
        $state = $this->indexState($schema, $name);

        if ($state === 'valid') {
            return;
        }

        if ($state === 'invalid') {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s.%s', $schema, $name));
        }

        DB::statement($sql);
    }

    private function indexState(string $schema, string $name): string
    {
        $row = DB::selectOne(
            "SELECT CASE WHEN i.indisvalid THEN 'valid' ELSE 'invalid' END AS state
               FROM pg_class c
               JOIN pg_namespace n ON n.oid = c.relnamespace
               JOIN pg_index i ON i.indexrelid = c.oid
              WHERE n.nspname = ?
                AND c.relname = ?
                AND c.relkind = 'i'",
            [$schema, $name]
        );

        return $row->state ?? 'missing';
    }
};
