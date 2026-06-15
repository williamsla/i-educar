<?php

namespace App\Console\Commands;

use App\Services\ImportZonaResidenciaService;
use Illuminate\Console\Command;

class ImportZonaResidenciaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'import:zona-residencia {--file=import/dados_zona_residencia.csv : Caminho do CSV de origem}';

    /**
     * @var string
     */
    protected $description = 'Importa zona de residência, localização diferenciada e cor/raça a partir do CSV do SISLAME';

    public function handle(): int
    {
        $filePath = base_path($this->option('file'));

        $this->info("Importando dados de: {$filePath}");

        $service = new ImportZonaResidenciaService($this->output);
        $service->import($filePath);

        return self::SUCCESS;
    }
}
