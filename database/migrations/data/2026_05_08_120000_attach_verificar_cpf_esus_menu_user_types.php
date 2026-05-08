<?php

use App\Menu;
use App\Models\LegacyUserType;
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
     * O item "Verificar CPFs (eSUS)" foi criado sem vínculos em menu_tipo_usuario; para usuários
     * não administradores o menu é filtrado por esse relacionamento e o link não aparecia.
     */
    public function up(): void
    {
        $menu = Menu::query()
            ->where('title', 'Verificar CPFs (eSUS)')
            ->where('link', '/intranet/educar_verificar_cpf_esus.php')
            ->first();

        if ($menu === null) {
            return;
        }

        $this->attachMenuIfNotExists(LegacyUserType::all(), $menu);
    }

    public function down(): void
    {
        $menu = Menu::query()
            ->where('title', 'Verificar CPFs (eSUS)')
            ->where('link', '/intranet/educar_verificar_cpf_esus.php')
            ->first();

        if ($menu === null) {
            return;
        }

        LegacyUserType::all()->each(static function (LegacyUserType $userType) use ($menu) {
            $userType->menus()->detach($menu);
        });
    }
};
