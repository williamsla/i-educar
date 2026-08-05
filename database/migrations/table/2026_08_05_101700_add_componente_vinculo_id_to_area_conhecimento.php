<?php

use App\Support\Database\WhenDeleted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WhenDeleted;

    public function up(): void
    {
        Schema::table('modules.area_conhecimento', function (Blueprint $table) {
            $table->integer('componente_vinculo_id')->nullable();
            $table->foreign('componente_vinculo_id')
                ->references('id')
                ->on('modules.componente_curricular')
                ->nullOnDelete();
        });

        Schema::table('modules.area_conhecimento_excluidos', function (Blueprint $table) {
            $table->integer('componente_vinculo_id')->nullable();
        });

        $this->dropTriggerWhenDeleted('modules.area_conhecimento');
        $this->whenDeletedMoveTo('modules.area_conhecimento', 'modules.area_conhecimento_excluidos', [
            'id',
            'instituicao_id',
            'nome',
            'secao',
            'ordenamento_ac',
            'agrupar_descritores',
            'componente_vinculo_id',
        ]);
    }

    public function down(): void
    {
        $this->dropTriggerWhenDeleted('modules.area_conhecimento');
        $this->whenDeletedMoveTo('modules.area_conhecimento', 'modules.area_conhecimento_excluidos', [
            'id',
            'instituicao_id',
            'nome',
            'secao',
            'ordenamento_ac',
            'agrupar_descritores',
        ]);

        Schema::table('modules.area_conhecimento', function (Blueprint $table) {
            $table->dropForeign(['componente_vinculo_id']);
            $table->dropColumn('componente_vinculo_id');
        });

        Schema::table('modules.area_conhecimento_excluidos', function (Blueprint $table) {
            $table->dropColumn('componente_vinculo_id');
        });
    }
};
