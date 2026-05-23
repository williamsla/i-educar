<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pmieducar.matricula_turma', 'cod_curso_profissional')) {
            Schema::table('pmieducar.matricula_turma', function (Blueprint $table) {
                $table->integer('cod_curso_profissional')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pmieducar.matricula_turma', 'cod_curso_profissional')) {
            Schema::table('pmieducar.matricula_turma', function (Blueprint $table) {
                $table->dropColumn('cod_curso_profissional');
            });
        }
    }
};
