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
            ['old' => Process::FREQUENCIA_SISTEMA_PRESENCA],
            [
                'parent_id' => $parent->getKey(),
                'process' => Process::FREQUENCIA_SISTEMA_PRESENCA,
                'title' => 'Frequência para o Sistema Presença',
                'description' => 'Relatório de faltas mensais por aluno gerado pelo i-Diário',
                'link' => '/relatorios/frequencia-sistema-presenca',
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
        $menu = Menu::query()->where('process', Process::FREQUENCIA_SISTEMA_PRESENCA)->first();

        if ($menu === null) {
            return;
        }

        LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
            $userType->menus()->detach($menu);
        });

        $menu->delete();
    }
};
