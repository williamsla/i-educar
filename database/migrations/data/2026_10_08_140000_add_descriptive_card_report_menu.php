<?php

declare(strict_types=1);

use App\Menu;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Menu::query()->where('old', 999450)->first();

        if ($parent === null) {
            return;
        }

        Menu::query()->updateOrCreate(
            ['old' => 19992021],
            [
                'parent_id' => $parent->getKey(),
                'parent_old' => 999450,
                'process' => 19992021,
                'title' => 'Boletim parecer descritivo',
                'description' => 'Boletim de parecer descritivo',
                'link' => '/module/Reports/ReportDescriptiveCard',
                'order' => 1,
                'active' => true,
            ]
        );
    }

    public function down(): void
    {
        Menu::query()->where('process', 19992021)->delete();
    }
};
