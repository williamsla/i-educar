<?php

namespace App\Services\InstitutoBrasilSolidario;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class InstitutoBrasilSolidarioWorkbook
{
    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     * @param  array<string, string>  $instituicao
     */
    public function salvar(string $municipio, array $instituicao, array $escolas): string
    {
        $planilha = new Spreadsheet;
        $planilha->getProperties()
            ->setTitle('Dados das Escolas')
            ->setSubject('Instituto Brasil Solidário');

        $this->dadosGerais($planilha->getActiveSheet(), $municipio, $escolas);
        $this->anosInfantis($planilha->createSheet(), $municipio, $escolas);
        $this->anosIniciais($planilha->createSheet(), $municipio, $escolas);
        $this->anosFinais($planilha->createSheet(), $municipio, $escolas);
        $this->ensinoMedio($planilha->createSheet(), $municipio, $escolas);
        $this->eja($planilha->createSheet(), $municipio, $escolas);
        $this->dadosInstitucionais($planilha->createSheet(), $instituicao);
        $this->jogosIniciais($planilha->createSheet(), $escolas);
        $this->jogosFinais($planilha->createSheet(), $escolas);
        $this->jogosMedio($planilha->createSheet(), $escolas);
        $this->jogosEja($planilha->createSheet(), $escolas);
        $this->resumoJogos($planilha->createSheet(), $municipio, $escolas);
        $this->elaboracao($planilha->createSheet(), $municipio, $escolas);

        $planilha->setActiveSheetIndex(0);

        $temporario = tempnam(sys_get_temp_dir(), 'ibs_');
        $caminho = $temporario . '.xlsx';
        @unlink($temporario);

        (new Xlsx($planilha))->save($caminho);
        $planilha->disconnectWorksheets();

        return $caminho;
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function dadosGerais(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Dados_Gerais');
        $aba->setCellValue('A1', $municipio !== '' ? $municipio : 'Município/UF');
        $aba->setCellValue('A2', 'Dados Gerais');
        $aba->setCellValue('N2', 'Alunos');
        $aba->setCellValue('T2', 'Educadores');
        $aba->setCellValue('Z2', 'Totais de alunos');

        $colunas = [
            'A' => 'Sequência',
            'B' => 'Nome da Escola',
            'C' => 'Etapas Presentes',
            'D' => 'Código INEP da Escola',
            'E' => 'Rede',
            'F' => 'Zona',
            'G' => 'Muncípio e Sigla',
            'H' => 'Redes Sociais',
            'I' => 'Nome para contato',
            'J' => 'Telefone',
            'K' => 'E-mail',
            'L' => 'Nº Docentes',
            'M' => 'Nº Funcionários',
            'N' => 'A escola possui ALUNOS com deficiências?',
            'O' => 'Alunos com Física Motora',
            'P' => 'Alunos com Física Mental  Intelectual',
            'Q' => 'Alunos com Física Psicossocial',
            'R' => 'Alunos com Física Visual',
            'S' => 'Alunos com Auditiva',
            'T' => 'A escola possui EDUCADORES com deficiências?',
            'U' => 'Educadores com Física Motora',
            'V' => 'Educadores com Física com Mental  Intelectual',
            'W' => 'Educadores com Física Psicossocial',
            'X' => 'Educadore com Física Visual',
            'Y' => 'Educadores com Auditiva',
            'Z' => 'AEI',
            'AA' => 'AAI',
            'AB' => 'AAF',
            'AC' => 'AEM',
            'AD' => 'EJA',
            'AE' => 'Total ',
            'AF' => 'Farol de Consistência',
            'AG' => 'Logs',
        ];

        $this->cabecalhos($aba, $colunas);

        $linha = 4;

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->etapasPresentes(),
                $escola->inep,
                $escola->rede,
                $escola->zona,
                $escola->municipio,
                $escola->redesSociais,
                $escola->contato,
                $escola->telefone,
                $escola->email,
                $escola->docentes,
                $escola->funcionarios,
                $escola->possuiDeficienciaAlunos(),
                $escola->defAlunos['motora'],
                $escola->defAlunos['intelectual'],
                $escola->defAlunos['psicossocial'],
                $escola->defAlunos['visual'],
                $escola->defAlunos['auditiva'],
                $escola->possuiDeficienciaEducadores(),
                $escola->defEducadores['motora'],
                $escola->defEducadores['intelectual'],
                $escola->defEducadores['psicossocial'],
                $escola->defEducadores['visual'],
                $escola->defEducadores['auditiva'],
                $escola->totalAlunos('ei'),
                $escola->totalAlunos('ai'),
                $escola->totalAlunos('af'),
                $escola->totalAlunos('em'),
                $escola->totalAlunos('eja'),
                $escola->totalGeralAlunos(),
                $escola->farol(),
                $escola->logs(),
            ], null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'AG');
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function anosInfantis(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Anos_Infantis');
        $aba->setCellValue('A1', $municipio);
        $aba->setCellValue('B2', 'Dados Gerais');
        $aba->setCellValue('C2', 'Turmas - Anos Infantis');
        $aba->setCellValue('F2', 'Alunos - Anos Infantis');
        $aba->setCellValue('I2', 'Docentes - Anos Infantis');
        $this->cabecalhos($aba, [
            'A' => 'Seq',
            'B' => 'Nome da Escola',
            'C' => 'Creche_TAEI',
            'D' => 'Pre_Escola_TAEI',
            'E' => 'TOTAL_TAEI',
            'F' => 'Creche_AEI',
            'G' => 'Pre_Escola_AEI',
            'H' => 'TOTAL_AEI',
            'I' => 'Quantidade de Educadores Anos Infantis',
        ]);
        $linha = 4;

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->turmasDe(EtapaBuckets::CRECHE),
                $escola->turmasDe(EtapaBuckets::PRE),
                $escola->totalTurmas('ei'),
                $escola->alunos(EtapaBuckets::CRECHE),
                $escola->alunos(EtapaBuckets::PRE),
                $escola->totalAlunos('ei'),
                $escola->educadores['ei'],
            ], null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'I');
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function anosIniciais(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Anos_Iniciais');
        $this->blocoFundamental($aba, $municipio, $escolas, 'Anos Iniciais', [
            EtapaBuckets::AI1,
            EtapaBuckets::AI2,
            EtapaBuckets::AI3,
            EtapaBuckets::AI4,
            EtapaBuckets::AI5,
        ], 'ai', ['1º ano', '2º ano', '3º ano', '4º ano', '5º ano'], 'TAI', 'AI', [
            'Total EI',
            'Total AF',
            'Total EM',
            'Total EJA',
        ], ['ei', 'af', 'em', 'eja']);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function anosFinais(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Anos_Finais');
        $this->blocoFundamental($aba, $municipio, $escolas, 'Anos Finais', [
            EtapaBuckets::AF6,
            EtapaBuckets::AF7,
            EtapaBuckets::AF8,
            EtapaBuckets::AF9,
        ], 'af', ['6º ano', '7º ano', '8º ano', '9º ano'], 'TAF', 'AF', [
            'Total EI',
            'Total AI',
            'Total EM',
            'Total EJA',
        ], ['ei', 'ai', 'em', 'eja']);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function ensinoMedio(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Ensino_Médio');
        $this->blocoFundamental($aba, $municipio, $escolas, 'Ensino Médio', [
            EtapaBuckets::EM1,
            EtapaBuckets::EM2,
            EtapaBuckets::EM3,
            EtapaBuckets::EM4,
        ], 'em', ['1º ano', '2º ano', '3º ano', '4º ano'], 'TEM', 'AEM', [
            'Total EI',
            'Total AI',
            'Total AF',
            'Total EJA',
        ], ['ei', 'ai', 'af', 'eja']);
    }

    /**
     * @param  array<int, string>  $buckets
     * @param  array<int, string>  $rotulos
     * @param  array<int, string>  $cruzados
     * @param  array<int, string>  $gruposCruzados
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function blocoFundamental(
        Worksheet $aba,
        string $municipio,
        array $escolas,
        string $titulo,
        array $buckets,
        string $grupo,
        array $rotulos,
        string $sufixoTurma,
        string $sufixoAluno,
        array $cruzados,
        array $gruposCruzados,
    ): void {
        $aba->setCellValue('A1', $municipio);
        $aba->setCellValue('B2', 'Dados Gerais');
        $aba->setCellValue('C2', 'Turmas - ' . $titulo);
        $quantidade = count($buckets);
        $colunaAlunos = chr(ord('C') + $quantidade + 1);
        $colunaDocentes = chr(ord($colunaAlunos) + $quantidade + 1);
        $aba->setCellValue($colunaAlunos . '2', 'Alunos - ' . $titulo);
        $aba->setCellValue($colunaDocentes . '2', 'Docentes - ' . $titulo);

        $colunas = [
            'A' => 'Seq',
            'B' => 'Nome da Escola',
        ];
        $coluna = 'C';

        foreach ($rotulos as $rotulo) {
            $colunas[$coluna] = $rotulo . '_' . $sufixoTurma;
            $coluna++;
        }

        $colunas[$coluna] = 'TOTAL_' . $sufixoTurma;
        $coluna++;

        foreach ($rotulos as $rotulo) {
            $colunas[$coluna] = $rotulo . '_' . $sufixoAluno;
            $coluna++;
        }

        $colunas[$coluna] = 'TOTAL_' . $sufixoAluno;
        $coluna++;
        $colunas[$coluna] = 'Quantidade de Educadores ' . $titulo;
        $coluna++;

        foreach ($cruzados as $rotuloCruzado) {
            $colunas[$coluna] = $rotuloCruzado;
            $coluna++;
        }

        $this->cabecalhos($aba, $colunas);

        $linha = 4;
        $ultima = 'B';

        foreach ($escolas as $escola) {
            $valores = [$escola->sequencia, $escola->nome];

            foreach ($buckets as $bucket) {
                $valores[] = $escola->turmasDe($bucket);
            }

            $valores[] = $escola->totalTurmas($grupo);

            foreach ($buckets as $bucket) {
                $valores[] = $escola->alunos($bucket);
            }

            $valores[] = $escola->totalAlunos($grupo);
            $valores[] = $escola->educadores[$grupo];

            foreach ($gruposCruzados as $grupoCruzado) {
                $valores[] = $escola->totalAlunos($grupoCruzado);
            }

            $aba->fromArray($valores, null, 'A' . $linha);
            $ultima = $this->colunaPorIndice(count($valores));
            $linha++;
        }

        $this->finalizar($aba, max(3, $linha - 1), $ultima);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function eja(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('EJA');
        $aba->setCellValue('A1', $municipio);
        $aba->setCellValue('B2', 'Dados Gerais');
        $aba->setCellValue('C2', 'Turmas - EJA');
        $aba->setCellValue('G2', 'Alunos - EJA');
        $aba->setCellValue('K2', 'Docentes - EJA');
        $this->cabecalhos($aba, [
            'A' => 'Seq',
            'B' => 'Nome da Escola',
            'C' => 'TAI',
            'D' => 'TAF',
            'E' => 'TEM',
            'F' => 'TOTAL_EJA',
            'G' => 'AAI',
            'H' => 'AAF',
            'I' => 'AEM',
            'J' => 'TOTAL_AJA',
            'K' => 'Quantidade de Educadores Eja',
        ]);

        $linha = 4;

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->turmasDe(EtapaBuckets::EJA_AI),
                $escola->turmasDe(EtapaBuckets::EJA_AF),
                $escola->turmasDe(EtapaBuckets::EJA_EM),
                $escola->totalTurmas('eja'),
                $escola->alunos(EtapaBuckets::EJA_AI),
                $escola->alunos(EtapaBuckets::EJA_AF),
                $escola->alunos(EtapaBuckets::EJA_EM),
                $escola->totalAlunos('eja'),
                $escola->educadores['eja'],
            ], null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'K');
    }

    /**
     * @param  array<string, string>  $instituicao
     */
    private function dadosInstitucionais(Worksheet $aba, array $instituicao): void
    {
        $aba->setTitle('Dados_Institucionais');
        $linhas = [
            ['DADOS_ENDEREÇO', 'DADOS_DA_SECRETARIA_DE_EDUCAÇÃO_INSTITUIÇÃO'],
            ['Razão Social', $instituicao['razao_social'] ?? ''],
            ['Endereço Rua ou Av', $instituicao['logradouro'] ?? ''],
            ['Número', $instituicao['numero'] ?? ''],
            ['Bairro', $instituicao['bairro'] ?? ''],
            ['CEP', $instituicao['cep'] ?? ''],
            ['Município', $instituicao['municipio'] ?? ''],
            ['Estado:', $instituicao['uf'] ?? ''],
            ['Horário de Funcionamento', ''],
            ['Rede Social (@ Instagram, Facebook e outros)', ''],
            ['DADOS_SECRETARIO', 'DADOS_SECRETARIO'],
            ['Nome completo', $instituicao['responsavel'] ?? ''],
            ['Telefone com DDD', $instituicao['telefone'] ?? ''],
            ['CPF', ''],
            ['E-mail', ''],
            ['Cargo', $instituicao['cargo'] ?? 'Secretário(a)'],
            ['DADOS_ENVIO', 'CAMPOS'],
            ['Nome do Representante Legal / Gestor:', $instituicao['responsavel'] ?? ''],
            ['Telefone com DDD', $instituicao['telefone'] ?? ''],
            ['E-mail:', ''],
            ['Grupo Institucional', $instituicao['razao_social'] ?? ''],
            ['Unidade Institucional', $instituicao['municipio'] ?? ''],
            ['CNPJ', ''],
            ['Projeto', 'Instituto Brasil Solidário'],
        ];

        $aba->fromArray($linhas, null, 'A1');
        $aba->getStyle('A1:B1')->getFont()->setBold(true);
        $aba->getStyle('A11:B11')->getFont()->setBold(true);
        $aba->getStyle('A17:B17')->getFont()->setBold(true);
        $aba->getColumnDimension('A')->setWidth(48);
        $aba->getColumnDimension('B')->setWidth(48);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function jogosIniciais(Worksheet $aba, array $escolas): void
    {
        $aba->setTitle('Anos_Iniciais_Jogos');
        $this->cabecalhos($aba, [
            'A' => 'Sequência',
            'B' => 'Nome da Escola',
            'C' => '1º ano_TAI',
            'D' => '2º ano_TAI',
            'E' => '3º ano_TAI',
            'F' => '4º ano_TAI',
            'G' => '5º ano_TAI',
            'H' => 'TOTAL_TAI',
            'I' => ' 1º ano_AI',
            'J' => ' 2º ano_AI',
            'K' => '3º ano_AI',
            'L' => ' 4º ano_AI',
            'M' => ' 5º ano_AI',
            'N' => 'TOTAL_AI',
            'O' => 'Piquenique',
            'P' => 'Bons Negócios',
            'Q' => 'Pic$ City',
        ]);
        $aba->setCellValue('B2', 'Dados Gerais');
        $aba->setCellValue('C2', 'Turmas - Anos Iniciais');
        $aba->setCellValue('I2', 'Alunos - Anos Iniciais');

        $linha = 4;

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->turmasDe(EtapaBuckets::AI1),
                $escola->turmasDe(EtapaBuckets::AI2),
                $escola->turmasDe(EtapaBuckets::AI3),
                $escola->turmasDe(EtapaBuckets::AI4),
                $escola->turmasDe(EtapaBuckets::AI5),
                $escola->totalTurmas('ai'),
                $escola->alunos(EtapaBuckets::AI1),
                $escola->alunos(EtapaBuckets::AI2),
                $escola->alunos(EtapaBuckets::AI3),
                $escola->alunos(EtapaBuckets::AI4),
                $escola->alunos(EtapaBuckets::AI5),
                $escola->totalAlunos('ai'),
                KitCalculator::piqueniqueIniciais($escola->totalAlunos('ai')),
                0,
                0,
            ], null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'Q');
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function jogosFinais(Worksheet $aba, array $escolas): void
    {
        $aba->setTitle('Anos_Finais_Jogos');
        $this->jogosComTeto($aba, $escolas, [
            EtapaBuckets::AF6,
            EtapaBuckets::AF7,
            EtapaBuckets::AF8,
            EtapaBuckets::AF9,
        ], ['6º ano_TAF', '7º ano_TAF', '8º ano_TAF', '9º ano_TAF'], ['6º ano_AF', '7º ano_AF', '8º ano_AF', '9º ano_AF'], 'af', false);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function jogosMedio(Worksheet $aba, array $escolas): void
    {
        $aba->setTitle('Ensino_Médio_Jogos');
        $this->jogosComTeto($aba, $escolas, [
            EtapaBuckets::EM1,
            EtapaBuckets::EM2,
            EtapaBuckets::EM3,
            EtapaBuckets::EM4,
        ], ['1º ano_TEM', '2º ano_TEM', '3º ano_TEM', '4º ano_TEM'], ['1º ano_AEM', '2º ano_AEM', '3º ano_AEM', '4º ano_AEM'], 'em', false);
    }

    /**
     * @param  array<int, string>  $buckets
     * @param  array<int, string>  $rotulosTurmas
     * @param  array<int, string>  $rotulosAlunos
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function jogosComTeto(Worksheet $aba, array $escolas, array $buckets, array $rotulosTurmas, array $rotulosAlunos, string $grupo, bool $eja): void
    {
        $colunas = ['A' => 'Sequência', 'B' => 'Nome da Escola'];
        $coluna = 'C';

        foreach ($rotulosTurmas as $rotulo) {
            $colunas[$coluna] = $rotulo;
            $coluna++;
        }

        $colunas[$coluna++] = 'TOTAL_TURMAS';

        foreach ($rotulosAlunos as $rotulo) {
            $colunas[$coluna] = $rotulo;
            $coluna++;
        }

        $colunas[$coluna++] = 'TOTAL_ALUNOS';
        $colunas[$coluna++] = 'Piquenique';
        $colunas[$coluna++] = 'Bons Negócios';
        $colunas[$coluna] = 'Pic$ City';
        $this->cabecalhos($aba, $colunas);

        $linha = 4;

        foreach ($escolas as $escola) {
            $alunos = $escola->totalAlunos($grupo);
            $turmas = $escola->totalTurmas($grupo);
            $valores = [$escola->sequencia, $escola->nome];

            foreach ($buckets as $bucket) {
                $valores[] = $escola->turmasDe($bucket);
            }

            $valores[] = $turmas;

            foreach ($buckets as $bucket) {
                $valores[] = $escola->alunos($bucket);
            }

            $valores[] = $alunos;
            $valores[] = $eja
                ? KitCalculator::piqueniqueEja($alunos, $turmas)
                : KitCalculator::piqueniqueFinais($alunos, $turmas);
            $valores[] = $eja
                ? KitCalculator::bonsNegociosEja($alunos, $turmas)
                : KitCalculator::bonsNegocios($alunos, $turmas);
            $valores[] = $eja
                ? KitCalculator::picCityEja($alunos, $turmas)
                : KitCalculator::picCity($alunos, $turmas);
            $aba->fromArray($valores, null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, $coluna);
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function jogosEja(Worksheet $aba, array $escolas): void
    {
        $aba->setTitle('EJA_Jogos');
        $this->cabecalhos($aba, [
            'A' => 'Sequência',
            'B' => 'Nome da Escola',
            'C' => 'TAI',
            'D' => 'TAF',
            'E' => 'TEM',
            'F' => 'TOTAL_EJA',
            'G' => 'AAI',
            'H' => 'AAF',
            'I' => 'AEM',
            'J' => 'TOTAL_AJA',
            'K' => 'Piquenique',
            'L' => 'Bons Negócios',
            'M' => 'Pic$ City',
        ]);
        $linha = 4;

        foreach ($escolas as $escola) {
            $alunos = $escola->totalAlunos('eja');
            $turmas = $escola->totalTurmas('eja');
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->turmasDe(EtapaBuckets::EJA_AI),
                $escola->turmasDe(EtapaBuckets::EJA_AF),
                $escola->turmasDe(EtapaBuckets::EJA_EM),
                $turmas,
                $escola->alunos(EtapaBuckets::EJA_AI),
                $escola->alunos(EtapaBuckets::EJA_AF),
                $escola->alunos(EtapaBuckets::EJA_EM),
                $alunos,
                KitCalculator::piqueniqueEja($alunos, $turmas),
                KitCalculator::bonsNegociosEja($alunos, $turmas),
                KitCalculator::picCityEja($alunos, $turmas),
            ], null, 'A' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'M');
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function resumoJogos(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Resumo_Jogos');
        $aba->setCellValue('A1', $municipio);
        $aba->setCellValue('A2', 'Dados Gerais');
        $aba->setCellValue('D2', 'Piquenique');
        $aba->setCellValue('E2', 'Bons Negócios');
        $aba->setCellValue('F2', 'Pic$ City');
        $this->cabecalhos($aba, [
            'A' => 'Sequência',
            'B' => 'Nome da Escola',
            'C' => 'INEP',
            'D' => 'TOTAL PQ',
            'E' => 'TOTAL BN',
            'F' => 'TOTAL PC',
            'H' => 'Total AAI',
            'I' => 'Total AAF',
            'J' => 'Total AEM',
            'K' => 'Total EJA',
        ]);

        $linha = 4;
        $totais = ['pq' => 0, 'bn' => 0, 'pc' => 0];

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->inep,
                $escola->piquenique(),
                $escola->bonsNegocios(),
                $escola->picCity(),
            ], null, 'A' . $linha);
            $aba->setCellValue('H' . $linha, $escola->totalAlunos('ai'));
            $aba->setCellValue('I' . $linha, $escola->totalAlunos('af'));
            $aba->setCellValue('J' . $linha, $escola->totalAlunos('em'));
            $aba->setCellValue('K' . $linha, $escola->totalAlunos('eja'));
            $totais['pq'] += $escola->piquenique();
            $totais['bn'] += $escola->bonsNegocios();
            $totais['pc'] += $escola->picCity();
            $linha++;
        }

        $aba->setCellValue('B' . $linha, 'Total');
        $aba->setCellValue('D' . $linha, $totais['pq']);
        $aba->setCellValue('E' . $linha, $totais['bn']);
        $aba->setCellValue('F' . $linha, $totais['pc']);
        $aba->getStyle('B' . $linha . ':F' . $linha)->getFont()->setBold(true);
        $this->finalizar($aba, $linha, 'K');
    }

    /**
     * @param  array<int, EscolaPlanilha>  $escolas
     */
    private function elaboracao(Worksheet $aba, string $municipio, array $escolas): void
    {
        $aba->setTitle('Elaboração');
        $aba->setCellValue('A1', $municipio);
        $aba->setCellValue('A2', 'Dados Gerais');
        $this->cabecalhos($aba, [
            'A' => 'Sequência',
            'B' => 'Nome da Escola',
            'C' => 'INEP',
            'D' => 'TOTAL PQ',
            'E' => 'TOTAL BN',
            'F' => 'TOTAL PC',
            'H' => '1º ano_MED',
            'I' => '2º ano_MED',
            'J' => '3º ano_MED',
            'K' => '4º ano_MED',
            'L' => '5º ano_MED',
            'M' => '6º ano_MED',
            'N' => '7º ano_MED',
            'O' => '8º ano_MED',
            'P' => '9º ano_MED',
        ]);

        $linha = 4;

        foreach ($escolas as $escola) {
            $aba->fromArray([
                $escola->sequencia,
                $escola->nome,
                $escola->inep,
                $escola->piquenique(),
                $escola->bonsNegocios(),
                $escola->picCity(),
            ], null, 'A' . $linha);
            $aba->fromArray([
                $escola->alunos(EtapaBuckets::AI1),
                $escola->alunos(EtapaBuckets::AI2),
                $escola->alunos(EtapaBuckets::AI3),
                $escola->alunos(EtapaBuckets::AI4),
                $escola->alunos(EtapaBuckets::AI5),
                $escola->alunos(EtapaBuckets::AF6),
                $escola->alunos(EtapaBuckets::AF7),
                $escola->alunos(EtapaBuckets::AF8),
                $escola->alunos(EtapaBuckets::AF9),
            ], null, 'H' . $linha);
            $linha++;
        }

        $this->finalizar($aba, $linha - 1, 'P');
    }

    /**
     * @param  array<string, string>  $colunas
     */
    private function cabecalhos(Worksheet $aba, array $colunas): void
    {
        foreach ($colunas as $coluna => $titulo) {
            $aba->setCellValue($coluna . '3', $titulo);
        }

        $ultima = array_key_last($colunas);
        $aba->getStyle('A3:' . $ultima . '3')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'],
            ],
            'alignment' => [
                'wrapText' => true,
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $aba->getRowDimension(3)->setRowHeight(32);
        $aba->getStyle('A2:' . $ultima . '2')->getFont()->setBold(true);
    }

    private function finalizar(Worksheet $aba, int $ultimaLinha, string $ultimaColuna): void
    {
        if ($ultimaLinha >= 3) {
            $aba->getStyle('A3:' . $ultimaColuna . $ultimaLinha)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);
            $aba->setAutoFilter('A3:' . $ultimaColuna . $ultimaLinha);
        }

        $aba->freezePane('C4');

        foreach ($this->colunasAte($ultimaColuna) as $coluna) {
            $aba->getColumnDimension($coluna)->setAutoSize(true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function colunasAte(string $ultima): array
    {
        $colunas = [];
        $atual = 'A';

        while (true) {
            $colunas[] = $atual;

            if ($atual === $ultima) {
                return $colunas;
            }

            $atual++;

            if ($atual === 'BA') {
                return $colunas;
            }
        }
    }

    private function colunaPorIndice(int $indice): string
    {
        $nome = '';

        while ($indice > 0) {
            $indice--;
            $nome = chr(65 + ($indice % 26)) . $nome;
            $indice = intdiv($indice, 26);
        }

        return $nome;
    }
}
