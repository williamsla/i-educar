<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;

class AddMenuExportacoesTCE extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Menu::query()
            ->where('process', Process::TCE_EXPORT)
            ->update([
                'title' => 'Exportação para o TCE',
                'link' => '/exportacao-para-o-tce',
                'parent_id' => Menu::query()->where('old', Process::MENU_SCHOOL_TOOLS_EXPORTS)->valueOrFail('id'),
                'parent_old' => Process::MENU_SCHOOL_TOOLS_EXPORTS,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Menu::query()
            ->where('process', Process::TCE_EXPORT)
            ->update([
                'title' => 'Exportações de documentos',
                'parent_id' => Menu::query()->where('old', Process::MENU_SCHOOL_TOOLS_EXPORTS)->valueOrFail('id'),
                'parent_old' => Process::MENU_SCHOOL_TOOLS_EXPORTS,
            ]);
    }
}
