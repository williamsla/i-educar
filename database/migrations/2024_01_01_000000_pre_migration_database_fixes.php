<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | 1. Corrigir estrutura da tabela religions
        |--------------------------------------------------------------------------
        */

        $tableExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'pmieducar'
                AND table_name = 'religions'
            ) as exists
        ");

        if ($tableExists->exists) {

            $columnExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'pmieducar'
                    AND table_name = 'religions'
                    AND column_name = 'cod_religiao'
                ) as exists
            ");

            if (!$columnExists->exists) {

                DB::statement("
                    ALTER TABLE pmieducar.religions
                    ADD COLUMN cod_religiao INTEGER
                ");

                $idExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = 'pmieducar'
                        AND table_name = 'religions'
                        AND column_name = 'id'
                    ) as exists
                ");

                if ($idExists->exists) {

                    DB::statement("
                        UPDATE pmieducar.religions
                        SET cod_religiao = id
                        WHERE cod_religiao IS NULL
                    ");
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Corrigir sequences quebradas
        |--------------------------------------------------------------------------
        */

        DB::statement("
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT
                    c.relname AS table_name,
                    a.attname AS column_name,
                    s.relname AS sequence_name
                FROM pg_class s
                JOIN pg_depend d ON d.objid = s.oid
                JOIN pg_class c ON d.refobjid = c.oid
                JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = d.refobjsubid
                WHERE s.relkind = 'S'
            LOOP
                BEGIN
                    EXECUTE format(
                        'SELECT setval(''%s'', (SELECT COALESCE(MAX(%s),0) FROM %s) + 1);',
                        r.sequence_name,
                        r.column_name,
                        r.table_name
                    );
                EXCEPTION
                    WHEN undefined_table THEN
                        RAISE NOTICE 'Tabela % não existe, ignorando sequence %',
                        r.table_name, r.sequence_name;
                END;
            END LOOP;
        END $$;
        ");
    }

    public function down(): void
    {
        // não remover automaticamente para evitar perda de dados
    }
};