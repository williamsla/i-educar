<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parent = Menu::query()
            ->where('old', Process::CONFIGURATIONS_TOOLS)
            ->first();

        if ($parent) {
            Menu::query()->create([
                'parent_id' => $parent->getKey(),
                'title' => 'Verificar CPFs (eSUS)',
                'description' => 'Verificar CPFs do relatório eSUS no cadastro',
                'link' => '/intranet/educar_verificar_cpf_esus.php',
                'process' => Process::CONFIGURATIONS_TOOLS,
                'order' => 99,
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Menu::query()
            ->where('title', 'Verificar CPFs (eSUS)')
            ->where('link', '/intranet/educar_verificar_cpf_esus.php')
            ->delete();
    }
};
