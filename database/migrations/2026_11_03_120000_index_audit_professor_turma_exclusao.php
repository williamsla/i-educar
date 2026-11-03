<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY não pode rodar dentro de transação.
     *
     * @var bool
     */
    public $withinTransaction = false;

    public function up(): void
    {
        $this->createIndexIfNeeded(
            'public',
            'ieducar_audit_professor_turma_exclusao_idx',
            <<<'SQL'
                CREATE INDEX CONCURRENTLY ieducar_audit_professor_turma_exclusao_idx
                    ON public.ieducar_audit ((before->>'servidor_id'), (before->>'id'), "date" DESC)
                    WHERE schema = 'modules'
                      AND "table" = 'professor_turma'
                      AND after IS NULL
            SQL
        );

        $this->createIndexIfNeeded(
            'modules',
            'professor_turma_excluidos_servidor_idx',
            <<<'SQL'
                CREATE INDEX CONCURRENTLY professor_turma_excluidos_servidor_idx
                    ON modules.professor_turma_excluidos (servidor_id, instituicao_id)
            SQL
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS public.ieducar_audit_professor_turma_exclusao_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS modules.professor_turma_excluidos_servidor_idx');
    }

    /**
     * CREATE INDEX IF NOT EXISTS não enxerga índice inválido deixado por uma
     * criação interrompida e estoura pg_class_relname_nsp_index.
     */
    private function createIndexIfNeeded(string $schema, string $name, string $sql): void
    {
        $state = $this->indexState($schema, $name);

        if ($state === 'valid') {
            return;
        }

        if ($state === 'invalid') {
            DB::statement(sprintf('DROP INDEX CONCURRENTLY IF EXISTS %s.%s', $schema, $name));
        }

        try {
            DB::statement($sql);
        } catch (QueryException $exception) {
            if ($this->indexState($schema, $name) === 'valid') {
                return;
            }

            throw $exception;
        }
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
