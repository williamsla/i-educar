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

    expect($reg['222.222.222-22']['data_nascimento'])->toBe('05/08/2012');
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

    expect($reg['222.222.222-22']['data_nascimento'])->toBe('20/03/2009');
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

    expect($reg['005.336.054-07']['data_nascimento'])->toBe('20/12/2024');
});
