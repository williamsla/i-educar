<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pmieducar.configuracoes_gerais 
            ADD COLUMN IF NOT EXISTS ieducar_background_image varchar(200),
            ADD COLUMN IF NOT EXISTS ieducar_background_image_url varchar(500),
            ADD COLUMN IF NOT EXISTS ieducar_entity_name varchar(200),
            ADD COLUMN IF NOT EXISTS url_diario_professor varchar(200),
            ADD COLUMN IF NOT EXISTS url_whatsapp varchar(200),
            ADD COLUMN IF NOT EXISTS mostrar_botoes_ajuda_login boolean DEFAULT false;
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pmieducar.configuracoes_gerais 
            DROP COLUMN IF EXISTS ieducar_background_image,
            DROP COLUMN IF EXISTS ieducar_background_image_url,
            DROP COLUMN IF EXISTS ieducar_entity_name,
            DROP COLUMN IF EXISTS url_diario_professor,
            DROP COLUMN IF EXISTS url_whatsapp,
            DROP COLUMN IF EXISTS mostrar_botoes_ajuda_login;
        ");
    }
};
