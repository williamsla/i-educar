<?php

class App_Model_MatriculaSituacao extends CoreExt_Enum
{
    const APROVADO = 1;

    const REPROVADO = 2;

    const EM_ANDAMENTO = 3;

    const TRANSFERIDO = 4;

    const RECLASSIFICADO = 5;

    const ABANDONO = 6;

    const EM_EXAME = 7;

    const APROVADO_APOS_EXAME = 8;

    const APROVADO_SEM_EXAME = 10;

    const PRE_MATRICULA = 11;

    const APROVADO_COM_DEPENDENCIA = 12;

    const APROVADO_PELO_CONSELHO = 13;

    const REPROVADO_POR_FALTAS = 14;

    const FALECIDO = 15;

    /**
     * Situação equivalente a {@see self::EM_ANDAMENTO} com rótulo distinto na interface.
     */
    const EM_CORRECAO_DE_FLUXO = 17;

    protected $_data = [
        self::APROVADO => 'Aprovado',
        self::REPROVADO => 'Retido',
        self::EM_ANDAMENTO => 'Cursando',
        self::TRANSFERIDO => 'Transferido',
        self::RECLASSIFICADO => 'Reclassificado',
        self::ABANDONO => 'Deixou de Frequentar',
        self::EM_EXAME => 'Em exame',
        self::APROVADO_APOS_EXAME => 'Aprovado após exame',
        self::PRE_MATRICULA => 'Pré-matrícula',
        self::APROVADO_COM_DEPENDENCIA => 'Aprovado com dependência',
        self::APROVADO_PELO_CONSELHO => 'Aprovado pelo conselho',
        self::REPROVADO_POR_FALTAS => 'Reprovado por faltas',
        self::FALECIDO => 'Falecido',
        self::EM_CORRECAO_DE_FLUXO => 'Em correção de fluxo',
    ];

    /**
     * Códigos de matrícula considerados "em andamento" (curricularmente ativos).
     *
     * @return int[]
     */
    public static function situacoesEmAndamento(): array
    {
        return [
            self::EM_ANDAMENTO,
        ];
    }

    public static function isEmAndamento($codigo): bool
    {
        return in_array((int) $codigo, self::situacoesEmAndamento(), true);
    }

    public static function getInstance()
    {
        return self::_getInstance(__CLASS__);
    }

    public static function getSituacao($id)
    {
        $instance = self::getInstance()->_data;

        return $instance[$id];
    }

    /**
     * Retorna todas as situação da matrícula consideradas "finais".
     *
     * @return array
     */
    public static function getSituacoesFinais()
    {
        return [
            self::TRANSFERIDO,
            self::ABANDONO,
            self::FALECIDO,
            self::APROVADO_COM_DEPENDENCIA,
            self::APROVADO_PELO_CONSELHO,
            self::RECLASSIFICADO,
            self::EM_CORRECAO_DE_FLUXO,
        ];
    }
}
