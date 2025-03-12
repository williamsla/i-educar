<?php

use App\Support\Database\AsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    use AsView;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Remover a view 'religions' se existir
        DB::unprepared('DROP VIEW IF EXISTS religions;');

        // 2. Remover a coluna 'ref_usuario_exc' e suas dependências
        DB::unprepared('ALTER TABLE pmieducar.religiao DROP COLUMN IF EXISTS ref_usuario_exc CASCADE;');

        // 3. Remover a coluna 'ref_usuario_cad' e suas dependências
        DB::unprepared('ALTER TABLE pmieducar.religiao DROP COLUMN IF EXISTS ref_usuario_cad CASCADE;');

        // 4. Renomear a tabela 'pmieducar.religiao' para 'religions'
        DB::unprepared('ALTER TABLE IF EXISTS pmieducar.religiao RENAME TO religions;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 1. Renomear a tabela 'religions' de volta para 'pmieducar.religiao'
        DB::unprepared('ALTER TABLE IF EXISTS religions RENAME TO religiao;');

        // 2. Adicionar a coluna 'ref_usuario_exc' de volta à tabela 'pmieducar.religiao'
        Schema::table('pmieducar.religiao', function ($table) {
            $table->integer('ref_usuario_exc')->nullable();
        });

        // 3. Adicionar a coluna 'ref_usuario_cad' de volta à tabela 'pmieducar.religiao'
        Schema::table('pmieducar.religiao', function ($table) {
            $table->integer('ref_usuario_cad')->nullable();
        });

        // 4. (Opcional) Recriar a view 'religions' se necessário
        // $this->createView('religions');
    }

    // Método auxiliar para recriar a view 'religions' (ajuste conforme necessário)
    protected function createView($viewName)
    {
        $viewSql = 'CREATE VIEW ' . $viewName . ' AS SELECT * FROM pmieducar.religiao;';
        DB::unprepared($viewSql);
    }
};
