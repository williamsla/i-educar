<?php

namespace App\Services\Siap\Exporters;

use Illuminate\Support\Facades\DB;

class TurmaAlunoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'TurmaAluno';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $inicio = $this->inicioMes();
        $fim = $this->fimMes();

        $vinculos = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->join('modules.educacenso_cod_aluno as ain', 'ain.cod_aluno', '=', 'a.cod_aluno')
            ->where('m.ano', $this->ano)
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('t.ativo', 1)
            ->where('a.ativo', 1)
            ->whereNotNull('ain.cod_aluno_inep')
            ->whereNotNull('f.cpf')
            ->whereRaw("regexp_replace(COALESCE(f.cpf::text, ''), '[^0-9]', '', 'g') !~ '^0*$'")
            ->where(function ($q) use ($fim) {
                $q->whereNull('mt.data_enturmacao')->orWhereDate('mt.data_enturmacao', '<=', $fim);
            })
            ->where(function ($q) use ($inicio) {
                $q->whereNull('mt.data_exclusao')->orWhereDate('mt.data_exclusao', '>=', $inicio);
            })
            ->select(
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'ain.cod_aluno_inep as identificacao'
            )
            ->distinct()
            ->get();

        foreach ($vinculos as $vinculo) {
            $builder->addRecord('TurmaAluno', [
                'CodigoTurma' => (string) $vinculo->cod_turma,
                'INEP' => (string) $vinculo->inep,
                'Identificacao' => (string) $vinculo->identificacao,
            ]);
        }

        return $builder->toFormattedXml();
    }
}
