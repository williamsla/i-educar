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

    private const NOMES_AREA = [
        1 => 'Linguagens',
        2 => 'Matemática',
        3 => 'Ciências da Natureza',
        4 => 'Ciências Humanas e Sociais',
        99 => 'Outras Áreas',
    ];

    /**
     * Códigos de componente previstos no domínio do SGP.
     */
    private const COMPONENTES_SGP = [
        1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16, 17, 23, 25, 26, 27, 28, 29, 30, 31, 32, 33,
    ];

    private const NOMES_COMPONENTE = [
        1 => 'Química',
        2 => 'Física',
        3 => 'Matemática',
        4 => 'Biologia',
        5 => 'Ciências',
        6 => 'Língua/Literatura Portuguesa',
        7 => 'Língua/Literatura Estrangeira - Inglês',
        8 => 'Língua/Literatura Estrangeira - Espanhol',
        9 => 'Língua/Literatura Estrangeira - outra',
        10 => 'Arte (Educação Artística, Teatro, Dança, Música, Artes Plásticas e outras)',
        11 => 'Educação Física',
        12 => 'História',
        13 => 'Geografia',
        14 => 'Filosofia',
        16 => 'Informática/Computação',
        17 => 'Áreas do conhecimento profissionalizantes',
        23 => 'Libras',
        25 => 'Áreas do conhecimento pedagógicas',
        26 => 'Ensino religioso',
        27 => 'Língua Indígena',
        28 => 'Estudos Sociais',
        29 => 'Sociologia',
        30 => 'Língua/Literatura Estrangeira - Francês',
        31 => 'Língua Portuguesa como Segunda Língua',
        32 => 'Estágio curricular supervisionado',
        33 => 'Projeto de vida',
        99 => 'Outros componentes curriculares',
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

        return in_array($codigo, self::COMPONENTES_SGP, true) ? (string) $codigo : '99';
    }

    public static function nomeAreaConhecimento(string $codigo, ?string $nomeInstituicao = null): string
    {
        $nome = trim((string) $nomeInstituicao);

        if ($nome !== '') {
            return mb_substr($nome, 0, 255);
        }

        return self::NOMES_AREA[(int) $codigo] ?? '';
    }

    public static function nomeComponenteCurricular(string $codigo, ?string $nomeInstituicao = null): string
    {
        $nome = trim((string) $nomeInstituicao);

        if ($nome !== '') {
            return mb_substr($nome, 0, 255);
        }

        return self::NOMES_COMPONENTE[(int) $codigo] ?? '';
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

    public static function cpfOnzeDigitos($cpf): string
    {
        $digitos = self::cpf($cpf);

        return $digitos === '' ? '' : str_pad($digitos, 11, '0', STR_PAD_LEFT);
    }

    public static function rg($rg): string
    {
        return substr(self::apenasDigitos((string) $rg), 0, 9);
    }

    /**
     * Planilha MEC: 0 não informado, 1 brasileira, 2 brasileira nascida no exterior/naturalizada, 3 estrangeira.
     */
    public static function nacionalidadeMec(?int $nacionalidade): string
    {
        $nacionalidade = (int) $nacionalidade;

        return in_array($nacionalidade, [1, 2, 3], true) ? (string) $nacionalidade : '0';
    }

    /**
     * ISO 3166-1 numérico com 3 dígitos (076 = Brasil).
     */
    public static function paisIso($codigo, ?int $nacionalidade = null): string
    {
        $codigo = self::apenasDigitos((string) $codigo);

        if ($codigo === '' && (int) $nacionalidade !== 3) {
            $codigo = '76';
        }

        if ($codigo === '') {
            return '0';
        }

        return str_pad(substr($codigo, 0, 3), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Educacenso/cadastro.escolaridade → nível da planilha MEC (0-11).
     */
    public static function nivelEscolaridadeMec(?int $escolaridade, ?string $tipoPos = null): string
    {
        if (in_array($tipoPos, ['7', '6', '5'], true)) {
            return match ($tipoPos) {
                '7' => '10',
                '6' => '9',
                default => '8',
            };
        }

        return match ((int) $escolaridade) {
            1 => '1',
            2 => '2',
            3, 4, 5, 7 => '4',
            6 => '7',
            default => '0',
        };
    }

    /**
     * Educacenso → códigos da planilha MEC de deficiência.
     */
    public static function deficienciaMec(iterable $codigosEducacenso): string
    {
        $mapa = [
            1 => '3',
            2 => '1',
            3 => '5',
            4 => '4',
            5 => '6',
            6 => '7',
            7 => '8',
            8 => '2',
            13 => '10',
            25 => '9',
        ];

        $convertidos = [];

        foreach ($codigosEducacenso as $codigo) {
            $mec = $mapa[(int) $codigo] ?? null;
            if ($mec !== null) {
                $convertidos[$mec] = $mec;
            }
        }

        if ($convertidos === []) {
            return '0';
        }

        ksort($convertidos);

        return implode(';', $convertidos);
    }

    public static function tipoFormacaoAcademicaGrau($grau): string
    {
        return match ((int) $grau) {
            1 => '1',
            2 => '2',
            3 => '3',
            default => '',
        };
    }

    public static function tipoFormacaoAcademicaPos($tipo): string
    {
        return match ((int) $tipo) {
            1 => '5',
            2 => '6',
            3 => '7',
            default => '',
        };
    }

    public static function naturezaInstituicao($dependenciaAdministrativa): string
    {
        $dependencia = (int) $dependenciaAdministrativa;

        if (in_array($dependencia, [1, 2, 3], true)) {
            return '1';
        }

        if ($dependencia === 4) {
            return '2';
        }

        return '';
    }

    /**
     * Função exercida i-Educar/Educacenso → CO_FUNCAO da planilha MEC.
     */
    public static function funcaoMec(?int $funcaoExercida, bool $ehProfessor = false, ?string $nomeFuncao = null): string
    {
        $mapa = [
            1 => '1',
            2 => '4',
            3 => '7',
            4 => '2',
            5 => '5',
            6 => '8',
            7 => '3',
            8 => '6',
            9 => '9',
        ];

        if (isset($mapa[(int) $funcaoExercida])) {
            return $mapa[(int) $funcaoExercida];
        }

        $nome = mb_strtolower(trim((string) $nomeFuncao));

        if ($nome !== '') {
            if (str_contains($nome, 'vice') && str_contains($nome, 'diretor')) {
                return '16';
            }
            if (str_contains($nome, 'diretor')) {
                return '10';
            }
            if (str_contains($nome, 'coordenador')) {
                return '11';
            }
            if (str_contains($nome, 'secretár') || str_contains($nome, 'secretario')) {
                return '12';
            }
            if (str_contains($nome, 'merende')) {
                return '15';
            }
            if (str_contains($nome, 'gestor')) {
                return '14';
            }
        }

        return $ehProfessor ? '1' : '99';
    }

    /**
     * Cargo do gestor escolar (school_managers.role_id) → CO_FUNCAO.
     * 1 Diretor, 2 Outro cargo (coordenador pedagógico por padrão).
     */
    public static function funcaoMecDeCargoGestor(?int $roleId): ?string
    {
        return match ((int) $roleId) {
            1 => '10',
            2 => '11',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $nomesFuncoes
     * @return list<string>
     */
    public static function codigosFuncaoVinculo(
        array $nomesFuncoes,
        ?int $gestorRoleId,
        ?int $funcaoTurma,
        ?string $nomeFuncaoAlocacao,
        bool $ehProfessor
    ): array {
        $codigos = [];
        $gestao = ['10', '11', '12', '14', '15', '16', '17'];

        foreach ($nomesFuncoes as $nome) {
            $codigo = self::funcaoMec(null, false, $nome);
            if (in_array($codigo, $gestao, true)) {
                $codigos[$codigo] = $codigo;
            }
        }

        $cargoGestor = self::funcaoMecDeCargoGestor($gestorRoleId);
        if ($cargoGestor === '10') {
            $codigos['10'] = '10';
        } elseif ($cargoGestor === '11' && !isset($codigos['10']) && !isset($codigos['11']) && !isset($codigos['16'])) {
            $codigos['11'] = '11';
        }

        if ($funcaoTurma !== null) {
            $codigoTurma = self::funcaoMec($funcaoTurma, true);
            $codigos[$codigoTurma] = $codigoTurma;
        }

        if ($nomeFuncaoAlocacao !== null && $nomeFuncaoAlocacao !== '') {
            $codigoAlocacao = self::funcaoMec(null, $ehProfessor, $nomeFuncaoAlocacao);
            $codigos[$codigoAlocacao] = $codigoAlocacao;
        } elseif ($funcaoTurma === null && $codigos === []) {
            $codigos[$ehProfessor ? '1' : '99'] = $ehProfessor ? '1' : '99';
        }

        if ($codigos === []) {
            $codigos['99'] = '99';
        }

        return array_values($codigos);
    }

    public static function perfilVinculoMec(string $coFuncao): string
    {
        return $coFuncao === '10' ? '1' : '0';
    }

    /**
     * Tipo de vínculo: professor_turma (1-4) ou portal.funcionario_vinculo.
     */
    public static function tipoVinculoMec($tipoProfessorTurma, $codFuncionarioVinculo = null): string
    {
        $tipo = (int) $tipoProfessorTurma;

        if ($tipo >= 1 && $tipo <= 4) {
            return (string) $tipo;
        }

        return match ((int) $codFuncionarioVinculo) {
            3 => '1',
            4 => '2',
            5 => '5',
            default => '0',
        };
    }

    public static function cargaHorariaSemanal($cargaAlocacao, $cargaServidor = null): string
    {
        $horas = self::horasDeCarga($cargaAlocacao);

        if ($horas === '') {
            $horas = self::horasDeCarga($cargaServidor);
        }

        return $horas;
    }

    public static function areaConhecimentoVinculo(?int $codigoEducacenso): string
    {
        $area = (int) self::areaConhecimento($codigoEducacenso);

        return $area === 99 ? '5' : (string) $area;
    }

    private static function horasDeCarga($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_numeric($valor)) {
            return (string) (int) round((float) $valor);
        }

        $texto = trim((string) $valor);

        if (preg_match('/^(\d+)/', $texto, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
