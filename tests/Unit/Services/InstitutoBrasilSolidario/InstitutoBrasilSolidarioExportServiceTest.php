<?php

namespace Tests\Unit\Services\InstitutoBrasilSolidario;

use App\Services\InstitutoBrasilSolidario\EscolaPlanilha;
use App\Services\InstitutoBrasilSolidario\EtapaBuckets;
use App\Services\InstitutoBrasilSolidario\InstitutoBrasilSolidarioWorkbook;
use App\Services\InstitutoBrasilSolidario\KitCalculator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InstitutoBrasilSolidarioExportServiceTest extends TestCase
{
    public function test_mapeia_etapas_do_educacenso_para_as_colunas_da_planilha(): void
    {
        $this->assertSame(EtapaBuckets::CRECHE, EtapaBuckets::fromCodigo(1));
        $this->assertSame(EtapaBuckets::PRE, EtapaBuckets::fromCodigo(2));
        $this->assertSame(EtapaBuckets::AI1, EtapaBuckets::fromCodigo(14));
        $this->assertSame(EtapaBuckets::AF9, EtapaBuckets::fromCodigo(41));
        $this->assertSame(EtapaBuckets::EM1, EtapaBuckets::fromCodigo(25));
        $this->assertSame(EtapaBuckets::EJA_EM, EtapaBuckets::fromCodigo(71));
        $this->assertNull(EtapaBuckets::fromCodigo(22));
        $this->assertSame(EtapaBuckets::AI2, EtapaBuckets::resolver(null, 22, '2º ANO'));
        $this->assertSame(EtapaBuckets::AF6, EtapaBuckets::resolver(19, 22, 'Multisseriada'));
        $this->assertSame(EtapaBuckets::CRECHE, EtapaBuckets::colunaDoCurso('ei', 1, null));
        $this->assertSame(EtapaBuckets::PRE, EtapaBuckets::colunaDoCurso('ei', 2, null));
        $this->assertSame(EtapaBuckets::PRE, EtapaBuckets::colunaDoCurso('ei', 1, 2));
        $this->assertSame(EtapaBuckets::AI1, EtapaBuckets::colunaDoCurso('ai', 1, null));
        $this->assertSame(EtapaBuckets::AI3, EtapaBuckets::colunaDoCurso('ai', 3, 16));
        $this->assertSame(EtapaBuckets::AI1, EtapaBuckets::colunaDoCurso('ai', 1, 19));
        $this->assertSame(EtapaBuckets::AF6, EtapaBuckets::colunaDoCurso('ai', 6, null));
        $this->assertSame(EtapaBuckets::AF6, EtapaBuckets::colunaDoCurso('af', 1, null));
        $this->assertSame(EtapaBuckets::AF6, EtapaBuckets::colunaDoCurso('af', 6, null));
        $this->assertSame(EtapaBuckets::AF7, EtapaBuckets::colunaDoCurso('af', 2, 20));
        $this->assertSame(EtapaBuckets::EM2, EtapaBuckets::colunaDoCurso('em', 2, null));
        $this->assertSame(EtapaBuckets::EJA_AI, EtapaBuckets::colunaDoCurso('eja_ai', 4, 70));
        $this->assertSame(EtapaBuckets::EJA_AF, EtapaBuckets::colunaDoCurso('eja_af', 1, null));
    }

    public function test_calcula_kits_pelas_faixas_do_modelo(): void
    {
        $this->assertSame(0, KitCalculator::piqueniqueIniciais(0));
        $this->assertSame(3, KitCalculator::piqueniqueIniciais(20));
        $this->assertSame(6, KitCalculator::piqueniqueIniciais(40));
        $this->assertSame(10, KitCalculator::piqueniqueIniciais(150));
        $this->assertSame(6, KitCalculator::bonsNegocios(200, 10));
        $this->assertSame(2, KitCalculator::bonsNegocios(200, 2));
        $this->assertSame(1, KitCalculator::bonsNegociosEja(10, 2));
        $this->assertSame(2, KitCalculator::picCityEja(40, 4));
    }

    public function test_preenche_as_abas_do_modelo(): void
    {
        $escola = new EscolaPlanilha(
            sequencia: 0,
            nome: 'ESCOLA MUNICIPAL TESTE',
            inep: '12345678',
            rede: 'MUNICIPAL',
            zona: 'Urbana',
            municipio: 'IGACI, AL',
            redesSociais: '',
            contato: 'Maria Gestora',
            telefone: '(82) 99999-9999',
            email: 'escola@exemplo.test',
            docentes: 4,
            funcionarios: 2,
            defAlunos: [
                'motora' => 1,
                'intelectual' => 0,
                'psicossocial' => 2,
                'visual' => 0,
                'auditiva' => 0,
            ],
            defEducadores: [
                'motora' => 0,
                'intelectual' => 0,
                'psicossocial' => 0,
                'visual' => 0,
                'auditiva' => 0,
            ],
            turmas: array_merge(EtapaBuckets::zeros(), [
                EtapaBuckets::AI1 => 2,
                EtapaBuckets::CRECHE => 1,
            ]),
            alunos: array_merge(EtapaBuckets::zeros(), [
                EtapaBuckets::AI1 => 40,
                EtapaBuckets::CRECHE => 8,
            ]),
            educadores: [
                'ei' => 1,
                'ai' => 3,
                'af' => 0,
                'em' => 0,
                'eja' => 0,
            ],
        );

        $caminho = (new InstitutoBrasilSolidarioWorkbook)->salvar('IGACI, AL', [
            'razao_social' => 'SECRETARIA MUNICIPAL DE EDUCAÇÃO',
            'logradouro' => 'Rua das Flores',
            'numero' => '10',
            'bairro' => 'Centro',
            'cep' => '57600-000',
            'municipio' => 'IGACI, AL',
            'uf' => 'AL',
            'responsavel' => 'João Secretário',
            'telefone' => '(82) 98888-7777',
            'cargo' => 'Secretário(a)',
        ], [$escola]);

        $planilha = IOFactory::load($caminho);

        $gerais = $planilha->getSheetByName('Dados_Gerais');
        $this->assertNotNull($gerais);
        $this->assertSame('IGACI, AL', $gerais->getCell('A1')->getValue());
        $this->assertSame('Nome da Escola', $gerais->getCell('B3')->getValue());
        $this->assertSame('ESCOLA MUNICIPAL TESTE', $gerais->getCell('B4')->getValue());
        $this->assertSame('12345678', $gerais->getCell('D4')->getValue());
        $this->assertSame('Anos Infantis, Anos Iniciais', $gerais->getCell('C4')->getValue());
        $this->assertSame('Sim', $gerais->getCell('N4')->getValue());
        $this->assertSame(8, $gerais->getCell('Z4')->getValue());
        $this->assertSame(40, $gerais->getCell('AA4')->getValue());
        $this->assertSame(48, $gerais->getCell('AE4')->getValue());

        $iniciais = $planilha->getSheetByName('Anos_Iniciais');
        $this->assertSame(2, $iniciais->getCell('C4')->getValue());
        $this->assertSame(40, $iniciais->getCell('I4')->getValue());
        $this->assertSame(40, $iniciais->getCell('N4')->getValue());

        $jogos = $planilha->getSheetByName('Anos_Iniciais_Jogos');
        $this->assertSame(6, $jogos->getCell('O4')->getValue());

        $resumo = $planilha->getSheetByName('Resumo_Jogos');
        $this->assertSame(6, $resumo->getCell('D4')->getValue());
        $this->assertSame(40, $resumo->getCell('H4')->getValue());

        $institucionais = $planilha->getSheetByName('Dados_Institucionais');
        $this->assertSame('SECRETARIA MUNICIPAL DE EDUCAÇÃO', $institucionais->getCell('B2')->getValue());
        $this->assertSame('João Secretário', $institucionais->getCell('B12')->getValue());

        $planilha->disconnectWorksheets();
        @unlink($caminho);
    }
}
