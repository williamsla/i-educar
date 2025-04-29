<?php

namespace Database\Seeders;

use App\Menu;
use App\Process;
use Illuminate\Database\Seeder;

class MenuMerendaSeeder extends Seeder
{

    public function run(): void
    {

        Menu::query()->create([
            'parent_id' => null,
            'title' => 'Merenda',
            'link' => '/intranet/educar_merenda.php',
            'icon' => 'fa-utensils',
            'order' => 7,
            'type' => 1,
            'process' => Process::MENU_GOODS,
            'old' => Process::MENU_GOODS,
            'active' => true
        ]);

        Menu::query()->create([
            'parent_id' => Menu::query()->where('process', Process::MENU_GOODS)->firstOrFail()->getKey(),
            'title' => 'Fornecedores',
            'description' => 'Fornecedores',
            'link' => '/intranet/educar_fornecedores.php',
            'icon' => null,
            'order' => 1,
            'type' => 2,
            'process' => Process::FORNECEDORES,
            'old' => Process::FORNECEDORES,
            'active' => true
        ]);

        Menu::query()->create([
            'parent_id' => Menu::query()->where('process', Process::MENU_GOODS)->firstOrFail()->getKey(),
            'title' => 'Produtos',
            'description' => 'Produtos',
            'link' => '/intranet/educar_produtos.php',
            'icon' => null,
            'order' => 1,
            'type' => 2,
            'process' => Process::PRODUTOS,
            'old' => Process::PRODUTOS,
            'active' => true
        ]);

        Menu::query()->create([
            'parent_id' => Menu::query()->where('process', Process::MENU_GOODS)->firstOrFail()->getKey(),
            'title' => 'Entradas',
            'description' => 'Entradas',
            'link' => '/intranet/educar_entradas.php',
            'icon' => null,
            'order' => 1,
            'type' => 2,
            'process' => Process::ENTRADAS,
            'old' => Process::ENTRADAS,
            'active' => true
        ]);

        Menu::query()->create([
            'parent_id' => Menu::query()->where('process', Process::MENU_GOODS)->firstOrFail()->getKey(),
            'title' => 'Saidas',
            'description' => 'Saidas',
            'link' => '/intranet/educar_saidas.php',
            'icon' => null,
            'order' => 1,
            'type' => 2,
            'process' => Process::SAIDAS,
            'old' => Process::SAIDAS,
            'active' => true
        ]);

        Menu::query()->create([
            'parent_id' => Menu::query()->where('process', Process::MENU_GOODS)->firstOrFail()->getKey(),
            'title' => 'Cardapios',
            'description' => 'Cardapios',
            'link' => '/intranet/educar_cardapios.php',
            'icon' => null,
            'order' => 1,
            'type' => 2,
            'process' => Process::CARDAPIOS,
            'old' => Process::CARDAPIOS,
            'active' => true
        ]);
    }
}