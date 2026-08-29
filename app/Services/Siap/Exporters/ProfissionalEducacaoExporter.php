<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapAddressHelper;
use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class ProfissionalEducacaoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'ProfissionalEducacao';
    }

    public function export(): string
    {
        $builder = $this->builder();

        $porAlocacao = $this->queryPorAlocacao()->get();
        $porTurma = $this->queryPorProfessorTurma()->get();

        $profissionais = $porAlocacao
            ->concat($porTurma)
            ->unique(fn ($prof) => (string) $prof->cod_servidor)
            ->values();

        $somenteTurma = $porTurma
            ->pluck('cod_servidor')
            ->diff($porAlocacao->pluck('cod_servidor'))
            ->count();

        $ceps = SiapAddressHelper::carregarCeps($profissionais->pluck('idpes')->unique()->filter()->all());
        $jaExportados = [];
        $exportados = 0;

        foreach ($profissionais as $prof) {
            $cpf = SiapCodeMappers::cpf($prof->cpf);
            if ($cpf === '' || isset($jaExportados[$cpf])) {
                continue;
            }
            $jaExportados[$cpf] = true;

            $builder->addRecord('ProfissionalEducacao', [
                'CPF' => $cpf,
                'Nome' => mb_substr((string) $prof->nome, 0, 255),
                'DataNascimento' => $prof->data_nasc ? date('Y-m-d', strtotime($prof->data_nasc)) : '1900-01-01',
                'NomeMae' => mb_substr((string) ($prof->nome_mae ?: 'NÃO INFORMADO'), 0, 255),
                'NomePai' => mb_substr((string) ($prof->nome_pai ?: 'NÃO INFORMADO'), 0, 255),
                'Sexo' => SiapCodeMappers::sexo($prof->sexo),
                'CorRaca' => SiapCodeMappers::corRaca($prof->cor_raca),
                'CEP' => SiapCodeMappers::cep($ceps[$prof->idpes] ?? null),
                'ZonaResidencia' => SiapCodeMappers::localizacao($prof->zona_localizacao_censo),
                'LocalizacaoDiferenciada' => SiapCodeMappers::localizacaoDiferenciada($prof->localizacao_diferenciada),
                'Escolaridade' => SiapCodeMappers::escolaridade($prof->ref_idesco ? (int) $prof->ref_idesco : null),
                'TipoEnsinoMedio' => SiapCodeMappers::tipoEnsinoMedio($prof->tipo_ensino_medio_cursado),
            ]);
            $exportados++;
        }

        $this->alert("Profissionais exportados: {$exportados}.");
        if ($somenteTurma > 0) {
            $this->alert("Incluídos via professor_turma (sem alocação ativa no ano): {$somenteTurma}.");
        }

        return $builder->toFormattedXml();
    }

    private function queryPorAlocacao()
    {
        return DB::table('pmieducar.servidor as s')
            ->join('pmieducar.servidor_alocacao as sa', 'sa.ref_cod_servidor', '=', 's.cod_servidor')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'sa.ref_cod_escola')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('s.ativo', 1)
            ->where('sa.ativo', 1)
            ->where('sa.ano', $this->ano)
            ->where('e.ref_cod_instituicao', $this->instituicaoId)
            ->whereNotNull('f.cpf')
            ->select($this->colunasProfissional())
            ->distinct();
    }

    private function queryPorProfessorTurma()
    {
        return DB::table('modules.professor_turma as pt')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'pt.servidor_id')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'pt.servidor_id')
            ->leftJoin('pmieducar.servidor as s', 's.cod_servidor', '=', 'pt.servidor_id')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('pt.ano', $this->ano)
            ->where('t.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->instituicaoId)
            ->whereNotNull('f.cpf')
            ->select([
                DB::raw('pt.servidor_id as cod_servidor'),
                'f.cpf',
                'p.nome',
                'f.data_nasc',
                'mae.nome as nome_mae',
                'pai.nome as nome_pai',
                'f.sexo',
                'fr.ref_cod_raca as cor_raca',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                's.ref_idesco',
                's.tipo_ensino_medio_cursado',
                'f.idpes',
            ])
            ->distinct();
    }

    /**
     * @return array<int, string>
     */
    private function colunasProfissional(): array
    {
        return [
            's.cod_servidor',
            'f.cpf',
            'p.nome',
            'f.data_nasc',
            'mae.nome as nome_mae',
            'pai.nome as nome_pai',
            'f.sexo',
            'fr.ref_cod_raca as cor_raca',
            'f.zona_localizacao_censo',
            'f.localizacao_diferenciada',
            's.ref_idesco',
            's.tipo_ensino_medio_cursado',
            'f.idpes',
        ];
    }
}
