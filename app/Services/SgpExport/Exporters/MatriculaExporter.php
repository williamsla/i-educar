<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class MatriculaExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_matriculas_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'ID_SGP_ESTUDANTE',
            'ESTUDANTE_CPF',
            'ESTUDANTE_NOME',
            'ESTUDANTE_NOME_SOCIAL',
            'ID_SGP_MATRICULA',
            'CO_ENTIDADE',
            'NO_ENTIDADE',
            'CO_MATRICULA_REDE',
            'TIPO_ATENDIMENTO_ESPECIALIZADO',
            'ESTUDANTE_ETAPA_DE_ENSINO',
            'NU_ANO_MATRICULA',
            'DATA_INICIO_MATRICULA',
            'ESTUDANTE_MATRICULA_INTEGRAL',
            'ESTUDANTE_ANO_PERIODO',
            'ID_SGP_TURMA',
            'ESTUDANTE_MATRICULA_SITUACAO',
            'ESTUDANTE_MATRICULA_FORMA_CONCLUSAO',
            'DATA_FIM',
            'DATA_CONCLUSAO_ENSINO_MEDIO',
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'a.ref_idpes')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'm.ref_ref_cod_escola')
            ->leftJoin('pmieducar.matricula_turma as mt', function ($join) {
                $join->on('mt.ref_cod_matricula', '=', 'm.cod_matricula')
                    ->where('mt.ativo', '=', 1);
            })
            ->leftJoin('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as pe', 'pe.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->where('m.ano', $this->ano())
            ->where('m.ativo', 1)
            ->where('a.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->where(function ($query) {
                $query->whereNull('t.cod_turma')
                    ->orWhere('t.ano', $this->ano());
            })
            ->select(
                'f.cpf',
                'p.nome',
                'f.nome_social',
                'm.cod_matricula',
                'm.aprovado',
                'm.data_matricula',
                'm.data_cancel',
                'm.ano',
                't.cod_turma',
                't.etapa_educacenso',
                't.turma_turno_id',
                'inep.cod_escola_inep as inep',
                DB::raw('COALESCE(pe.nome, j.fantasia, e.sigla) as nome_escola')
            );

        $this->applySchoolFilter($query, 'm.ref_ref_cod_escola');
        $this->applySchoolAcademicYear($query, 'm.ref_ref_cod_escola');

        $matriculas = $query->distinct()->orderBy('p.nome')->get();
        $linhas = [];

        foreach ($matriculas as $matricula) {
            $situacao = SgpCodeMappers::situacaoMatricula($matricula->aprovado ? (int) $matricula->aprovado : null);
            $dataFim = in_array($situacao, ['0', ''], true) ? '' : SgpCodeMappers::data($matricula->data_cancel);

            $linhas[] = [
                '',
                SgpCodeMappers::cpf($matricula->cpf),
                mb_substr((string) $matricula->nome, 0, 255),
                mb_substr((string) ($matricula->nome_social ?? ''), 0, 255),
                '',
                SgpCodeMappers::inep($matricula->inep),
                mb_substr((string) $matricula->nome_escola, 0, 255),
                (string) $matricula->cod_matricula,
                '',
                (string) ($matricula->etapa_educacenso ?: ''),
                (string) $matricula->ano,
                SgpCodeMappers::data($matricula->data_matricula),
                ((int) $matricula->turma_turno_id === 4) ? '1' : '2',
                '',
                (string) ($matricula->cod_turma ?: ''),
                $situacao,
                '',
                $dataFim,
                '',
            ];
        }

        return $linhas;
    }
}
