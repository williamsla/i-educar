<?php

namespace Tests\Unit\Services\SgpExport;

use App\Services\SgpExport\Exporters\MecProfissionalExporter;
use App\Services\SgpExport\MecGestaoPresenteExportService;
use App\Services\SgpExport\SgpCodeMappers;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MecGestaoPresenteExportServiceTest extends TestCase
{
    public function test_cabecalhos_seguem_o_modelo_oficial(): void
    {
        $headings = (new MecProfissionalExporter(['ano' => 2026]))->headings();

        $this->assertCount(46, $headings);
        $this->assertSame('PROFISSIONAL_CPF', $headings[0]);
        $this->assertSame('PROFISSIONAL_RG', $headings[1]);
        $this->assertSame('NATUREZA_INSTITUICAO_MEDIO_PROFISSIONAL', $headings[17]);
        $this->assertSame('CO_ENTIDADE_VINCULO', $headings[34]);
        $this->assertSame('AREA_CONHECIMENTO_VINCULO_PROFISSIONAL', $headings[45]);
    }

    public function test_nome_do_arquivo_inclui_o_ano(): void
    {
        $this->assertSame(
            'mec_gestao_presente_profissionais_2026',
            (new MecProfissionalExporter(['ano' => 2026]))->fileName()
        );
    }

    public function test_modelo_oficial_existe_com_as_colunas_esperadas(): void
    {
        $service = new MecGestaoPresenteExportService;
        $path = $service->templatePath();

        $this->assertFileExists($path);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('PROFISSIONAL_CPF', $sheet->getCell('A2')->getValue());
        $this->assertSame('AREA_CONHECIMENTO_VINCULO_PROFISSIONAL', $sheet->getCell('AT2')->getValue());
        $this->assertSame('CPF*', $sheet->getCell('A3')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_preenche_o_modelo_a_partir_da_linha_4(): void
    {
        $service = new MecGestaoPresenteExportService;
        $linha = array_fill(0, 46, '');
        $linha[0] = '12345678901';
        $linha[2] = 'Maria da Silva';

        $path = $service->preencherModelo([$linha]);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('PROFISSIONAL_CPF', $sheet->getCell('A2')->getValue());
        $this->assertSame('12345678901', $sheet->getCell('A4')->getValue());
        $this->assertSame('Maria da Silva', $sheet->getCell('C4')->getValue());

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function test_mapeia_dominios_da_planilha_mec(): void
    {
        $this->assertSame('01234567890', SgpCodeMappers::cpfOnzeDigitos('123.456.789-0'));
        $this->assertSame('12345678', SgpCodeMappers::rg('12.345.678-9'));
        $this->assertSame('1', SgpCodeMappers::nacionalidadeMec(1));
        $this->assertSame('076', SgpCodeMappers::paisIso(null, 1));
        $this->assertSame('0', SgpCodeMappers::paisIso(null, 3));
        $this->assertSame('7', SgpCodeMappers::nivelEscolaridadeMec(6));
        $this->assertSame('10', SgpCodeMappers::nivelEscolaridadeMec(6, '7'));
        $this->assertSame('1;3', SgpCodeMappers::deficienciaMec([1, 2]));
        $this->assertSame('2', SgpCodeMappers::tipoFormacaoAcademicaGrau(2));
        $this->assertSame('7', SgpCodeMappers::tipoFormacaoAcademicaPos(3));
        $this->assertSame('1', SgpCodeMappers::naturezaInstituicao(2));
        $this->assertSame('2', SgpCodeMappers::funcaoMec(4));
        $this->assertSame('10', SgpCodeMappers::funcaoMec(null, false, 'Diretor Escolar'));
        $this->assertSame('1', SgpCodeMappers::perfilVinculoMec('10'));
        $this->assertSame('0', SgpCodeMappers::perfilVinculoMec('1'));
        $this->assertSame('1', SgpCodeMappers::tipoVinculoMec(1, 4));
        $this->assertSame('5', SgpCodeMappers::tipoVinculoMec(null, 5));
        $this->assertSame('40', SgpCodeMappers::cargaHorariaSemanal('40:00:00', 20));
        $this->assertSame('5', SgpCodeMappers::areaConhecimentoVinculo(99));
        $this->assertSame('1', SgpCodeMappers::areaConhecimentoVinculo(6));
        $this->assertSame('10', SgpCodeMappers::funcaoMecDeCargoGestor(1));
        $this->assertSame('11', SgpCodeMappers::funcaoMecDeCargoGestor(2));
        $this->assertSame('11', SgpCodeMappers::funcaoMec(null, false, 'Coordenador Pedagógico'));
        $this->assertSame('16', SgpCodeMappers::funcaoMec(null, false, 'Vice-diretor'));
        $this->assertEqualsCanonicalizing(['1', '10'], SgpCodeMappers::codigosFuncaoVinculo(
            ['Diretor Escolar'],
            1,
            1,
            'Professor',
            true
        ));
        $this->assertEqualsCanonicalizing(['11'], SgpCodeMappers::codigosFuncaoVinculo(
            ['Coordenador Pedagógico'],
            2,
            null,
            null,
            false
        ));
    }
}
