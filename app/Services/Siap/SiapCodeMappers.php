<?php

namespace App\Services\Siap;

class SiapCodeMappers
{
    /**
     * Etapa SIAP: 1=Infantil, 2=Fundamental, 3=Médio, 4=EJA/Outros
     */
    public static function etapa(?int $etapaEducacenso): string
    {
        if (!$etapaEducacenso) {
            return '2';
        }

        if (in_array($etapaEducacenso, [1, 2, 3], true)) {
            return '1';
        }

        $fundamental = array_merge(range(14, 24), [41, 56, 69, 70]);
        if (in_array($etapaEducacenso, $fundamental, true)) {
            return '2';
        }

        $medio = array_merge(range(25, 38), [71, 72, 73, 74]);
        if (in_array($etapaEducacenso, $medio, true)) {
            return '3';
        }

        return '4';
    }

    /**
     * Modalidade Educacenso 1-4 → SIAP 1-6 (extras reservados).
     */
    public static function modalidade(?int $modalidadeCurso): string
    {
        $modalidade = (int) $modalidadeCurso;

        if ($modalidade >= 1 && $modalidade <= 6) {
            return (string) $modalidade;
        }

        return '1';
    }

    /**
     * Turno: mantém 1-4; fallback manhã.
     */
    public static function turno(?int $turnoId): string
    {
        $turno = (int) $turnoId;

        if ($turno >= 1 && $turno <= 4) {
            return (string) $turno;
        }

        return '1';
    }

    public static function localizacao(?int $zona): string
    {
        return ((int) $zona) === 2 ? '2' : '1';
    }

    public static function localizacaoDiferenciada(?int $valor): string
    {
        $valor = (int) $valor;

        return in_array($valor, [0, 1, 2, 3], true) ? (string) $valor : '0';
    }

    public static function situacaoEscola(?int $situacao): string
    {
        $situacao = (int) $situacao;

        return in_array($situacao, [1, 2, 3, 4], true) ? (string) $situacao : '1';
    }

    public static function parceriaPoderPublico($valor): string
    {
        if (is_array($valor) && count($valor) > 0) {
            return '1';
        }

        return ((int) $valor) > 0 ? '1' : '2';
    }

    public static function sexo(?string $sexo): string
    {
        $sexo = strtoupper(trim((string) $sexo));

        if ($sexo === 'M' || $sexo === 'F') {
            return $sexo;
        }

        return 'O';
    }

    public static function corRaca(?int $raca): string
    {
        $raca = (int) $raca;

        return ($raca >= 1 && $raca <= 6) ? (string) $raca : '6';
    }

    public static function simNao(bool $condicao): string
    {
        return $condicao ? '1' : '2';
    }

    public static function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    public static function cpf(?string $cpf): string
    {
        return substr(self::apenasDigitos($cpf), 0, 11);
    }

    public static function cep(?string $cep): string
    {
        $cep = self::apenasDigitos($cep);

        return $cep !== '' ? substr($cep, 0, 8) : '00000000';
    }

    /**
     * Mapeia dependências Educacenso → códigos SIAP de estrutura escolar.
     * Alinhado ao padrão do Censo Escolar / layouts TCE.
     */
    public static function estruturasDaEscola(object $escola): array
    {
        $codigos = [];

        $mapSalasGerais = [1 => 1, 2 => 2, 3 => 3, 4 => 10, 5 => 22];
        $mapLabs = [1 => 4, 2 => 5, 3 => 23, 4 => 24];
        $mapAtividades = [1 => 11, 6 => 6, 7 => 13];
        $mapExternas = [1 => 7, 2 => 8, 5 => 12];
        $mapFuncionais = [1 => 9, 2 => 19, 3 => 20, 4 => 21];
        $mapBanheiros = [1 => 14, 3 => 18, 4 => 17, 5 => 15];

        foreach (self::pgArray($escola->salas_gerais ?? null) as $v) {
            if (isset($mapSalasGerais[$v])) {
                $codigos[] = $mapSalasGerais[$v];
            }
        }
        foreach (self::pgArray($escola->laboratorios ?? null) as $v) {
            if (isset($mapLabs[$v])) {
                $codigos[] = $mapLabs[$v];
            }
        }
        foreach (self::pgArray($escola->salas_atividades ?? null) as $v) {
            if (isset($mapAtividades[$v])) {
                $codigos[] = $mapAtividades[$v];
            }
        }
        foreach (self::pgArray($escola->areas_externas ?? null) as $v) {
            if (isset($mapExternas[$v])) {
                $codigos[] = $mapExternas[$v];
            }
        }
        foreach (self::pgArray($escola->salas_funcionais ?? null) as $v) {
            if (isset($mapFuncionais[$v])) {
                $codigos[] = $mapFuncionais[$v];
            }
        }
        foreach (self::pgArray($escola->banheiros ?? null) as $v) {
            if (isset($mapBanheiros[$v])) {
                $codigos[] = $mapBanheiros[$v];
            }
        }

        $codigos = array_values(array_unique(array_filter($codigos, fn ($c) => $c >= 1 && $c <= 99)));
        sort($codigos);

        return $codigos;
    }

