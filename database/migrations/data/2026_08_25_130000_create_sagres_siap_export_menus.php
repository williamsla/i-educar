<?php

declare(strict_types=1);

use App\Menu;
use App\Models\LegacyUserType;
use App\Process;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Menu::query()
            ->where(function ($query) {
                $query->where('old', Process::MENU_SCHOOL_TOOLS_EXPORTS)
                    ->orWhere('process', Process::MENU_SCHOOL_TOOLS_EXPORTS);
            })
            ->first();

        if ($parent === null) {
            return;
        }

        $items = [
            [
                'process' => Process::SAGRES_EXPORT,
                'title' => 'SAGRES',
                'description' => 'Exportação SAGRES TCE-SE',
                'link' => '/exportacao-sagres',
                'order' => 97,
            ],
            [
                'process' => Process::SIAP_EXPORT,
                'title' => 'SIAP',
                'description' => 'Exportação SIAP TCE-AL',
                'link' => '/exportacao-siap',
                'order' => 98,
            ],
        ];

        foreach ($items as $item) {
            $menu = Menu::query()->updateOrCreate(
                ['old' => $item['process']],
                [
                    'parent_id' => $parent->getKey(),
                    'process' => $item['process'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'link' => $item['link'],
                    'order' => $item['order'],
                    'parent_old' => Process::MENU_SCHOOL_TOOLS_EXPORTS,
                    'active' => true,
                ]
            );

            LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
                $exists = $userType->menus()->where('menu_id', $menu->getKey())->exists();

                if (!$exists) {
                    $userType->menus()->attach($menu, [
                        'visualiza' => 1,
                        'cadastra' => 1,
                        'exclui' => 1,
                    ]);
                }
            });
        }

        // Remove o item genérico "Exportação para o TCE", se existir,
        // para evitar duplicidade com SAGRES/SIAP.
        $tceMenu = Menu::query()->where('process', Process::TCE_EXPORT)->first();
        if ($tceMenu) {
            LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($tceMenu) {
                $userType->menus()->detach($tceMenu);
            });
            $tceMenu->delete();
        }
    }

    public function down(): void
    {
        foreach ([Process::SAGRES_EXPORT, Process::SIAP_EXPORT] as $process) {
            $menu = Menu::query()->where('process', $process)->first();

            if ($menu === null) {
                continue;
            }

            LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
                $userType->menus()->detach($menu);
            });

            $menu->delete();
        }
    }
};
