<?php

use App\Models\DeficiencyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use iEducar\Modules\Educacenso\Model\Transtornos;

return new class extends Migration
{
    /**
     * @return list<array{nm_deficiencia: string, transtorno_educacenso: int}>
     */
    private function rows(): array
    {
        return [
            [
                'nm_deficiencia' => 'Transtorno do Déficit de Atenção com Hiperatividade (TDAH)',
                'transtorno_educacenso' => Transtornos::TDAH,
            ],
            [
                'nm_deficiencia' => 'Transtorno Opositor Desafiador (TOD)',
                'transtorno_educacenso' => Transtornos::OUTROS,
            ],
            [
                'nm_deficiencia' => 'Dislexia',
                'transtorno_educacenso' => Transtornos::OUTROS,
            ],
            [
                'nm_deficiencia' => 'Discalculia',
                'transtorno_educacenso' => Transtornos::DISCALCULIA,
            ],
            [
                'nm_deficiencia' => 'Disgrafia',
                'transtorno_educacenso' => Transtornos::DISGRAFIA,
            ],
            [
                'nm_deficiencia' => 'Dislalia',
                'transtorno_educacenso' => Transtornos::DISLALIA,
            ],
            [
                'nm_deficiencia' => 'Transtorno do Processamento Auditivo Central (TPAC)',
                'transtorno_educacenso' => Transtornos::TPAC,
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->rows() as $row) {
            $exists = DB::table('cadastro.deficiencia')
                ->where('deficiency_type_id', DeficiencyType::DISORDER)
                ->where('nm_deficiencia', $row['nm_deficiencia'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cadastro.deficiencia')->insert([
                'nm_deficiencia' => $row['nm_deficiencia'],
                'deficiencia_educacenso' => null,
                'desconsidera_regra_diferenciada' => false,
                'exigir_laudo_medico' => false,
                'deficiency_type_id' => DeficiencyType::DISORDER,
                'transtorno_educacenso' => $row['transtorno_educacenso'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $names = array_column($this->rows(), 'nm_deficiencia');

        DB::table('cadastro.deficiencia')
            ->where('deficiency_type_id', DeficiencyType::DISORDER)
            ->whereIn('nm_deficiencia', $names)
            ->delete();
    }
};
