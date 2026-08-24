<?php

namespace App\Services\SgpExport;

class SgpCodeMappers
{
    /**
     * Códigos IBGE das UFs.
     */
    private const UF_IBGE = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16', 'TO' => '17',
        'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27', 'SE' => '28', 'BA' => '29',
        'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35',
        'PR' => '41', 'SC' => '42', 'RS' => '43',
        'MS' => '50', 'MT' => '51', 'GO' => '52', 'DF' => '53',
    ];

    /**
     * Educacenso (código da disciplina) → área SGP.
     * 1 Linguagens, 2 Matemática, 3 Ciências da Natureza, 4 Ciências Humanas e Sociais, 99 Outras.
     */
    private const AREA_POR_DISCIPLINA = [
        6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1, 11 => 1, 23 => 1, 27 => 1, 30 => 1, 31 => 1,
        3 => 2,
        1 => 3, 2 => 3, 4 => 3, 5 => 3,
        12 => 4, 13 => 4, 14 => 4, 28 => 4, 29 => 4,
    ];

    public static function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    public static function cpf($cpf): string
    {
        return substr(self::apenasDigitos((string) $cpf), 0, 11);
    }

    public static function cnpj($cnpj): string
    {
        return substr(self::apenasDigitos((string) $cnpj), 0, 14);
    }

    public static function cep($cep): string
    {
        return substr(self::apenasDigitos((string) $cep), 0, 8);
    }

    public static function inep($inep): string
    {
        return substr(self::apenasDigitos((string) $inep), 0, 8);
    }

    public static function telefone(?string $ddd, ?string $fone): string
    {
        return self::apenasDigitos(($ddd ?? '') . ($fone ?? ''));
    }

    public static function data($date): string
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime((string) $date);

        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }

    public static function ufIbge(?string $sigla): string
    {
        $sigla = strtoupper(trim((string) $sigla));

        return self::UF_IBGE[$sigla] ?? '';
    }

    public static function municipioIbge($codigo): string
    {
        return substr(self::apenasDigitos((string) $codigo), 0, 7);
    }

    /**
     * SGP: 1 Federal, 2 Estadual, 3 Municipal, 4 Privada.
     */
    public static function dependenciaAdministrativa(?int $valor): string
    {
        $valor = (int) $valor;

        return in_array($valor, [1, 2, 3, 4], true) ? (string) $valor : '3';
    }

    /**
     * SGP: 1 Em funcionamento, 2 Paralisada, 3 Inativada.
     */
    public static function situacaoFuncionamento(?int $valor): string
    {
        $valor = (int) $valor;

        if (in_array($valor, [1, 2, 3], true)) {
            return (string) $valor;
        }

        return '1';
    }

    /**
     * SGP: 0 Não informado, 1 Urbana, 2 Rural.
     */
    public static function localizacaoGeografica(?int $zona): string
    {
        $zona = (int) $zona;

        if (in_array($zona, [1, 2], true)) {
            return (string) $zona;
        }

        return '0';
    }

    /**
     * SGP: 0 Não informado, 1 Não está em área diferenciada, 2 Quilombola, 3 Terra indígena, 4 Assentamento, 5 Povos tradicionais.
     */
    public static function localizacaoDiferenciada(?int $valor): string
    {
        $valor = (int) $valor;

        return in_array($valor, [0, 1, 2, 3, 4, 5], true) ? (string) $valor : '0';
    }

    /**
     * SGP: 0 Não informado, 1 Masculino, 2 Feminino, 3 Intersexo, 4 Prefere não informar.
     */
    public static function sexo(?string $sexo): string
    {
        $sexo = strtoupper(trim((string) $sexo));

        return match ($sexo) {
            'M' => '1',
            'F' => '2',
            default => '0',
        };
    }

    /**
     * i-Educar (Educacenso): 1 Branca, 2 Preta, 3 Parda, 4 Amarela, 5 Indígena, 0 Não declarada.
     * SGP: 1 Branco, 2 Preto, 3 Pardo, 4 Indígena, 5 Amarelo, 0 Não informado.
     */
    public static function racaCor(?int $racaEducacenso): string
    {
        return match ((int) $racaEducacenso) {
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '5',
            5 => '4',
            default => '0',
        };
    }

    /**
     * Nacionalidade i-Educar: 1 brasileiro, 2 naturalizado, 3 estrangeiro.
     * SGP usa ISO 3166-1 numérico (76 = Brasil).
     */
    public static function nacionalidade(?int $nacionalidade, $codigoPais = null): string
    {
        if ((int) $nacionalidade === 3 && !empty($codigoPais)) {
            $codigo = self::apenasDigitos((string) $codigoPais);

            return $codigo !== '' ? $codigo : '76';
        }

        return '76';
    }

    public static function simNaoNaoInformado($valor): string
    {
        if ($valor === true || $valor === 1 || $valor === '1') {
            return '1';
        }

        if ($valor === false || $valor === 2 || $valor === '2' || $valor === 0 || $valor === '0') {
            return '2';
        }

        return '0';
    }

    public static function areaConhecimento(?int $codigoEducacenso): string
    {
        $codigo = (int) $codigoEducacenso;

        return (string) (self::AREA_POR_DISCIPLINA[$codigo] ?? 99);
    }

    public static function componenteCurricular(?int $codigoEducacenso): string
    {
        $codigo = (int) $codigoEducacenso;

        return $codigo > 0 ? (string) $codigo : '99';
    }

    /**
     * tipo_nota i-Educar: 1 numérica, 2 conceitual, 3 sem nota.
     * SGP: 1 notas numéricas, 2 menções qualitativas, 10 não se aplica.
     */
    public static function sistemaAvaliacao(?int $tipoNota): string
    {
        return match ((int) $tipoNota) {
            2 => '2',
            3 => '10',
            default => '1',
        };
    }

    /**
     * Turno i-Educar/SGP: 1 Matutino, 2 Vespertino, 3 Noturno, 4 Integral.
     */
    public static function turno(?int $turnoId): string
    {
        $turno = (int) $turnoId;

        return ($turno >= 1 && $turno <= 4) ? (string) $turno : '0';
    }

    /**
     * tipo_atendimento da turma → TIPO_TURMA SGP.
     * 1 Curricular, 2 Curricular com AC, 3 Atividade complementar, 4 AEE.
     */
    public static function tipoTurma($tipoAtendimento): string
    {
        $valores = [];

        if (is_array($tipoAtendimento)) {
            $valores = array_map('intval', $tipoAtendimento);
        } elseif (is_string($tipoAtendimento)) {
            $limpo = trim($tipoAtendimento, '{}');
            if ($limpo !== '') {
                $valores = array_map('intval', explode(',', $limpo));
            }
        } elseif ($tipoAtendimento !== null && $tipoAtendimento !== '') {
            $valores = [(int) $tipoAtendimento];
        }

        $temEscolarizacao = in_array(1, $valores, true) || in_array(0, $valores, true) || $valores === [];
        $temAtividadeComplementar = in_array(4, $valores, true);
        $temAee = in_array(5, $valores, true);

        if ($temAee && !$temEscolarizacao && !$temAtividadeComplementar) {
            return '4';
        }

        if ($temAtividadeComplementar && $temEscolarizacao) {
            return '2';
        }

        if ($temAtividadeComplementar) {
            return '3';
        }

        return '1';
    }

    public static function modalidadeEnsino(?int $modalidadeCurso): string
    {
        $modalidade = (int) $modalidadeCurso;

        return ($modalidade >= 1 && $modalidade <= 4) ? (string) $modalidade : '1';
    }

    public static function tipoMediacao(?int $tipo): string
    {
        $tipo = (int) $tipo;

        return ($tipo >= 1 && $tipo <= 3) ? (string) $tipo : '1';
    }

    /**
     * Situação da matrícula i-Educar → SGP.
     * 0 Em andamento, 2-5 transferências, 6 evasão, 7 abandono, 8 óbito, 9 reclassificação, 10 aprovado, 12 reprovado.
     */
    public static function situacaoMatricula(?int $aprovado): string
    {
        return match ((int) $aprovado) {
            1, 8, 10, 12, 13 => '10',
            2, 14 => '12',
            3, 7, 11, 17 => '0',
            4 => '5',
            5 => '9',
            6 => '7',
            15 => '8',
            default => '0',
        };
    }

    public static function etapasSeparadasPorPontoEVirgula(iterable $etapas): string
    {
        $codigos = [];

        foreach ($etapas as $etapa) {
            $codigo = (int) $etapa;
            if ($codigo > 0) {
                $codigos[$codigo] = $codigo;
            }
        }

        ksort($codigos);

        return implode(';', $codigos);
    }
}
