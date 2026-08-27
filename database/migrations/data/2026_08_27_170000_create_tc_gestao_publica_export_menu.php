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

        $menu = Menu::query()->updateOrCreate(
            ['old' => Process::TC_GESTAO_PUBLICA_EXPORT],
            [
                'parent_id' => $parent->getKey(),
                'process' => Process::TC_GESTAO_PUBLICA_EXPORT,
                'title' => 'TC Gestão Pública',
                'description' => 'Exportação CSV no padrão Educação SIAP do TC Gestão Pública',
                'link' => '/exportacao-tc-gestao-publica',
                'order' => 96,
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

    public function down(): void
    {
        $menu = Menu::query()->where('process', Process::TC_GESTAO_PUBLICA_EXPORT)->first();

        if ($menu === null) {
            return;
        }

        LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
            $userType->menus()->detach($menu);
        });

        $menu->delete();
    }
};
