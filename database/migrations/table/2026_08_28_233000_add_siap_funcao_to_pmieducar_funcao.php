<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pmieducar.funcao', function (Blueprint $table) {
            $table->smallInteger('siap_funcao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pmieducar.funcao', function (Blueprint $table) {
            $table->dropColumn('siap_funcao');
        });
    }
};
