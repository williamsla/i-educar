<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('pmieducar.instituicao', 'obrigar_cpf')) { 
            Schema::table('pmieducar.instituicao', function (Blueprint $table) {
                $table->boolean('obrigar_cpf')->default(true);
            });
        }
    }

    public function down()
    {
        Schema::table('pmieducar.instituicao', function (Blueprint $table) {
            $table->dropColumn('obrigar_cpf');
        });
    }
};
