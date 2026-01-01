<?php

namespace App\Reports;

use iEducar\Reports\Contracts\TeacherReportCard;

class TeacherReportCardImplementation implements TeacherReportCard
{
    /**
     * Retorna as opções de modelo de boletim do professor
     *
     * @return array
     */
    public function getOptions(): array
    {
        // Valores baseados em configurações comuns do iEducar
        // Você pode ajustar esses valores conforme necessário
        return [
            '1' => 'Modelo 1 (Padrão)',
            '2' => 'Modelo 2',
            '3' => 'Modelo 3',
        ];
    }
}
