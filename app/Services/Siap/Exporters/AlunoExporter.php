<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapAddressHelper;
use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class AlunoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'Aluno';
    }

    public function export(): string
    {
        $builder = $this->builder();

        // Cadastro de alunos: todos com matrícula/enturmação ativa no ano
        // (não restringe ao mês de referência — o mês vale para remessa/faltas).
        $alunos = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'a.ref_idpes')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->leftJoin('modules.educacenso_cod_aluno as inep', 'inep.cod_aluno', '=', 'a.cod_aluno')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('m.ano', $this->ano)
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('a.ativo', 1)
            ->select(
                'a.cod_aluno',
                'inep.cod_aluno_inep as aluno_inep',
                'f.cpf',
                'p.nome',
                'f.data_nasc',
                'mae.nome as nome_mae',
                'pai.nome as nome_pai',
                'f.sexo',
                'fr.ref_cod_raca as cor_raca',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                'a.tipo_transporte',
                'a.veiculo_transporte_escolar',
                'f.idpes'
            )
            ->distinct()
            ->orderBy('a.cod_aluno')
            ->get();

        // Um registro por aluno (pode haver várias enturmações no ano)
        $alunos = $alunos->unique('cod_aluno')->values();

        $deficiencias = DB::table('cadastro.fisica_deficiencia')
            ->select('ref_idpes')
            ->distinct()
            ->pluck('ref_idpes')
            ->flip();

        $ceps = SiapAddressHelper::carregarCeps($alunos->pluck('idpes')->unique()->filter()->all());

        $exportados = 0;
        $omitidosCpf = 0;
        $semInep = 0;

        foreach ($alunos as $aluno) {
            $temInep = !empty($aluno->aluno_inep);
            $identificacao = $temInep ? (string) $aluno->aluno_inep : '';
            if (!$temInep) {
                $semInep++;
            }

            $cpf = SiapCodeMappers::cpf($aluno->cpf);
            if ($cpf === '') {
                $omitidosCpf++;
                continue;
            }

            $temDeficiencia = isset($deficiencias[$aluno->idpes]);
            $transporte = ((int) ($aluno->tipo_transporte ?? 0) > 0)
                || !empty($aluno->veiculo_transporte_escolar);

            $builder->addRecord('Aluno', [
                'Identificacao' => $identificacao,
                'CPF' => $cpf,
                'Nome' => mb_substr((string) $aluno->nome, 0, 255),
                'DataNascimento' => $aluno->data_nasc ? date('Y-m-d', strtotime($aluno->data_nasc)) : '1900-01-01',
                'NomeMae' => mb_substr((string) ($aluno->nome_mae ?: 'NÃO INFORMADO'), 0, 255),
                'NomePai' => mb_substr((string) ($aluno->nome_pai ?: 'NÃO INFORMADO'), 0, 255),
                'Sexo' => SiapCodeMappers::sexo($aluno->sexo),
                'CorRaca' => SiapCodeMappers::corRaca($aluno->cor_raca),
                'NecessitaEducacaoEspecial' => SiapCodeMappers::simNao($temDeficiencia),
                'CEP' => SiapCodeMappers::cep($ceps[$aluno->idpes] ?? null),
                'ZonaResidencia' => SiapCodeMappers::localizacao($aluno->zona_localizacao_censo),
                'LocalizacaoDiferenciada' => SiapCodeMappers::localizacaoDiferenciada($aluno->localizacao_diferenciada),
                'TransporteEscolarPublico' => SiapCodeMappers::simNao($transporte),
            ]);
            $exportados++;
        }

        $this->alert("Alunos candidatos (matrícula ativa no ano): {$alunos->count()}.");
        $this->alert("Alunos exportados: {$exportados}.");
        if ($omitidosCpf > 0) {
            $this->alert("Alunos omitidos por CPF ausente/inválido: {$omitidosCpf}.");
        }
        if ($semInep > 0) {
            $this->alert("Alunos exportados sem INEP (Identificacao em branco): {$semInep}.");
        }

        return $builder->toFormattedXml();
    }
}
