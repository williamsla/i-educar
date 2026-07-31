<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class TurmaProfissionalExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'TurmaProfissional';
    }

    public function export(): string
    {
        $builder = $this->builder();

        $vinculos = DB::table('modules.professor_turma as pt')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'pt.servidor_id')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 'pt.servidor_id')
            ->where('pt.ano', $this->ano)
            ->where('t.ativo', 1)
            ->whereNotNull('f.cpf')
            ->select(
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula'
            )
            ->distinct()
            ->get();

        // Fallback: quadro de horários quando não houver professor_turma
        if ($vinculos->isEmpty()) {
            $vinculos = DB::table('pmieducar.quadro_horario as qh')
                ->join('pmieducar.quadro_horario_horarios as qhh', 'qhh.ref_cod_quadro_horario', '=', 'qh.cod_quadro_horario')
                ->join('pmieducar.turma as t', 't.cod_turma', '=', 'qh.ref_cod_turma')
                ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
                ->join('cadastro.fisica as f', 'f.idpes', '=', 'qhh.ref_cod_servidor')
                ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 'qhh.ref_cod_servidor')
                ->where('t.ano', $this->ano)
                ->where('t.ativo', 1)
                ->where('qh.ativo', 1)
                ->whereNotNull('f.cpf')
                ->select(
                    't.cod_turma',
                    'inep.cod_escola_inep as inep',
                    'f.cpf',
                    'func.matricula'
                )
                ->distinct()
                ->get();
        }

        $ja = [];
        foreach ($vinculos as $vinculo) {
            $cpf = SiapCodeMappers::cpf($vinculo->cpf);
            if ($cpf === '') {
                continue;
            }
            $chave = $vinculo->cod_turma . '|' . $cpf;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $builder->addRecord('TurmaProfissional', [
                'CodigoTurma' => (string) $vinculo->cod_turma,
                'INEP' => (string) $vinculo->inep,
                'CPF' => $cpf,
                'Matricula' => (string) ($vinculo->matricula ?? ''),
            ]);
        }

        return $builder->toFormattedXml();
    }
}
