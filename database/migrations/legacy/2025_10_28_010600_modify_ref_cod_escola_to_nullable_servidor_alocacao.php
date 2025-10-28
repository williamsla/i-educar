<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeRefCodEscolaToNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Só altera se a coluna ainda for NOT NULL
        $check = DB::selectOne("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'pmieducar'
              AND table_name = 'servidor_alocacao'
              AND column_name = 'ref_cod_escola'
        ");

        if ($check && $check->is_nullable === 'NO') {
            DB::unprepared("
                ALTER TABLE pmieducar.servidor_alocacao
                ALTER COLUMN ref_cod_escola DROP NOT NULL;
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Só volta a ser NOT NULL se atualmente estiver aceitando NULL
        $check = DB::selectOne("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'pmieducar'
              AND table_name = 'servidor_alocacao'
              AND column_name = 'ref_cod_escola'
        ");

        if ($check && $check->is_nullable === 'YES') {
            DB::unprepared("
                ALTER TABLE pmieducar.servidor_alocacao
                ALTER COLUMN ref_cod_escola SET NOT NULL;
            ");
        }
    }
}
