<?php

namespace App\Console\Commands;

use App\Services\ImportSislameBeneficioTransporteService;
use Illuminate\Console\Command;

class ImportSislameBeneficioTransporteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'import:sislame-beneficio-transporte {--file=import/migrar_do_sislame.csv : Caminho do CSV de origem}';

    /**
     * @var string
     */
    protected $description = 'Importa benefício (Bolsa Família) e veículo de transporte escolar a partir do CSV do SISLAME';

    public function handle(): int
    {
        $filePath = base_path($this->option('file'));

        $this->info("Importando dados de: {$filePath}");

        $service = new ImportSislameBeneficioTransporteService($this->output);
        $service->import($filePath);

        return self::SUCCESS;
    }
}
