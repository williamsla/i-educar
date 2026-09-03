<?php

namespace App\Services\SgpExport;

use App\Services\SgpExport\Exporters\MecProfissionalExporter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MecGestaoPresenteExportService
{
    public function download(array $filters): BinaryFileResponse
    {
        $exporter = new MecProfissionalExporter($filters);
        $path = $this->preencherModelo($exporter->rows());

        return response()
            ->download($path, $exporter->fileName() . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<int, array<int, string>>  $linhas
     */
    public function preencherModelo(array $linhas): string
    {
        $template = $this->templatePath();

        if (!is_file($template)) {
            throw new \RuntimeException('Modelo da planilha MEC Gestão Presente não encontrado.');
        }

        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();
        $colunaInicial = 1;
        $linha = MecProfissionalExporter::DATA_START_ROW;

        foreach ($linhas as $valores) {
            foreach (array_values($valores) as $indice => $valor) {
                $coordenada = Coordinate::stringFromColumnIndex($colunaInicial + $indice) . $linha;
                $sheet->setCellValueExplicit($coordenada, (string) $valor, DataType::TYPE_STRING);
            }
            $linha++;
        }

        $ultimaLinha = max(MecProfissionalExporter::DATA_START_ROW, $linha - 1);
        $this->ajustarTabela($spreadsheet, $ultimaLinha);

        $destino = tempnam(sys_get_temp_dir(), 'mec_gp_') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($destino);
        $spreadsheet->disconnectWorksheets();

        return $destino;
    }

    public function templatePath(): string
    {
        return resource_path('templates/mec-gestao-presente/modelo_profissional.xlsx');
    }

    private function ajustarTabela(Spreadsheet $spreadsheet, int $ultimaLinha): void
    {
        if ($ultimaLinha <= 994) {
            return;
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($sheet->getTableCollection() as $tabela) {
                if (!$tabela instanceof Table) {
                    continue;
                }

                $ultimaColuna = Coordinate::stringFromColumnIndex(46);
                $tabela->setRange('A' . MecProfissionalExporter::DATA_START_ROW . ':' . $ultimaColuna . $ultimaLinha);
            }
        } catch (\Throwable $e) {
            // Mantém o intervalo original da tabela do modelo quando não for possível expandir.
        }
    }
}
