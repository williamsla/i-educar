<?php

namespace App\Services\InstitutoBrasilSolidario;

/**
 * Faixas da planilha modelo (abas de jogos). O teto de 80% das turmas
 * reproduz a fórmula de Bons Negócios / Pic$ City dos anos finais.
 */
final class KitCalculator
{
    /**
     * @var array<int, array{0: int, 1: int}>
     */
    private const PIQUENIQUE_INICIAIS = [
        [1, 3],
        [21, 6],
        [100, 10],
        [200, 12],
        [300, 18],
        [500, 24],
        [700, 30],
        [900, 36],
    ];

    /**
     * @var array<int, array{0: int, 1: int}>
     */
    private const PIQUENIQUE_FINAIS = [
        [1, 3],
        [21, 6],
    ];

    /**
     * @var array<int, array{0: int, 1: int}>
     */
    private const BONS_NEGOCIOS_E_CITY = [
        [1, 3],
        [21, 4],
        [100, 6],
        [200, 6],
        [300, 9],
        [500, 12],
        [700, 15],
        [900, 18],
    ];

    /**
     * @var array<int, array{0: int, 1: int}>
     */
    private const EJA_BN_CITY = [
        [1, 1],
        [21, 2],
    ];

    public static function piqueniqueIniciais(int $alunos): int
    {
        return self::faixa($alunos, self::PIQUENIQUE_INICIAIS);
    }

    public static function piqueniqueFinais(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::PIQUENIQUE_FINAIS);
    }

    public static function bonsNegocios(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::BONS_NEGOCIOS_E_CITY);
    }

    public static function picCity(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::BONS_NEGOCIOS_E_CITY);
    }

    public static function piqueniqueEja(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::PIQUENIQUE_INICIAIS);
    }

    public static function bonsNegociosEja(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::EJA_BN_CITY);
    }

    public static function picCityEja(int $alunos, int $turmas): int
    {
        return self::comTeto($alunos, $turmas, self::EJA_BN_CITY);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $faixas
     */
    private static function faixa(int $alunos, array $faixas): int
    {
        if ($alunos <= 0) {
            return 0;
        }

        $kits = 0;

        foreach ($faixas as [$minimo, $quantidade]) {
            if ($alunos >= $minimo) {
                $kits = $quantidade;
            }
        }

        return $kits;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $faixas
     */
    private static function comTeto(int $alunos, int $turmas, array $faixas): int
    {
        $kits = self::faixa($alunos, $faixas);

        if ($kits === 0 || $turmas <= 0) {
            return $kits;
        }

        return min($kits, (int) ceil($turmas * 0.8));
    }
}
