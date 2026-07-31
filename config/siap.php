<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Código do município / UG no SIAP TCE-AL
    |--------------------------------------------------------------------------
    | Identificador exigido no cabeçalho de todos os XMLs (ex.: 628).
    */
    'codigo' => env('SIAP_CODIGO', env('SIAP_CODIGO_MUNICIPIO', '000')),

    /*
    |--------------------------------------------------------------------------
    | Defaults para campos sem cadastro no i-Educar
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // 1=Sim entregue, 2=Não entregue, 3=Não se aplica
        'kit_escolar' => env('SIAP_KIT_ESCOLAR', '2'),
        'data_entrega_kit_escolar' => env('SIAP_DATA_ENTREGA_KIT', ''),
        // Data usada quando não há histórico de compra de equipamentos
        'data_ultima_compra_equipamento' => env('SIAP_DATA_ULTIMA_COMPRA', null),
        // Código de cardápio padrão quando merenda não resolve vínculo
        'codigo_cardapio' => env('SIAP_CODIGO_CARDAPIO', '1'),
        'carga_horaria_turma' => env('SIAP_CARGA_HORARIA_TURMA', '20'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa tipo_despesa (módulo despesas-escolar) → código SIAP
    |--------------------------------------------------------------------------
    | No exemplo real do município: 11 (alimentação) e 12 (demais) predominam.
    */
    'tipo_despesa' => [
        'ALIMENTAÇÃO' => '11',
        'ALIMENTACAO' => '11',
        'BENS' => '12',
        'SERVIÇOS' => '12',
        'SERVICOS' => '12',
        'IMOVEIS' => '3',
        'IMÓVEIS' => '3',
        'LOCAÇÃO BENS' => '5',
        'LOCACAO BENS' => '5',
        'OUTROS' => '12',
    ],

    /*
    |--------------------------------------------------------------------------
    | Arquivos gerados na remessa
    |--------------------------------------------------------------------------
    */
    'arquivos' => [
        'Escola',
        'EstruturaEscolar',
        'EquipamentoEscola',
        'Aluno',
        'Matricula',
        'Turma',
        'TurmaAluno',
        'ProfissionalEducacao',
        'VinculoProfissionalEducacao',
        'TurmaProfissional',
        'CapacitacaoProfissionalEducacao',
        'FaltasProfissionalEducacao',
        'Cardapio',
        'ResponsavelTecnico',
        'AtividadesResponsavelTecnico',
        'AgriculturaFamiliar',
        'ConselhoAlimentacaoEscolar',
        'EventoAlimentacaoEscolar',
        'DespesaPorEscola',
    ],
];
