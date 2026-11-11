<?php

namespace App\Services\InstitutoBrasilSolidario;

final class EtapaBuckets
{
    public const CRECHE = 'creche';

    public const PRE = 'pre';

    public const AI1 = 'ai1';

    public const AI2 = 'ai2';

    public const AI3 = 'ai3';

    public const AI4 = 'ai4';

    public const AI5 = 'ai5';

    public const AF6 = 'af6';

    public const AF7 = 'af7';

    public const AF8 = 'af8';

    public const AF9 = 'af9';

    public const EM1 = 'em1';

    public const EM2 = 'em2';

    public const EM3 = 'em3';

    public const EM4 = 'em4';

    public const EJA_AI = 'eja_ai';

    public const EJA_AF = 'eja_af';

    public const EJA_EM = 'eja_em';

    /**
     * Etapas multisseriadas: a turma só entra no ano em que houver aluno com etapa específica.
     *
     * @var array<int, true>
     */
    private const MULTISSERIADAS = [
        3 => true,
        22 => true,
        23 => true,
        24 => true,
        56 => true,
        72 => true,
    ];

    /**
     * @var array<int, string>
     */
    private const POR_CODIGO = [
        1 => self::CRECHE,
        2 => self::PRE,
        14 => self::AI1,
        15 => self::AI2,
        16 => self::AI3,
        17 => self::AI4,
        18 => self::AI5,
        19 => self::AF6,
        20 => self::AF7,
        21 => self::AF8,
        41 => self::AF9,
        25 => self::EM1,
        30 => self::EM1,
        35 => self::EM1,
        26 => self::EM2,
        31 => self::EM2,
        36 => self::EM2,
        27 => self::EM3,
        29 => self::EM3,
        32 => self::EM3,
        34 => self::EM3,
        37 => self::EM3,
        28 => self::EM4,
        33 => self::EM4,
        38 => self::EM4,
        69 => self::EJA_AI,
        70 => self::EJA_AF,
        71 => self::EJA_EM,
        74 => self::EJA_EM,
        67 => self::EJA_EM,
    ];

    /**
     * @var array<string, string>
     */
    public const GRUPO = [
        self::CRECHE => 'ei',
        self::PRE => 'ei',
        self::AI1 => 'ai',
        self::AI2 => 'ai',
        self::AI3 => 'ai',
        self::AI4 => 'ai',
        self::AI5 => 'ai',
        self::AF6 => 'af',
        self::AF7 => 'af',
        self::AF8 => 'af',
        self::AF9 => 'af',
        self::EM1 => 'em',
        self::EM2 => 'em',
        self::EM3 => 'em',
        self::EM4 => 'em',
        self::EJA_AI => 'eja',
        self::EJA_AF => 'eja',
        self::EJA_EM => 'eja',
    ];

    /**
     * @return array<string, int>
     */
    public static function zeros(): array
    {
        $zeros = [];

        foreach (array_keys(self::GRUPO) as $bucket) {
            $zeros[$bucket] = 0;
        }

        return $zeros;
    }

    public static function isMultisseriada(?int $etapa): bool
    {
        return $etapa !== null && isset(self::MULTISSERIADAS[$etapa]);
    }

    public static function fromCodigo(?int $etapa): ?string
    {
        if ($etapa === null || self::isMultisseriada($etapa)) {
            return null;
        }

        return self::POR_CODIGO[$etapa] ?? null;
    }

    public static function fromSerie(?string $nome): ?string
    {
        $nome = mb_strtoupper(trim((string) $nome));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome;

        if ($nome === '') {
            return null;
        }

        if (str_contains($ascii, 'EJA')) {
            if (str_contains($ascii, 'MEDIO')) {
                return self::EJA_EM;
            }

            if (str_contains($ascii, 'FINAIS') || preg_match('/(?:^|\D)[6-9]\D{0,4}ANO/u', $ascii)) {
                return self::EJA_AF;
            }

            return self::EJA_AI;
        }

        if (str_contains($ascii, 'CRECHE')) {
            return self::CRECHE;
        }

        if (preg_match('/PRE.?ESCOLA/', $ascii)) {
            return self::PRE;
        }

        if (preg_match('/(?:^|\D)([1-9])\D{0,4}ANO/u', $ascii, $ano)) {
            return self::anoFundamental((int) $ano[1]);
        }

        if (preg_match('/(?:^|\D)([1-4])\D{0,4}SERIE/u', $ascii, $serie)) {
            return match ((int) $serie[1]) {
                1 => self::EM1,
                2 => self::EM2,
                3 => self::EM3,
                default => self::EM4,
            };
        }

        return null;
    }

    public static function resolver(?int $etapaAluno, ?int $etapaTurma, ?string $serie): ?string
    {
        return self::fromCodigo($etapaAluno)
            ?? self::fromCodigo($etapaTurma)
            ?? self::fromSerie($serie);
    }

    /**
     * Coluna da planilha a partir do grupo escolhido pelo usuário.
     * A etapa do curso (1ª, 2ª...) define o ano. O código do Educacenso só entra
     * quando aponta para o mesmo grupo.
     */
    public static function colunaDoCurso(string $grupo, int $etapaCurso, ?int $etapaEducacenso): ?string
    {
        $porCodigo = self::fromCodigo($etapaEducacenso);

        if ($porCodigo !== null && self::grupoDaColuna($porCodigo) === $grupo) {
            return $porCodigo;
        }

        $etapaCurso = max(1, $etapaCurso);

        return match ($grupo) {
            'ei' => $etapaCurso === 1 ? self::CRECHE : self::PRE,
            'ai' => self::anoFundamental(min($etapaCurso, 9)),
            'af' => self::anoFinal($etapaCurso),
            'em' => [1 => self::EM1, 2 => self::EM2, 3 => self::EM3][$etapaCurso] ?? self::EM4,
            'eja_ai' => self::EJA_AI,
            'eja_af' => self::EJA_AF,
            'eja_em' => self::EJA_EM,
            default => null,
        };
    }

    public static function grupoDaColuna(string $bucket): ?string
    {
        return match ($bucket) {
            self::CRECHE, self::PRE => 'ei',
            self::AI1, self::AI2, self::AI3, self::AI4, self::AI5 => 'ai',
            self::AF6, self::AF7, self::AF8, self::AF9 => 'af',
            self::EM1, self::EM2, self::EM3, self::EM4 => 'em',
            self::EJA_AI => 'eja_ai',
            self::EJA_AF => 'eja_af',
            self::EJA_EM => 'eja_em',
            default => null,
        };
    }

    private static function anoFinal(int $etapaCurso): string
    {
        if ($etapaCurso >= 6) {
            return self::anoFundamental(min($etapaCurso, 9)) ?? self::AF9;
        }

        return [1 => self::AF6, 2 => self::AF7, 3 => self::AF8, 4 => self::AF9][$etapaCurso] ?? self::AF9;
    }

    private static function anoFundamental(int $ano): ?string
    {
        return [
            1 => self::AI1,
            2 => self::AI2,
            3 => self::AI3,
            4 => self::AI4,
            5 => self::AI5,
            6 => self::AF6,
            7 => self::AF7,
            8 => self::AF8,
            9 => self::AF9,
        ][$ano] ?? null;
    }
}
