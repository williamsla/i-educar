<?php

namespace Tests\Unit\Services\SgpExport;

use App\Services\SgpExport\Exporters\ComponenteCurricularExporter;
use App\Services\SgpExport\Exporters\EscolaExporter;
use App\Services\SgpExport\Exporters\EstudanteExporter;
use App\Services\SgpExport\Exporters\MatriculaExporter;
use App\Services\SgpExport\Exporters\ProfissionalExporter;
use App\Services\SgpExport\Exporters\TurmaExporter;
use App\Services\SgpExport\SgpExportService;
use Tests\TestCase;

class SgpExportServiceTest extends TestCase
{
    public function test_types_contem_as_planilhas_solicitadas(): void
    {
        $types = SgpExportService::types();

        $this->assertSame('Escolas', $types[SgpExportService::TIPO_ESCOLAS]);
        $this->assertSame('Componentes curriculares', $types[SgpExportService::TIPO_COMPONENTES]);
        $this->assertSame('Profissionais', $types[SgpExportService::TIPO_PROFISSIONAIS]);
        $this->assertSame('Turmas', $types[SgpExportService::TIPO_TURMAS]);
        $this->assertSame('Estudantes', $types[SgpExportService::TIPO_ESTUDANTES]);
        $this->assertSame('Matrículas', $types[SgpExportService::TIPO_MATRICULAS]);
        $this->assertArrayHasKey(SgpExportService::TIPO_TODAS, $types);
    }

    public function test_make_retorna_exportador_com_cabecalhos_do_sgp(): void
    {
        $filters = ['ano' => 2026, 'institution_id' => 1];
        $service = new SgpExportService;

        $this->assertSame(
            ['CO_ENTIDADE', 'NO_ENTIDADE'],
            array_slice($service->make(SgpExportService::TIPO_ESCOLAS, $filters)->headings(), 0, 2)
        );
        $this->assertContains('CO_COMPONENTE_CURRICULAR', $service->make(SgpExportService::TIPO_COMPONENTES, $filters)->headings());
        $this->assertContains('PROFISSIONAL_CPF', $service->make(SgpExportService::TIPO_PROFISSIONAIS, $filters)->headings());
        $this->assertContains('CO_TURMA_REDE', $service->make(SgpExportService::TIPO_TURMAS, $filters)->headings());
        $this->assertContains('ESTUDANTE_CPF', $service->make(SgpExportService::TIPO_ESTUDANTES, $filters)->headings());
        $this->assertContains('ESTUDANTE_MATRICULA_SITUACAO', $service->make(SgpExportService::TIPO_MATRICULAS, $filters)->headings());
        $this->assertContains('CO_MATRICULA_REDE', $service->make(SgpExportService::TIPO_MATRICULAS, $filters)->headings());
    }

    public function test_make_rejeita_tipo_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SgpExportService)->make('invalido', []);
    }

    public function test_nomes_de_arquivo_incluem_o_ano(): void
    {
        $filters = ['ano' => 2026];

        $this->assertSame('sgp_escolas_2026', (new EscolaExporter($filters))->fileName());
        $this->assertSame('sgp_componentes_curriculares_2026', (new ComponenteCurricularExporter($filters))->fileName());
        $this->assertSame('sgp_profissionais_2026', (new ProfissionalExporter($filters))->fileName());
        $this->assertSame('sgp_turmas_2026', (new TurmaExporter($filters))->fileName());
        $this->assertSame('sgp_estudantes_2026', (new EstudanteExporter($filters))->fileName());
        $this->assertSame('sgp_matriculas_2026', (new MatriculaExporter($filters))->fileName());
    }
}
