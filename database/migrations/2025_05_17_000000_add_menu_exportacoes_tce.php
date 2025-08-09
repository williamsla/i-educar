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
        Menu::query()->create([
            'parent_id' => Menu::query()->where('old', Process::MENU_SCHOOL_TOOLS_EXPORTS)->firstOrFail()->getKey(),
            'title' => 'Exportação para o TCE',
            'link' => '/exportacao-para-o-tce',
            'process' => Process::TCE_EXPORT,
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
            ->delete();
    }
}
