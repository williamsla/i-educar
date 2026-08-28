<?php

namespace App\Services\Siap;

use App\Services\Siap\Exporters\AbstractSiapExporter;
use App\Services\Siap\Exporters\AlunoExporter;
use App\Services\Siap\Exporters\CapacitacaoProfissionalEducacaoExporter;
use App\Services\Siap\Exporters\CardapioExporter;
use App\Services\Siap\Exporters\DespesaPorEscolaExporter;
use App\Services\Siap\Exporters\EmptyHeaderExporter;
use App\Services\Siap\Exporters\EquipamentoEscolaExporter;
use App\Services\Siap\Exporters\EscolaExporter;
use App\Services\Siap\Exporters\EstruturaEscolarExporter;
use App\Services\Siap\Exporters\FaltasProfissionalEducacaoExporter;
use App\Services\Siap\Exporters\MatriculaExporter;
use App\Services\Siap\Exporters\ProfissionalEducacaoExporter;
use App\Services\Siap\Exporters\ResponsavelTecnicoExporter;
use App\Services\Siap\Exporters\TurmaAlunoExporter;
use App\Services\Siap\Exporters\TurmaExporter;
use App\Services\Siap\Exporters\TurmaProfissionalExporter;
use App\Services\Siap\Exporters\VinculoProfissionalEducacaoExporter;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class SiapExportService
{
    private array $alerts = [];

    public function export(int $ano, int $mes, int $instituicaoId, bool $somenteAlunosComInep = false): array
    {
        $this->alerts = [];
        $codigo = (string) config('siap.codigo', '000');

        if ($codigo === '' || $codigo === '000') {
            $this->alerts[] = 'ATENÇÃO: configure SIAP_CODIGO (código do município no TCE-AL) no .env.';
        }

        $arquivos = [];

        foreach ($this->exporters($codigo, $ano, $mes, $instituicaoId, $somenteAlunosComInep) as $exporter) {
            try {
                $conteudo = $exporter->export();
                $arquivos[$exporter->fileName() . '.xml'] = $conteudo;
                foreach ($exporter->getAlerts() as $alert) {
                    $this->alerts[] = $alert;
                }
            } catch (\Throwable $e) {
                $this->alerts[] = '[' . $exporter->fileName() . '] ERRO: ' . $e->getMessage();
                $fallback = new SiapXmlBuilder($codigo, (string) $ano, (string) $mes);
                $arquivos[$exporter->fileName() . '.xml'] = $fallback->toFormattedXml();
            }
        }

        return $this->compactar($arquivos, $ano, $mes);
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    /**
     * @return AbstractSiapExporter[]
     */
    private function exporters(string $codigo, int $ano, int $mes, int $instituicaoId, bool $somenteAlunosComInep = false): array
    {
        return [
            new EscolaExporter($codigo, $ano, $mes, $instituicaoId),
            new EstruturaEscolarExporter($codigo, $ano, $mes, $instituicaoId),
            new EquipamentoEscolaExporter($codigo, $ano, $mes, $instituicaoId),
            new AlunoExporter($codigo, $ano, $mes, $instituicaoId, $somenteAlunosComInep),
            new MatriculaExporter($codigo, $ano, $mes, $instituicaoId),
            new TurmaExporter($codigo, $ano, $mes, $instituicaoId),
            new TurmaAlunoExporter($codigo, $ano, $mes, $instituicaoId, $somenteAlunosComInep),
            new ProfissionalEducacaoExporter($codigo, $ano, $mes, $instituicaoId),
            new VinculoProfissionalEducacaoExporter($codigo, $ano, $mes, $instituicaoId),
            new TurmaProfissionalExporter($codigo, $ano, $mes, $instituicaoId),
            new CapacitacaoProfissionalEducacaoExporter($codigo, $ano, $mes, $instituicaoId),
            new FaltasProfissionalEducacaoExporter($codigo, $ano, $mes, $instituicaoId),
            new CardapioExporter($codigo, $ano, $mes, $instituicaoId),
            new ResponsavelTecnicoExporter($codigo, $ano, $mes, $instituicaoId),
            new EmptyHeaderExporter($codigo, $ano, $mes, $instituicaoId, 'AtividadesResponsavelTecnico'),
            new EmptyHeaderExporter($codigo, $ano, $mes, $instituicaoId, 'AgriculturaFamiliar'),
            new EmptyHeaderExporter($codigo, $ano, $mes, $instituicaoId, 'ConselhoAlimentacaoEscolar'),
            new EmptyHeaderExporter($codigo, $ano, $mes, $instituicaoId, 'EventoAlimentacaoEscolar'),
            new DespesaPorEscolaExporter($codigo, $ano, $mes, $instituicaoId),
        ];
    }

    private function compactar(array $arquivos, int $ano, int $mes): array
    {
        $dir = 'exportacoes/siap';
        Storage::disk('public')->makeDirectory($dir);

        $baseName = sprintf('SIAP_%04d_%02d', $ano, $mes);
        $zipRelative = $dir . '/' . $baseName . '.zip';
        $txtRelative = $dir . '/' . $baseName . '_avisos.txt';
        $zipPath = Storage::disk('public')->path($zipRelative);

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP SIAP.');
        }

        foreach ($arquivos as $nome => $conteudo) {
            $xmlRelative = $dir . '/' . $nome;
            Storage::disk('public')->put($xmlRelative, $conteudo);
            $zip->addFile(Storage::disk('public')->path($xmlRelative), $nome);
        }

        $zip->close();

        Storage::disk('public')->put(
            $txtRelative,
            empty($this->alerts) ? 'Exportação SIAP concluída sem avisos.' : implode(PHP_EOL, $this->alerts)
        );

        return [
            'zipUrl' => asset(Storage::disk('public')->url($zipRelative)),
            'txtUrl' => asset(Storage::disk('public')->url($txtRelative)),
            'alerts' => $this->alerts,
        ];
    }
}
