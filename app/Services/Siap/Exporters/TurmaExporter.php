<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use iEducar\Modules\Educacenso\Model\TipoAtendimentoTurma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TurmaExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'Turma';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $cardapioDefault = (string) config('siap.defaults.codigo_cardapio', '1');
        $cardapios = $this->carregarCardapios();

        $query = DB::table('pmieducar.turma as t')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->where('t.ano', $this->ano)
            ->where('t.ativo', 1)
            ->select(
                't.cod_turma',
                't.ref_ref_cod_escola as cod_escola',
                't.ref_ref_cod_serie',
                't.ref_ref_cod_serie_mult',
                'inep.cod_escola_inep as inep',
                't.etapa_educacenso',
                'c.modalidade_curso',
                't.turma_turno_id',
                't.dias_semana',
                't.tipo_atendimento',
                't.ano'
            );

        $this->aplicarFiltroInstituicao($query);

        $turmas = $query->get();
        $turmaRegularPorAee = $this->carregarTurmasRegularesDosAee($turmas);

        foreach ($turmas as $turma) {
            $periodo = $this->periodoLetivo((int) $turma->cod_turma, (int) $turma->cod_escola);
            $carga = SiapCodeMappers::cargaHorariaTurma(
                $turma->dias_semana,
                $turma->turma_turno_id ? (int) $turma->turma_turno_id : null
            );
            $turmaParaCardapio = $turma;
            if ($this->turmaEhAee($turma->tipo_atendimento ?? null)) {
                $turmaParaCardapio = $turmaRegularPorAee[(int) $turma->cod_turma] ?? $turma;
            }
            $codigoCardapio = $this->resolverCodigoCardapio($turmaParaCardapio, $cardapios, $cardapioDefault);

            $builder->addRecord('Turma', [
                'Codigo' => (string) $turma->cod_turma,
                'INEP' => (string) $turma->inep,
                'Etapa' => SiapCodeMappers::etapa($turma->etapa_educacenso ? (int) $turma->etapa_educacenso : null),
                'Modalidade' => SiapCodeMappers::modalidade($turma->modalidade_curso ? (int) $turma->modalidade_curso : null),
                'Turno' => SiapCodeMappers::turno($turma->turma_turno_id ? (int) $turma->turma_turno_id : null),
                'CargaHoraria' => substr($carga, 0, 2),
                'CodigoCardapio' => (string) $codigoCardapio,
                'DataInicioAnoLetivo' => $periodo['inicio'],
                'DataFimAnoLetivo' => $periodo['fim'],
                'ReferenciaAnoLetivo' => (string) $turma->ano,
            ]);
        }

        return $builder->toFormattedXml();
    }

    private function periodoLetivo(int $codTurma, int $codEscola): array
    {
        $turmaModulo = DB::table('pmieducar.turma_modulo')
            ->where('ref_cod_turma', $codTurma)
            ->selectRaw('MIN(data_inicio) as inicio, MAX(data_fim) as fim')
            ->first();

        if ($turmaModulo && $turmaModulo->inicio && $turmaModulo->fim) {
            return [
                'inicio' => date('Y-m-d', strtotime($turmaModulo->inicio)),
                'fim' => date('Y-m-d', strtotime($turmaModulo->fim)),
            ];
        }

        $anoModulo = DB::table('pmieducar.ano_letivo_modulo')
            ->where('ref_ano', $this->ano)
            ->where('ref_ref_cod_escola', $codEscola)
            ->selectRaw('MIN(data_inicio) as inicio, MAX(data_fim) as fim')
            ->first();

        return [
            'inicio' => $anoModulo && $anoModulo->inicio
                ? date('Y-m-d', strtotime($anoModulo->inicio))
                : sprintf('%04d-02-01', $this->ano),
            'fim' => $anoModulo && $anoModulo->fim
                ? date('Y-m-d', strtotime($anoModulo->fim))
                : sprintf('%04d-12-20', $this->ano),
        ];
    }

    /**
     * Cardápios ativos do período para cruzar com a série da turma.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function carregarCardapios()
    {
        if (!Schema::hasTable('merenda_cardapios')) {
            return collect();
        }

        $escolasIds = $this->idsEscolasInstituicao();
        $colunas = ['id', 'ref_cod_escola', 'ref_cod_serie'];
        foreach (['codigo', 'series_ids', 'turnos', 'turno'] as $coluna) {
            if (Schema::hasColumn('merenda_cardapios', $coluna)) {
                $colunas[] = $coluna;
            }
        }

        return DB::table('merenda_cardapios')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($escolasIds) {
                $q->whereNull('ref_cod_escola')
                    ->orWhere('ref_cod_escola', 0)
                    ->orWhereIn('ref_cod_escola', $escolasIds);
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('ano_referencia', $this->ano)
                        ->where('mes_referencia', $this->mes);
                })->orWhere(function ($q2) {
                    $inicio = $this->inicioMes();
                    $fim = $this->fimMes();
                    $q2->whereDate('data_inicio', '<=', $fim)
                        ->whereDate('data_fim', '>=', $inicio);
                });
            })
            ->where(function ($q) {
                $q->where('status', 'ativo')->orWhereNull('status');
            })
            ->orderBy('id')
            ->get($colunas);
    }

    /**
     * Turma AEE herda o cardápio da turma regular de um aluno enturmado nela.
     * Prefere matrícula regular na mesma escola; se ninguém tiver regular, resolve pela própria AEE.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $turmas
     * @return array<int, object>
     */
    private function carregarTurmasRegularesDosAee($turmas): array
    {
        $codTurmasAee = $turmas
            ->filter(fn ($turma) => $this->turmaEhAee($turma->tipo_atendimento ?? null))
            ->map(fn ($turma) => (int) $turma->cod_turma)
            ->unique()
            ->values()
            ->all();

        if ($codTurmasAee === []) {
            return [];
        }

        $registros = DB::table('pmieducar.matricula_turma as mt_aee')
            ->join('pmieducar.matricula as m_aee', 'm_aee.cod_matricula', '=', 'mt_aee.ref_cod_matricula')
            ->join('pmieducar.turma as t_aee', 't_aee.cod_turma', '=', 'mt_aee.ref_cod_turma')
            ->join('pmieducar.matricula as m_reg', function ($join) {
                $join->on('m_reg.ref_cod_aluno', '=', 'm_aee.ref_cod_aluno')
                    ->whereColumn('m_reg.ano', 'm_aee.ano');
            })
            ->join('pmieducar.matricula_turma as mt_reg', 'mt_reg.ref_cod_matricula', '=', 'm_reg.cod_matricula')
            ->join('pmieducar.turma as t_reg', 't_reg.cod_turma', '=', 'mt_reg.ref_cod_turma')
            ->whereIn('mt_aee.ref_cod_turma', $codTurmasAee)
            ->where('mt_aee.ativo', 1)
            ->where('m_aee.ativo', 1)
            ->where('m_aee.ano', $this->ano)
            ->where('mt_reg.ativo', 1)
            ->where('m_reg.ativo', 1)
            ->where('t_reg.ativo', 1)
            ->whereColumn('t_reg.cod_turma', '<>', 't_aee.cod_turma')
            ->whereRaw('NOT (? = ANY(COALESCE(t_reg.tipo_atendimento, ARRAY[]::integer[])))', [TipoAtendimentoTurma::AEE])
            ->whereRaw('NOT (? = ANY(COALESCE(t_reg.tipo_atendimento, ARRAY[]::integer[])))', [TipoAtendimentoTurma::ATIVIDADE_COMPLEMENTAR])
            ->selectRaw(
                'DISTINCT ON (mt_aee.ref_cod_turma)
                    mt_aee.ref_cod_turma as turma_aee,
                    t_reg.cod_turma,
                    t_reg.ref_ref_cod_escola as cod_escola,
                    t_reg.ref_ref_cod_serie,
                    t_reg.ref_ref_cod_serie_mult,
                    t_reg.turma_turno_id'
            )
            ->orderBy('mt_aee.ref_cod_turma')
            ->orderByRaw('CASE WHEN t_reg.ref_ref_cod_escola = t_aee.ref_ref_cod_escola THEN 0 ELSE 1 END')
            ->orderBy('t_reg.cod_turma')
            ->get();

        $mapa = [];
        foreach ($registros as $registro) {
            $mapa[(int) $registro->turma_aee] = $registro;
        }

        return $mapa;
    }

    private function turmaEhAee(mixed $tipoAtendimento): bool
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

        return in_array(TipoAtendimentoTurma::AEE, $valores, true);
    }

    /**
     * CodigoCardapio da turma:
     * 1) filtra cardápios pelo grupo de turno (integral vs parcial);
     * 2) nesse conjunto, escolhe o que contém a série da turma.
     */
    private function resolverCodigoCardapio(object $turma, $cardapios, string $default): string
    {
        if ($cardapios->isEmpty()) {
            return $default;
        }

        $seriesTurma = array_values(array_unique(array_filter([
            (int) ($turma->ref_ref_cod_serie ?? 0),
            (int) ($turma->ref_ref_cod_serie_mult ?? 0),
        ])));
        $escolaId = (int) $turma->cod_escola;
        $turnosAceitos = $this->turnosAceitosDaTurma((int) ($turma->turma_turno_id ?? 0));

        $melhor = null;
        $melhorScore = -1;

        foreach ($cardapios as $cardapio) {
            $escolaCardapio = (int) ($cardapio->ref_cod_escola ?? 0);
            if ($escolaCardapio > 0 && $escolaCardapio !== $escolaId) {
                continue;
            }

            if ($turnosAceitos !== [] && ! $this->cardapioTemAlgumTurno($cardapio, $turnosAceitos)) {
                continue;
            }

            $seriesCardapio = $this->seriesDoCardapio($cardapio);
            $serieEspecifica = $seriesCardapio !== [] && $seriesTurma !== []
                && count(array_intersect($seriesCardapio, $seriesTurma)) > 0;

            if ($seriesCardapio !== [] && ! $serieEspecifica) {
                continue;
            }

            $score = 0;
            if ($serieEspecifica) {
                $score += 100;
            }
            if ($escolaCardapio === $escolaId) {
                $score += 20;
            } else {
                $score += 5;
            }

            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $cardapio;
            }
        }

        if ($melhor === null) {
            return $default;
        }

        return (string) (($melhor->codigo ?? null) ?: $melhor->id);
    }

    /**
     * @return array<int, int>
     */
    private function seriesDoCardapio(object $cardapio): array
    {
        $ids = $cardapio->series_ids ?? null;
        if (is_string($ids) && $ids !== '') {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : null;
        }

        if (is_array($ids) && $ids !== []) {
            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        }

        if (! empty($cardapio->ref_cod_serie)) {
            return [(int) $cardapio->ref_cod_serie];
        }

        return [];
    }

    /**
     * Turma integral → só cardápios integral.
     * Turma parcial (matutino/vespertino/noturno) → cardápios desses turnos.
     *
     * @return array<int, string>
     */
    private function turnosAceitosDaTurma(int $turmaTurnoId): array
    {
        return match ($turmaTurnoId) {
            4 => ['integral'],
            1, 2, 3 => ['matutino', 'vespertino', 'noturno'],
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $turnosAceitos
     */
    private function cardapioTemAlgumTurno(object $cardapio, array $turnosAceitos): bool
    {
        $turnos = $this->turnosDoCardapio($cardapio);
        if ($turnos === []) {
            return false;
        }

        return count(array_intersect($turnos, $turnosAceitos)) > 0;
    }

    /**
     * @return array<int, string>
     */
    private function turnosDoCardapio(object $cardapio): array
    {
        $turnos = $cardapio->turnos ?? null;
        if (is_string($turnos) && $turnos !== '') {
            $decoded = json_decode($turnos, true);
            $turnos = is_array($decoded) ? $decoded : null;
        }

        if (is_array($turnos) && $turnos !== []) {
            return array_values(array_unique(array_map(
                fn ($t) => strtolower(trim((string) $t)),
                $turnos
            )));
        }

        $turnoSingular = strtolower(trim((string) ($cardapio->turno ?? '')));

        return $turnoSingular !== '' ? [$turnoSingular] : [];
    }
}