    /**
     * Equipamentos SIAP 1-14 a partir das quantidades da escola.
     *
     * @return array<int, array{codigo:int,quantidade:int}>
     */
    public static function equipamentosDaEscola(object $escola): array
    {
        $mapa = [
            1 => (int) ($escola->televisoes ?? 0),
            2 => (int) ($escola->dvds ?? 0),
            3 => (int) ($escola->antenas_parabolicas ?? 0),
            4 => (int) ($escola->copiadoras ?? 0),
            5 => (int) ($escola->retroprojetores ?? 0),
            6 => (int) ($escola->impressoras ?? 0),
            7 => (int) ($escola->aparelhos_de_som ?? 0),
            8 => (int) ($escola->projetores_digitais ?? 0),
            9 => (int) ($escola->faxs ?? 0),
            10 => (int) ($escola->maquinas_fotograficas ?? 0),
            11 => max(
                (int) ($escola->computadores ?? 0),
                (int) ($escola->computadores_alunos ?? 0),
                (int) ($escola->quantidade_computadores_alunos_mesa ?? 0)
            ),
            12 => (int) ($escola->impressoras_multifuncionais ?? 0),
            13 => (int) ($escola->quantidade_computadores_alunos_tablets ?? 0),
            14 => (int) ($escola->lousas_digitais ?? 0),
        ];

        $resultado = [];
        foreach ($mapa as $codigo => $qtd) {
            if ($qtd > 0) {
                $resultado[] = ['codigo' => $codigo, 'quantidade' => $qtd];
            }
        }

        return $resultado;
    }

    public static function tipoDespesa(string $tipoTexto): string
    {
        $mapa = config('siap.tipo_despesa', []);
        $chave = mb_strtoupper(trim($tipoTexto));

        return $mapa[$chave] ?? '12';
    }

    public static function escolaridade(?int $idesco): string
    {
        // Educacenso escolaridade costuma ser 1-7; SIAP aceita 1-9
        $idesco = (int) $idesco;

        return ($idesco >= 1 && $idesco <= 9) ? (string) $idesco : '1';
    }

    public static function tipoEnsinoMedio($valor): string
    {
        $valor = (int) $valor;

        return ($valor >= 1 && $valor <= 4) ? (string) $valor : '';
    }

    public static function funcao(?int $funcaoExercida, bool $ehProfessor = true): string
    {
        if ($funcaoExercida >= 1 && $funcaoExercida <= 11) {
            return (string) $funcaoExercida;
        }

        return $ehProfessor ? '1' : '8';
    }

    public static function tipoVinculo(?int $tipo): string
    {
        $tipo = (int) $tipo;

        return ($tipo >= 1 && $tipo <= 10) ? (string) $tipo : '1';
    }

    /**
     * Converte array PostgreSQL "{1,2,3}" ou array PHP em lista de inteiros.
     */
    public static function pgArray($value): array
    {
        if (is_array($value)) {
            return array_map('intval', $value);
        }

        if (!is_string($value) || $value === '' || $value === '{}') {
            return [];
        }

        $value = trim($value, '{}');
        if ($value === '') {
            return [];
        }

        return array_map('intval', array_map('trim', explode(',', $value)));
    }
}
