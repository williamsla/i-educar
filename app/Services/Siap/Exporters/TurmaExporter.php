<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
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
        $cargaDefault = (string) config('siap.defaults.carga_horaria_turma', '20');
        $cardapioDefault = (string) config('siap.defaults.codigo_cardapio', '1');
        $cardapiosPorEscola = $this->resolverCardapiosPorEscola();

        $query = DB::table('pmieducar.turma as t')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->where('t.ano', $this->ano)
            ->where('t.ativo', 1)
            ->select(
                't.cod_turma',
                't.ref_ref_cod_escola as cod_escola',
                'inep.cod_escola_inep as inep',
                't.etapa_educacenso',
                'c.modalidade_curso',
                't.turma_turno_id',
                't.hora_inicial',
                't.hora_final',
                't.ano'
            );

        $this->aplicarFiltroInstituicao($query);

        $turmas = $query->get();

        foreach ($turmas as $turma) {
            $periodo = $this->periodoLetivo((int) $turma->cod_turma, (int) $turma->cod_escola);
            $carga = $this->calcularCargaHoraria($turma->hora_inicial, $turma->hora_final, $cargaDefault);
            $codigoCardapio = $cardapiosPorEscola[(int) $turma->cod_escola]
                ?? $cardapiosPorEscola[0]
                ?? $cardapioDefault;

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

    private function calcularCargaHoraria($horaInicial, $horaFinal, string $default): string
    {
        if (!$horaInicial || !$horaFinal) {
            return $default;
        }

        try {
            $ini = strtotime($horaInicial);
            $fim = strtotime($horaFinal);
            if ($fim <= $ini) {
                return $default;
            }
            $horasDia = (int) round(($fim - $ini) / 3600);
            $semanal = max(1, min(99, $horasDia * 5));

            return (string) $semanal;
        } catch (\Throwable $e) {
            return $default;
        }
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
     * @return array<int, string> cod_escola => codigo_cardapio (0 = município)
     */
    private function resolverCardapiosPorEscola(): array
    {
        $mapa = [];

        if (!Schema::hasTable('merenda_cardapios')) {
            return $mapa;
        }

        $escolasIds = $this->idsEscolasInstituicao();

        $cardapios = DB::table('merenda_cardapios')
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
            ->get(['id', 'ref_cod_escola']);

        foreach ($cardapios as $cardapio) {
            $escolaId = (int) ($cardapio->ref_cod_escola ?? 0);
            if ($escolaId > 0 && !isset($mapa[$escolaId])) {
                $mapa[$escolaId] = (string) $cardapio->id;
            } elseif ($escolaId === 0 && !isset($mapa[0])) {
                $mapa[0] = (string) $cardapio->id;
            }
        }

        return $mapa;
    }
}
