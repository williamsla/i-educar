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
        $parentId = Menu::query()
            ->where('old', Process::MENU_SCHOOL_TOOLS_EXPORTS)
            ->value('id');

        if ($parentId) {
            Menu::query()
                ->where('process', Process::TCE_EXPORT)
                ->update([
                    'title'      => 'Exportação para o TCE',
                    'link'       => '/exportacao-para-o-tce',
                    'parent_id'  => $parentId,
                    'parent_old' => Process::MENU_SCHOOL_TOOLS_EXPORTS,
                ]);
        }
    }
}
