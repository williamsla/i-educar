<?php

namespace Tests\Unit\Services\SgpExport;

use App\Services\SgpExport\SgpCodeMappers;
use Tests\TestCase;

class SgpCodeMappersTest extends TestCase
{
    public function test_mapeia_identificadores_numericos(): void
    {
        $this->assertSame('12345678901', SgpCodeMappers::cpf('123.456.789-01'));
        $this->assertSame('12345678000199', SgpCodeMappers::cnpj('12.345.678/0001-99'));
        $this->assertSame('57000000', SgpCodeMappers::cep('57.000-000'));
        $this->assertSame('12345678', SgpCodeMappers::inep('12345678'));
        $this->assertSame('82999998888', SgpCodeMappers::telefone('82', '99999-8888'));
        $this->assertSame('27', SgpCodeMappers::ufIbge('AL'));
        $this->assertSame('2700300', SgpCodeMappers::municipioIbge('2700300'));
    }

    public function test_formata_data_no_padrao_do_sgp(): void
    {
        $this->assertSame('01/02/2026', SgpCodeMappers::data('2026-02-01'));
        $this->assertSame('', SgpCodeMappers::data(null));
    }

    public function test_mapeia_dominios_de_escola(): void
    {
        $this->assertSame('3', SgpCodeMappers::dependenciaAdministrativa(3));
        $this->assertSame('1', SgpCodeMappers::situacaoFuncionamento(1));
        $this->assertSame('1', SgpCodeMappers::localizacaoGeografica(1));
        $this->assertSame('2', SgpCodeMappers::localizacaoGeografica(2));
        $this->assertSame('0', SgpCodeMappers::localizacaoGeografica(null));
        $this->assertSame('2', SgpCodeMappers::localizacaoDiferenciada(2));
    }

    public function test_mapeia_pessoa(): void
    {
        $this->assertSame('1', SgpCodeMappers::sexo('M'));
        $this->assertSame('2', SgpCodeMappers::sexo('F'));
        $this->assertSame('1', SgpCodeMappers::racaCor(1));
        $this->assertSame('5', SgpCodeMappers::racaCor(4));
        $this->assertSame('4', SgpCodeMappers::racaCor(5));
        $this->assertSame('0', SgpCodeMappers::racaCor(0));
        $this->assertSame('76', SgpCodeMappers::nacionalidade(1));
    }

    public function test_mapeia_componente_e_avaliacao(): void
    {
        $this->assertSame('1', SgpCodeMappers::areaConhecimento(6));
        $this->assertSame('2', SgpCodeMappers::areaConhecimento(3));
        $this->assertSame('3', SgpCodeMappers::areaConhecimento(5));
        $this->assertSame('4', SgpCodeMappers::areaConhecimento(12));
        $this->assertSame('99', SgpCodeMappers::areaConhecimento(99));
        $this->assertSame('6', SgpCodeMappers::componenteCurricular(6));
        $this->assertSame('99', SgpCodeMappers::componenteCurricular(0));
        $this->assertSame('99', SgpCodeMappers::componenteCurricular(15));
        $this->assertSame('Linguagens', SgpCodeMappers::nomeAreaConhecimento('1'));
        $this->assertSame('Linguagens e Códigos', SgpCodeMappers::nomeAreaConhecimento('1', 'Linguagens e Códigos'));
        $this->assertSame('Língua/Literatura Portuguesa', SgpCodeMappers::nomeComponenteCurricular('6'));
        $this->assertSame('Português', SgpCodeMappers::nomeComponenteCurricular('6', 'Português'));
        $this->assertSame('1', SgpCodeMappers::sistemaAvaliacao(1));
        $this->assertSame('2', SgpCodeMappers::sistemaAvaliacao(2));
        $this->assertSame('10', SgpCodeMappers::sistemaAvaliacao(3));
    }

    public function test_mapeia_turma_e_matricula(): void
    {
        $this->assertSame('1', SgpCodeMappers::turno(1));
        $this->assertSame('1', SgpCodeMappers::tipoTurma(1));
        $this->assertSame('3', SgpCodeMappers::tipoTurma('{4}'));
        $this->assertSame('4', SgpCodeMappers::tipoTurma([5]));
        $this->assertSame('2', SgpCodeMappers::tipoTurma('{1,4}'));
        $this->assertSame('10', SgpCodeMappers::situacaoMatricula(1));
        $this->assertSame('0', SgpCodeMappers::situacaoMatricula(3));
        $this->assertSame('7', SgpCodeMappers::situacaoMatricula(6));
        $this->assertSame('8', SgpCodeMappers::situacaoMatricula(15));
        $this->assertSame('14;15;16', SgpCodeMappers::etapasSeparadasPorPontoEVirgula([16, 14, 15, 14]));
    }
}
