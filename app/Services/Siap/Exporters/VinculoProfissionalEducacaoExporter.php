<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class VinculoProfissionalEducacaoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'VinculoProfissionalEducacao';
    }

    public function export(): string
    {
        $builder = $this->builder();

        $vinculos = DB::table('pmieducar.servidor_alocacao as sa')
            ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'sa.ref_cod_escola')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'sa.ref_cod_escola')
            ->leftJoin('pmieducar.servidor_funcao as sf', 'sf.cod_servidor_funcao', '=', 'sa.ref_cod_servidor_funcao')
            ->leftJoin('pmieducar.funcao as fn', 'fn.cod_funcao', '=', 'sf.ref_cod_funcao')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 's.cod_servidor')
            ->where('sa.ativo', 1)
            ->where('s.ativo', 1)
            ->where('sa.ano', $this->ano)
            ->where('e.ref_cod_instituicao', $this->instituicaoId)
            ->whereNotNull('f.cpf')
            ->select(
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula',
                'sa.carga_horaria',
                's.carga_horaria as carga_servidor',
                'fn.professor',
                'fn.siap_funcao',
                'sa.data_admissao',
                's.cod_servidor',
                'sa.ref_cod_escola'
            )
            ->distinct()
            ->get();

        $funcoesProfessor = DB::table('modules.professor_turma')
            ->where('ano', $this->ano)
            ->select('servidor_id', 'funcao_exercida', 'tipo_vinculo')
            ->get()
            ->groupBy('servidor_id');

        $jaExportados = [];

        foreach ($vinculos as $vinculo) {
            $cpf = SiapCodeMappers::cpf($vinculo->cpf);
            if ($cpf === '') {
                continue;
            }

            $chave = $vinculo->inep . '|' . $cpf;
            if (isset($jaExportados[$chave])) {
                continue;
            }
            $jaExportados[$chave] = true;

            $profTurma = $funcoesProfessor->get($vinculo->cod_servidor)?->first();
            $funcao = SiapCodeMappers::funcao(
                $vinculo->siap_funcao !== null ? (int) $vinculo->siap_funcao : null,
                isset($profTurma->funcao_exercida) ? (int) $profTurma->funcao_exercida : null,
                (bool) ($vinculo->professor ?? $profTurma)
            );
            $tipoVinculo = SiapCodeMappers::tipoVinculo($profTurma->tipo_vinculo ?? 1);
            $carga = (int) ($vinculo->carga_horaria ?: $vinculo->carga_servidor ?: 20);
            $dataInicio = $vinculo->data_admissao
                ? date('Y-m-d', strtotime($vinculo->data_admissao))
                : sprintf('%04d-01-01', $this->ano);

            $builder->addRecord('VinculoProfissionalEducacao', [
                'INEP' => (string) $vinculo->inep,
                'CPF' => $cpf,
                'Matricula' => (string) ($vinculo->matricula ?? ''),
                'CargaHoraria' => (string) min(99, max(1, $carga)),
                'Funcao' => $funcao,
                'TipoVinculo' => $tipoVinculo,
                'DataInicio' => $dataInicio,
            ]);
        }

        return $builder->toFormattedXml();
    }
}
