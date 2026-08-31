<?php

namespace App\Services\Siap;

use Illuminate\Support\Facades\DB;

class SiapCodeMappers
{
    /**
     * Etapa SIAP (1-4). Exige curso.siap_etapa preenchido.
     */
    public static function etapa(?int $siapEtapa = null): string
    {
        $etapas = config('siap.etapas', []);

        if ($siapEtapa !== null && isset($etapas[$siapEtapa])) {
            return (string) $siapEtapa;
        }

        throw new SiapExportValidationException(
            'É necessário preencher o mapeamento Etapa SIAP no cadastro do curso (Escola → Cursos).'
        );
    }

    /**
     * Modalidade SIAP (1-6). Exige curso.siap_modalidade preenchido.
     */
    public static function modalidade(?int $siapModalidade): string
    {
        $modalidades = config('siap.modalidades', []);

        if ($siapModalidade !== null && isset($modalidades[$siapModalidade])) {
            return (string) $siapModalidade;
        }

        throw new SiapExportValidationException(
            'É necessário preencher o mapeamento Modalidade SIAP no cadastro do curso (Escola → Cursos).'
        );
    }

    /**
     * Garante que todos os cursos das turmas ativas do ano/instituição
     * tenham siap_modalidade e siap_etapa preenchidos.
     *
     * @throws SiapExportValidationException
     */
    public static function assertCursosComModalidadeSiap(int $ano, int $instituicaoId): void
    {
        self::assertCursosComMapeamentoSiap($ano, $instituicaoId);
    }

    /**
     * @throws SiapExportValidationException
     */
    public static function assertCursosComMapeamentoSiap(int $ano, int $instituicaoId): void
    {
        $modalidadesValidas = array_keys(config('siap.modalidades', []));
        $etapasValidas = array_keys(config('siap.etapas', []));

        $cursos = DB::table('pmieducar.turma as t')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->where('t.ano', $ano)
            ->where('t.ativo', 1)
            ->where('e.ref_cod_instituicao', $instituicaoId)
            ->where(function ($q) use ($modalidadesValidas, $etapasValidas) {
                $q->whereNull('c.siap_modalidade')
                    ->orWhereNull('c.siap_etapa');

                if (!empty($modalidadesValidas)) {
                    $q->orWhereNotIn('c.siap_modalidade', $modalidadesValidas);
                }
                if (!empty($etapasValidas)) {
                    $q->orWhereNotIn('c.siap_etapa', $etapasValidas);
                }
            })
            ->select('c.cod_curso', 'c.nm_curso', 'c.siap_modalidade', 'c.siap_etapa')
            ->distinct()
            ->orderBy('c.nm_curso')
            ->get();

        if ($cursos->isEmpty()) {
            return;
        }

        $lista = $cursos
            ->map(function ($c) use ($modalidadesValidas, $etapasValidas) {
                $faltas = [];
                if ($c->siap_modalidade === null || !in_array((int) $c->siap_modalidade, $modalidadesValidas, true)) {
                    $faltas[] = 'Modalidade SIAP';
                }
                if ($c->siap_etapa === null || !in_array((int) $c->siap_etapa, $etapasValidas, true)) {
                    $faltas[] = 'Etapa SIAP';
                }

                return trim((string) $c->nm_curso)
                    . ' (código ' . $c->cod_curso . ')'
                    . (empty($faltas) ? '' : ' — falta: ' . implode(' e ', $faltas));
            })
            ->implode('; ');

        throw new SiapExportValidationException(
            'Preencha o mapeamento SIAP nos cursos antes de exportar: ' . $lista
        );
    }

    /**
     * @return array<int|string, string>
     */
    public static function opcoesModalidade(): array
    {
        $opcoes = ['' => 'Selecione'];
        foreach (config('siap.modalidades', []) as $codigo => $nome) {
            $opcoes[(string) $codigo] = $codigo . ' - ' . $nome;
        }

        return $opcoes;
    }

    public static function labelModalidade(?int $codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        $nome = config('siap.modalidades.' . $codigo);

        return $nome ? $codigo . ' - ' . $nome : (string) $codigo;
    }

    /**
     * @return array<int|string, string>
     */
    public static function opcoesEtapa(): array
    {
        $opcoes = ['' => 'Selecione'];
        foreach (config('siap.etapas', []) as $codigo => $nome) {
            $opcoes[(string) $codigo] = $codigo . ' - ' . $nome;
        }

        return $opcoes;
    }

    public static function labelEtapa(?int $codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        $nome = config('siap.etapas.' . $codigo);

        return $nome ? $codigo . ' - ' . $nome : (string) $codigo;
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

    /**
     * Carga horária semanal da turma (SIAP).
     * Dias: conta dias_semana; se vazio, assume 5.
     * Horas/dia: 8 se integral (4), 3 se noturno (3), senão 4.
     */
    public static function cargaHorariaTurma($diasSemana, ?int $turmaTurnoId): string
    {
        $dias = self::pgArray($diasSemana);
        $quantidadeDias = count($dias) > 0 ? count($dias) : 5;
        $turno = (int) $turmaTurnoId;
        $horasPorDia = match ($turno) {
            4 => 8, // Integral
            3 => 3, // Noturno
            default => 4,
        };
        $carga = max(1, min(99, $quantidadeDias * $horasPorDia));

        return (string) $carga;
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
        $digits = self::apenasDigitos($cpf);
        // Vazio, só zeros ou inválido → não exportar
        if ($digits === '' || preg_match('/^0+$/', $digits)) {
            return '';
        }

        // CPF no banco costuma perder zeros à esquerda (armazenado como numérico).
        // SIAP exige exatamente 11 dígitos.
        return str_pad(substr($digits, -11), 11, '0', STR_PAD_LEFT);
    }

    public static function cpfValido(?string $cpf): bool
    {
        return strlen(self::cpf($cpf)) === 11;
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

    public static function funcao(
        ?int $siapFuncao = null,
        ?int $funcaoExercida = null,
        bool $ehProfessor = true
    ): string {
        $funcoes = config('siap.funcoes', []);

        if ($siapFuncao !== null && isset($funcoes[$siapFuncao])) {
            return (string) $siapFuncao;
        }

        $mapaEducacenso = config('siap.funcao_exercida_para_siap', []);
        if ($funcaoExercida !== null && isset($mapaEducacenso[$funcaoExercida])) {
            return (string) $mapaEducacenso[$funcaoExercida];
        }

        // Fallback: docente → 2; demais → 11 (Serviços Administrativos)
        return $ehProfessor ? '2' : '11';
    }

    /**
     * @return array<int|string, string>
     */
    public static function opcoesFuncao(): array
    {
        $opcoes = ['' => 'Selecione'];
        foreach (config('siap.funcoes', []) as $codigo => $nome) {
            $opcoes[(string) $codigo] = $codigo . ' - ' . $nome;
        }

        return $opcoes;
    }

    public static function labelFuncao(?int $codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        $nome = config('siap.funcoes.' . $codigo);

        return $nome ? $codigo . ' - ' . $nome : (string) $codigo;
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
