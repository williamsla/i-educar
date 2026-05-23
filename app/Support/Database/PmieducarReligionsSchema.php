<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Ajustes defensivos em pmieducar.religions para views de exportação que
 * esperam colunas do modelo legado (cod_religiao / nm_religiao).
 */
final class PmieducarReligionsSchema
{
    public static function ensureForExporterPersonView(): void
    {
        $tableExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'pmieducar'
                AND table_name = 'religions'
            ) as exists
        ");

        if (!$tableExists->exists) {
            return;
        }

        $nmReligiaoExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'pmieducar'
                AND table_name = 'religions'
                AND column_name = 'nm_religiao'
            ) as exists
        ");

        if (!$nmReligiaoExists->exists) {
            DB::statement('ALTER TABLE pmieducar.religions ADD COLUMN nm_religiao VARCHAR(255)');
        }

        $codReligiaoExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'pmieducar'
                AND table_name = 'religions'
                AND column_name = 'cod_religiao'
            ) as exists
        ");

        if (!$codReligiaoExists->exists) {
            DB::statement('ALTER TABLE pmieducar.religions ADD COLUMN cod_religiao INTEGER');

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
                DB::statement('
                    UPDATE pmieducar.religions
                    SET cod_religiao = id
                    WHERE cod_religiao IS NULL
                ');
            }
        }
    }
}
