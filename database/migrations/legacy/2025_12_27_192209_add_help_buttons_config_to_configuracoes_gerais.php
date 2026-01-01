<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pmieducar.configuracoes_gerais', function (Blueprint $table) {
            // Adicionar coluna URL do Diário do Professor
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_diario_professor')) {
                $table->string('url_diario_professor', 500)->nullable();
            }
            
            // Adicionar coluna URL do WhatsApp
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_whatsapp')) {
                $table->string('url_whatsapp', 500)->nullable();
            }
            
            // Adicionar coluna para mostrar/ocultar botões de ajuda
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'mostrar_botoes_ajuda_login')) {
                $table->boolean('mostrar_botoes_ajuda_login')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmieducar.configuracoes_gerais', function (Blueprint $table) {
            // Remover colunas se existirem
            if (Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_diario_professor')) {
                $table->dropColumn('url_diario_professor');
            }
            
            if (Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_whatsapp')) {
                $table->dropColumn('url_whatsapp');
            }
            
            if (Schema::hasColumn('pmieducar.configuracoes_gerais', 'mostrar_botoes_ajuda_login')) {
                $table->dropColumn('mostrar_botoes_ajuda_login');
            }
        });
    }
};
