<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

/**
 * Matrícula SIAP é agregada por escola + etapa + modalidade (não individual).
 */
class MatriculaExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'Matricula';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $inicio = $this->inicioMes();
        $fim = $this->fimMes();

        $turmas = DB::table('pmieducar.turma as t')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->leftJoin('pmieducar.turma_turno as tt', 'tt.id', '=', 't.turma_turno_id')
            ->where('t.ano', $this->ano)
            ->where('t.ativo', 1)
            ->select(
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                't.etapa_educacenso',
                'c.modalidade_curso',
                'tt.nome as turno_nome'
            )
            ->get();

        $agregado = [];

        foreach ($turmas as $turma) {
            $etapa = SiapCodeMappers::etapa($turma->etapa_educacenso ? (int) $turma->etapa_educacenso : null);
            $modalidade = SiapCodeMappers::modalidade($turma->modalidade_curso ? (int) $turma->modalidade_curso : null);
            $chave = $turma->inep . '|' . $etapa . '|' . $modalidade;

            if (!isset($agregado[$chave])) {
                $agregado[$chave] = [
                    'INEP' => (string) $turma->inep,
                    'Etapa' => $etapa,
                    'Modalidade' => $modalidade,
                    'turmas' => [],
                    'matriculas' => 0,
                    'integrais' => 0,
                    'docentes' => [],
                ];
            }

            $agregado[$chave]['turmas'][$turma->cod_turma] = true;

            $ehIntegral = is_string($turma->turno_nome) && stripos($turma->turno_nome, 'integral') !== false;

            $totalMatriculas = (int) DB::table('pmieducar.matricula as m')
                ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
                ->where('mt.ref_cod_turma', $turma->cod_turma)
                ->where('m.ano', $this->ano)
                ->where('m.ativo', 1)
                ->where('mt.ativo', 1)
                ->where(function ($q) use ($fim) {
                    $q->whereNull('mt.data_enturmacao')->orWhereDate('mt.data_enturmacao', '<=', $fim);
                })
                ->where(function ($q) use ($inicio) {
                    $q->whereNull('mt.data_exclusao')->orWhereDate('mt.data_exclusao', '>=', $inicio);
                })
                ->distinct('m.cod_matricula')
                ->count('m.cod_matricula');

            $agregado[$chave]['matriculas'] += $totalMatriculas;
            if ($ehIntegral) {
                $agregado[$chave]['integrais'] += $totalMatriculas;
            }

            $docentes = DB::table('modules.professor_turma as pt')
                ->where('pt.turma_id', $turma->cod_turma)
                ->where('pt.ano', $this->ano)
                ->pluck('pt.servidor_id');

            foreach ($docentes as $servidorId) {
                $agregado[$chave]['docentes'][$servidorId] = true;
            }
        }

        foreach ($agregado as $item) {
            $builder->addRecord('Matricula', [
                'INEP' => $item['INEP'],
                'Etapa' => $item['Etapa'],
                'Modalidade' => $item['Modalidade'],
                'QuantidadeMatriculas' => (string) $item['matriculas'],
                'QuantidadeMatriculasTempoIntegral' => (string) $item['integrais'],
                'QuantidadeDocentes' => (string) count($item['docentes']),
                'QuantidadeTurmas' => (string) count($item['turmas']),
            ]);
        }

        return $builder->toFormattedXml();
    }
}
