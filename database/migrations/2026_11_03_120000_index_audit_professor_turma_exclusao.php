<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS ieducar_audit_professor_turma_exclusao_idx
                ON public.ieducar_audit ((before->>'servidor_id'), (before->>'id'), "date" DESC)
                WHERE schema = 'modules'
                  AND "table" = 'professor_turma'
                  AND after IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS professor_turma_excluidos_servidor_idx
                ON modules.professor_turma_excluidos (servidor_id, instituicao_id)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS public.ieducar_audit_professor_turma_exclusao_idx');
        DB::statement('DROP INDEX IF EXISTS modules.professor_turma_excluidos_servidor_idx');
    }
};
