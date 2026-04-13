<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove triggers antigas se existirem
        DB::unprepared("
            DROP TRIGGER IF EXISTS trigger_sincronizar_data_enturmacao ON pmieducar.matricula;
            DROP FUNCTION IF EXISTS sincronizar_data_enturmacao();
        ");
        
        // Cria a função que será executada pela trigger
        DB::unprepared("
            CREATE OR REPLACE FUNCTION sincronizar_data_enturmacao()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Atualiza a data de enturmação na tabela matricula_turma
                UPDATE pmieducar.matricula_turma
                SET data_enturmacao = NEW.data_matricula
                WHERE ref_cod_matricula = NEW.cod_matricula
                AND ativo = 1;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
        
        // Cria a trigger na tabela matricula
        DB::unprepared("
            CREATE TRIGGER trigger_sincronizar_data_enturmacao
            AFTER UPDATE OF data_matricula ON pmieducar.matricula
            FOR EACH ROW
            WHEN (OLD.data_matricula IS DISTINCT FROM NEW.data_matricula)
            EXECUTE FUNCTION sincronizar_data_enturmacao();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove a trigger
        DB::unprepared("
            DROP TRIGGER IF EXISTS trigger_sincronizar_data_enturmacao ON pmieducar.matricula;
        ");
        
        // Remove a função
        DB::unprepared("
            DROP FUNCTION IF EXISTS sincronizar_data_enturmacao();
        ");
    }
};