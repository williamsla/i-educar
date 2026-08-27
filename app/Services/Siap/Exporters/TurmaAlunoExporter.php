<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
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

        // Enturmações do ano; Identificacao = INEP do aluno (quando existir).
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
            ->select(
                'a.cod_aluno',
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'ain.cod_aluno_inep as identificacao',
                'f.cpf'
            )
            ->distinct()
            ->get();

        $exportados = 0;
        $omitidosCpf = 0;
        $ja = [];

        foreach ($vinculos as $vinculo) {
            $cpf = SiapCodeMappers::cpf($vinculo->cpf);
            if ($cpf === '') {
                $omitidosCpf++;
                continue;
            }

            $chave = $vinculo->cod_turma . '|' . $vinculo->identificacao;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $builder->addRecord('TurmaAluno', [
                'CodigoTurma' => (string) $vinculo->cod_turma,
                'INEP' => (string) $vinculo->inep,
                'Identificacao' => (string) $vinculo->identificacao,
            ]);
            $exportados++;
        }

        $this->alert("TurmaAluno exportados: {$exportados}.");
        if ($omitidosCpf > 0) {
            $this->alert("TurmaAluno omitidos por CPF inválido do aluno: {$omitidosCpf}.");
        }

        return $builder->toFormattedXml();
    }
}
