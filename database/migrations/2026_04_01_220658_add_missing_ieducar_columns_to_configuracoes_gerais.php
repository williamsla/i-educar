<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pmieducar.configuracoes_gerais', function (Blueprint $table) {
            // Verificar se a coluna não existe antes de adicionar
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_background_image')) {
                $table->text('ieducar_background_image')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_background_image_url')) {
                $table->text('ieducar_background_image_url')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_entity_name')) {
                $table->string('ieducar_entity_name')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_login_footer')) {
                $table->text('ieducar_login_footer')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_external_footer')) {
                $table->text('ieducar_external_footer')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_internal_footer')) {
                $table->text('ieducar_internal_footer')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'ieducar_suspension_message')) {
                $table->text('ieducar_suspension_message')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'facebook_url')) {
                $table->string('facebook_url')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'twitter_url')) {
                $table->string('twitter_url')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'bloquear_cadastro_aluno')) {
                $table->boolean('bloquear_cadastro_aluno')->default(false);
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'situacoes_especificas_atestados')) {
                $table->text('situacoes_especificas_atestados')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'emitir_ato_autorizativo')) {
                $table->boolean('emitir_ato_autorizativo')->default(false);
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'emitir_ato_criacao_credenciamento')) {
                $table->boolean('emitir_ato_criacao_credenciamento')->default(false);
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_diario_professor')) {
                $table->text('url_diario_professor')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'url_whatsapp')) {
                $table->text('url_whatsapp')->nullable();
            }
            
            if (!Schema::hasColumn('pmieducar.configuracoes_gerais', 'mostrar_botoes_ajuda_login')) {
                $table->boolean('mostrar_botoes_ajuda_login')->default(true);
            }
        });
    }

    public function down()
    {
        Schema::table('pmieducar.configuracoes_gerais', function (Blueprint $table) {
            $columns = [
                'ieducar_background_image',
                'ieducar_background_image_url',
                'ieducar_entity_name',
                'ieducar_login_footer',
                'ieducar_external_footer',
                'ieducar_internal_footer',
                'ieducar_suspension_message',
                'facebook_url',
                'twitter_url',
                'linkedin_url',
                'bloquear_cadastro_aluno',
                'situacoes_especificas_atestados',
                'emitir_ato_autorizativo',
                'emitir_ato_criacao_credenciamento',
                'url_diario_professor',
                'url_whatsapp',
                'mostrar_botoes_ajuda_login'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('pmieducar.configuracoes_gerais', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};