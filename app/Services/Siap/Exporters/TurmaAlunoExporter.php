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
        $query = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->join('pmieducar.escola as esc', 'esc.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->leftJoin('modules.educacenso_cod_aluno as ain', 'ain.cod_aluno', '=', 'a.cod_aluno')
            ->where('m.ano', $this->ano)
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('t.ativo', 1)
            ->where('a.ativo', 1);

        $this->aplicarFiltroInstituicao($query, 'esc');

        if ($this->somenteAlunosComInep) {
            $query->whereNotNull('ain.cod_aluno_inep');
        }

        $vinculos = $query
            ->select(
                'a.cod_aluno',
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'ain.cod_aluno_inep as aluno_inep',
                'f.cpf'
            )
            ->distinct()
            ->get();

        $exportados = 0;
        $omitidosCpf = 0;
        $semInep = 0;
        $ja = [];

        foreach ($vinculos as $vinculo) {
            $cpf = SiapCodeMappers::cpf($vinculo->cpf);
            if ($cpf === '') {
                $omitidosCpf++;
                continue;
            }

            $identificacao = !empty($vinculo->aluno_inep) ? (string) $vinculo->aluno_inep : '';
            if ($identificacao === '') {
                $semInep++;
            }

            $chave = $vinculo->cod_turma . '|' . $vinculo->cod_aluno;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $builder->addRecord('TurmaAluno', [
                'CodigoTurma' => (string) $vinculo->cod_turma,
                'INEP' => (string) $vinculo->inep,
                'IdentificacaoAluno' => $identificacao,
            ]);
            $exportados++;
        }

        $this->alert("TurmaAluno exportados: {$exportados}.");
        if ($omitidosCpf > 0) {
            $this->alert("TurmaAluno omitidos por CPF inválido do aluno: {$omitidosCpf}.");
        }
        if ($this->somenteAlunosComInep) {
            $this->alert('Filtro ativo: somente alunos com código INEP.');
        } elseif ($semInep > 0) {
            $this->alert("TurmaAluno sem INEP (Identificacao em branco): {$semInep}.");
        }

        return $builder->toFormattedXml();
    }
}
