<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('pmieducar.escola', 'formas_contratacao_parceria_escola_secretaria_estadual')) {
            DB::unprepared('alter table pmieducar.escola rename column formas_contratacao_parceria_escola_secretaria to formas_contratacao_parceria_escola_secretaria_estadual;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('alter table pmieducar.escola rename column formas_contratacao_parceria_escola_secretaria_estadual to formas_contratacao_parceria_escola_secretaria;');
    }
};
