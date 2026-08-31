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
        // Carga horária da turma passa a ser calculada por dias da semana × horas do turno
        // (integral=8h/dia, noturno=3h/dia, demais=4h/dia; dias vazios=5). Mantido só por compatibilidade.
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
    | Etapas do leiaute Turma / Matricula (Manual SIAP 2026)
    |--------------------------------------------------------------------------
    */
    'etapas' => [
        1 => 'Educação Infantil',
        2 => 'Ensino Fundamental',
        3 => 'Ensino Médio',
        4 => 'Ensino Superior',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modalidades do leiaute Turma / Matricula (Manual SIAP 2026)
    |--------------------------------------------------------------------------
    */
    'modalidades' => [
        1 => 'Educação Regular',
        2 => 'Educação Escolar Indígena',
        3 => 'Educação Especial',
        4 => 'Educação Escolar Quilombola',
        5 => 'Educação de Jovens e Adultos (EJA)',
        6 => 'Educação Profissional',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modalidade Educacenso (curso.modalidade_curso) → SIAP
    |--------------------------------------------------------------------------
    | Educacenso: 1 Regular, 2 Especial, 3 EJA, 4 Profissional
    */
    'modalidade_curso_para_siap' => [
        1 => 1,
        2 => 3,
        3 => 5,
        4 => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Funções do leiaute VinculoProfissionalEducacao (Manual SIAP 2026)
    |--------------------------------------------------------------------------
    */
    'funcoes' => [
        1 => 'Dirigente/Diretor da Escola',
        2 => 'Docente',
        3 => 'Tradutor e Intérprete de Libras',
        4 => 'Guia-Intérprete',
        5 => 'Auxiliar / Assistente Educacional',
        6 => 'Docente Titular - coordenador(a) de tutoria (EaD)',
        7 => 'Profissional de apoio escolar para alunos com deficiência',
        8 => 'Profissional/Monitor de Atividade Complementar',
        9 => 'Docente tutor - auxiliar (EaD)',
        10 => 'Instrutor de educação profissional',
        11 => 'Serviços Administrativos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Educacenso funcao_exercida → código SIAP Funcao
    |--------------------------------------------------------------------------
    */
    'funcao_exercida_para_siap' => [
        1 => 2,  // Docente
        2 => 5,  // Auxiliar/Assistente educacional
        3 => 8,  // Monitor de atividade complementar
        4 => 3,  // Tradutor Intérprete de LIBRAS
        5 => 6,  // Docente titular EAD
        6 => 9,  // Docente tutor EAD
        7 => 4,  // Guia-Intérprete
        8 => 7,  // Apoio escolar (deficiência)
        9 => 10, // Instrutor da Educação Profissional
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
