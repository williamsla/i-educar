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
            ['old' => Process::INSTITUTO_BRASIL_SOLIDARIO_EXPORT],
            [
                'parent_id' => $parent->getKey(),
                'process' => Process::INSTITUTO_BRASIL_SOLIDARIO_EXPORT,
                'title' => 'Instituto Brasil Solidário',
                'description' => 'Exportação da planilha Dados das Escolas do Instituto Brasil Solidário',
                'link' => '/exportacao-instituto-brasil-solidario',
                'order' => 97,
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
        $menu = Menu::query()->where('process', Process::INSTITUTO_BRASIL_SOLIDARIO_EXPORT)->first();

        if ($menu === null) {
            return;
        }

        LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
            $userType->menus()->detach($menu);
        });

        $menu->delete();
    }
};
