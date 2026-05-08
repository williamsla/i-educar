<?php

use App\Services\EsusPdfCpfService;
use Tests\TestCase;

uses(TestCase::class);

test('extrai data de nascimento do trecho antes do CPF quando o layout é nome data cpf', function () {
    $text = <<<'TXT'
111.111.111-11 outro
ANA BEATRIZ SOUZA
05/08/2012
222.222.222-22 Feminino 12 anos 10/06/2024 última visita
333.333.333-33
TXT;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoTexto($text);

    expect($reg['222.222.222-22']['data_nascimento'])->toBe('05/08/2012')
        ->and($reg['222.222.222-22']['endereco_relatorio'])->toBe('')
        ->and($reg['222.222.222-22']['ultima_atualizacao_cadastral'])->toBe('')
        ->and($reg['222.222.222-22']['cns'])->toBe('');
});

test('usa primeira data plausível após o CPF quando não há data antes', function () {
    $text = <<<'TXT'
111.111.111-11
JOÃO CARLOS LIMA
222.222.222-22 Feminino 15 anos 20/03/2009 Rua Teste
333.333.333-33
TXT;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoTexto($text);

    expect($reg['222.222.222-22']['data_nascimento'])->toBe('20/03/2009')
        ->and($reg['222.222.222-22']['endereco_relatorio'])->toBe('');
});

test('ignora data da coluna CDS e usa nascimento após sexo e idade', function () {
    $text = <<<'TXT'
111.111.111-11
JOSÉ FELIPE SILVA
222.222.222-22 Feminino 5 anos 10/01/2020 Rua A 21/05/2025 CDS
MARIA ELOYZE FERREIRA GOMES
005.336.054-07 Feminino 1 ano e 1 mês 20/12/2024 Rua B 03/06/2025 CDS
333.333.333-33
TXT;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoTexto($text);

    expect($reg['005.336.054-07']['data_nascimento'])->toBe('20/12/2024')
        ->and($reg['005.336.054-07']['ultima_atualizacao_cadastral'])->toBe('');
});

test('extrai registros de csv do esus com cabecalho textual', function () {
    $csv = <<<'CSV'
e-SUS - Atenção Primária;;;;;;;;;;;;;;
MINISTÉRIO DA SAÚDE;;;;;;;;;;;;;;
;;;;;;;;;;;;;;
Nome equipe;INE equipe;Microárea;Endereço;CPF/CNS;Nome;Idade;Sexo;Identidade de gênero;Data de nascimento;Telefone celular;Telefone residencial;Telefone de contato;Última atualização cadastral;Origem
ESF III - COITE DO NOIA;163635;Não informada;Sítio Areias;147.922.394-86;MIRELLA NAUANE DE OLIVEIRA GOMES;8 anos e 6 meses;Feminino;-;18/09/2017;(82) 98179-6128;-;-;14/05/2021;PEC
ESF III - COITE DO NOIA;163635;1;Sítio Areias;8,98005E+14;JUCIELY MARIA PEREIRA DA SILVA;9 anos e 8 meses;Feminino;-;07/08/2016;(82) 3526-1289;-;-;30/03/2025;CDS
CSV;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoCsv($csv);

    expect($reg)->toHaveCount(2)
        ->and($reg)->toHaveKey('147.922.394-86')
        ->and($reg)->toHaveKey('cns:898005000000000')
        ->and($reg['147.922.394-86']['nome'])->toBe('MIRELLA NAUANE DE OLIVEIRA GOMES')
        ->and($reg['147.922.394-86']['data_nascimento'])->toBe('18/09/2017')
        ->and($reg['147.922.394-86']['endereco_relatorio'])->toBe('Sítio Areias')
        ->and($reg['147.922.394-86']['ultima_atualizacao_cadastral'])->toBe('14/05/2021')
        ->and($reg['147.922.394-86']['cns'])->toBe('')
        ->and($reg['cns:898005000000000']['cpf'])->toBe('')
        ->and($reg['cns:898005000000000']['cns'])->toBe('898005000000000')
        ->and($reg['cns:898005000000000']['nome'])->toBe('JUCIELY MARIA PEREIRA DA SILVA')
        ->and($reg['cns:898005000000000']['endereco_relatorio'])->toBe('Sítio Areias');
});

test('inclui linha sem cpf cns valido quando ha nome e endereco no csv', function () {
    $csv = <<<'CSV'
Nome equipe;INE equipe;Microárea;Endereço;CPF/CNS;Nome;Idade;Sexo;Identidade de gênero;Data de nascimento;Telefone celular;Telefone residencial;Telefone de contato;Última atualização cadastral;Origem
ESF;1;1;Rua X sem numero;INVALIDO;PESSOA SEM DOC;10 anos;Feminino;-;01/01/2016;-;-;-;01/01/2020;PEC
CSV;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoCsv($csv);

    expect($reg)->toHaveCount(1);
    $primeiro = array_values($reg)[0];
    expect($primeiro['nome'])->toBe('PESSOA SEM DOC')
        ->and($primeiro['cpf'])->toBe('')
        ->and($primeiro['cns'])->toBe('')
        ->and($primeiro['endereco_relatorio'])->toContain('Rua X');
});

test('usa coluna antes de cpf/cns como endereco quando cabecalho nao tem endereco', function () {
    $csv = <<<'CSV'
Nome equipe;INE equipe;Microárea;Detalhe local;CPF/CNS;Nome;Idade;Sexo;Identidade de gênero;Data de nascimento;Telefone celular;Telefone residencial;Telefone de contato;Última atualização cadastral;Origem
ESF;1;1;Rua Fallback Antes Do Cpf;147.922.394-86;ANA;10 anos;Feminino;-;01/01/2016;-;-;-;14/05/2021;PEC
CSV;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoCsv($csv);

    expect($reg['147.922.394-86']['endereco_relatorio'])->toBe('Rua Fallback Antes Do Cpf');
});

test('formata cpf numerico no csv', function () {
    $csv = <<<'CSV'
Nome equipe;INE equipe;Microárea;Endereço;CPF/CNS;Nome;Idade;Sexo;Identidade de gênero;Data de nascimento;Telefone celular;Telefone residencial;Telefone de contato;Última atualização cadastral;Origem
ESF III;163635;1;Endereço;14792239486;NOME TESTE;10 anos;Feminino;-;01/01/2016;(82) 99999-9999;-;-;14/05/2021;PEC
CSV;

    $s = new EsusPdfCpfService;
    $reg = $s->extrairRegistrosDoCsv($csv);

    expect($reg)->toHaveKey('147.922.394-86')
        ->and($reg['147.922.394-86']['cns'])->toBe('');
});
