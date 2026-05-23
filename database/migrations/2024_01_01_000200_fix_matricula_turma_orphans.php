<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | Remover matriculas_turma órfãs
        |--------------------------------------------------------------------------
        */

        DB::statement("
            DELETE FROM pmieducar.matricula_turma mt
            WHERE NOT EXISTS (
                SELECT 1
                FROM pmieducar.matricula m
                WHERE m.cod_matricula = mt.ref_cod_matricula
            );
        ");

        /*
        |--------------------------------------------------------------------------
        | Remover duplicidades de aluno na mesma turma
        |--------------------------------------------------------------------------
        */

        DB::statement("
            DELETE FROM pmieducar.matricula_turma a
            USING pmieducar.matricula_turma b
            WHERE a.ctid < b.ctid
            AND a.ref_cod_matricula = b.ref_cod_matricula
            AND a.ref_cod_turma = b.ref_cod_turma;
        ");
    }

    public function down(): void
    {
        //
    }
};