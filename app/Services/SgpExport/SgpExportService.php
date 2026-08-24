<?php

namespace App\Services\SgpExport;

use App\Services\SgpExport\Exporters\AbstractSgpExporter;
use App\Services\SgpExport\Exporters\ComponenteCurricularExporter;
use App\Services\SgpExport\Exporters\EscolaExporter;
use App\Services\SgpExport\Exporters\EstudanteExporter;
use App\Services\SgpExport\Exporters\MatriculaExporter;
use App\Services\SgpExport\Exporters\ProfissionalExporter;
use App\Services\SgpExport\Exporters\TurmaExporter;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SgpExportService
{
    public const TIPO_ESCOLAS = 'escolas';

    public const TIPO_COMPONENTES = 'componentes';

    public const TIPO_PROFISSIONAIS = 'profissionais';

    public const TIPO_TURMAS = 'turmas';

    public const TIPO_ESTUDANTES = 'estudantes';

    public const TIPO_MATRICULAS = 'matriculas';

    public const TIPO_TODAS = 'todas';

    public static function types(): array
    {
        return [
            self::TIPO_ESCOLAS => 'Escolas',
            self::TIPO_COMPONENTES => 'Componentes curriculares',
            self::TIPO_PROFISSIONAIS => 'Profissionais',
            self::TIPO_TURMAS => 'Turmas',
            self::TIPO_ESTUDANTES => 'Estudantes',
            self::TIPO_MATRICULAS => 'Matrículas',
            self::TIPO_TODAS => 'Todas as planilhas (ZIP)',
        ];
    }

    public function download(array $filters): BinaryFileResponse|StreamedResponse
    {
        $tipo = $filters['tipo'] ?? null;

        if ($tipo === self::TIPO_TODAS) {
            return $this->downloadZip($filters);
        }

        $exporter = $this->make($tipo, $filters);

        return Excel::download(
            new SgpSpreadsheetExport($exporter->headings(), $exporter->rows(), $exporter->fileName()),
            $exporter->fileName() . '.xlsx'
        );
    }

    private function downloadZip(array $filters): BinaryFileResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'sgp_') . '.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->exportersForZip() as $tipo) {
            $exporter = $this->make($tipo, $filters);
            $conteudo = Excel::raw(
                new SgpSpreadsheetExport($exporter->headings(), $exporter->rows(), $exporter->fileName()),
                ExcelFormat::XLSX
            );
            $zip->addFromString($exporter->fileName() . '.xlsx', $conteudo);
        }

        $zip->close();

        return response()
            ->download($zipPath, 'sgp_exportacao_' . ($filters['ano'] ?? date('Y')) . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * @return string[]
     */
    private function exportersForZip(): array
    {
        return [
            self::TIPO_ESCOLAS,
            self::TIPO_COMPONENTES,
            self::TIPO_PROFISSIONAIS,
            self::TIPO_TURMAS,
            self::TIPO_ESTUDANTES,
            self::TIPO_MATRICULAS,
        ];
    }

    public function make(string $tipo, array $filters): AbstractSgpExporter
    {
        return match ($tipo) {
            self::TIPO_ESCOLAS => new EscolaExporter($filters),
            self::TIPO_COMPONENTES => new ComponenteCurricularExporter($filters),
            self::TIPO_PROFISSIONAIS => new ProfissionalExporter($filters),
            self::TIPO_TURMAS => new TurmaExporter($filters),
            self::TIPO_ESTUDANTES => new EstudanteExporter($filters),
            self::TIPO_MATRICULAS => new MatriculaExporter($filters),
            default => throw new \InvalidArgumentException('Tipo de exportação SGP inválido.'),
        };
    }
}
