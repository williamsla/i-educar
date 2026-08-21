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

        $profissionais = DB::table('pmieducar.servidor as s')
            ->join('pmieducar.servidor_alocacao as sa', 'sa.ref_cod_servidor', '=', 's.cod_servidor')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('s.ativo', 1)
            ->where('sa.ativo', 1)
            ->where('sa.ano', $this->ano)
            ->whereNotNull('f.cpf')
            ->select(
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
                'f.idpes'
            )
            ->distinct()
            ->get();

        $ceps = SiapAddressHelper::carregarCeps($profissionais->pluck('idpes')->unique()->filter()->all());
        $jaExportados = [];

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
        }

        return $builder->toFormattedXml();
    }
}
