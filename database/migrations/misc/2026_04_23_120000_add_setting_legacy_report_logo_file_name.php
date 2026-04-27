<?php

declare(strict_types=1);

use App\Setting;
use App\SettingCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * legacy.report.logo_file_name existia em config e em UPDATEs de metadados,
     * mas nunca foi inserido em populate_settings_table — a UI so lista chaves na tabela settings.
     */
    public function up(): void
    {
        if (Setting::query()->where('key', 'legacy.report.logo_file_name')->exists()) {
            return;
        }

        $categoryId = SettingCategory::query()
            ->where('name', 'Validações de relatórios')
            ->value('id');

        if ($categoryId === null) {
            $categoryId = SettingCategory::query()->value('id');
        }

        Setting::query()->create([
            'key' => 'legacy.report.logo_file_name',
            'value' => 'brasil.png',
            'type' => Setting::TYPE_STRING,
            'description' => 'Logo dos relatórios',
            'setting_category_id' => $categoryId,
            'hint' => 'Nome do ficheiro da imagem (ex.: brasil.png) na pasta de logos dos relatórios. Alinha com a variável de ambiente REPORTS_LOGO quando definida.',
        ]);
    }

    public function down(): void
    {
        Setting::query()->where('key', 'legacy.report.logo_file_name')->delete();
    }
};
