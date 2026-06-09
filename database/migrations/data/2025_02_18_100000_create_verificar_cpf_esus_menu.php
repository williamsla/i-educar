<?php

use App\Menu;
use App\Models\LegacyUserType;
use App\Process;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private function attachMenuIfNotExists($userTypes, Menu $menu): void
    {
        $userTypes->each(static function (LegacyUserType $userType) use ($menu) {
            $exists = $userType->menus()->where('menu_id', $menu->getKey())->exists();

            if (! $exists) {
                $userType->menus()->attach($menu, [
                    'visualiza' => 1,
                    'cadastra' => 1,
                    'exclui' => 1,
                ]);
            }
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parent = Menu::query()
            ->where('old', Process::CONFIGURATIONS_TOOLS)
            ->first();

        if ($parent) {
            $menu = Menu::query()->create([
                'parent_id' => $parent->getKey(),
                'title' => 'Verificar CPFs (eSUS)',
                'description' => 'Verificar CPFs do relatório eSUS no cadastro',
                'link' => '/intranet/educar_verificar_cpf_esus.php',
                'process' => Process::CONFIGURATIONS_TOOLS,
                'order' => 99,
                'active' => true,
            ]);

            $this->attachMenuIfNotExists(LegacyUserType::all(), $menu);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menu = Menu::query()
            ->where('title', 'Verificar CPFs (eSUS)')
            ->where('link', '/intranet/educar_verificar_cpf_esus.php')
            ->first();

        if ($menu !== null) {
            LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
                $userType->menus()->detach($menu);
            });
            $menu->delete();
        }
    }
};
