<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpAddressHelper;
use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class ProfissionalExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_profissionais_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'PROFISSIONAL_CPF',
            'PROFISSIONAL_NOME',
            'PROFISSIONAL_NOME_SOCIAL',
            'PROFISSIONAL_DT_NASCIMENTO',
            'PROFISSIONAL_SEXO',
            'PROFISSIONAL_GENERO',
            'PROFISSIONAL_RACA_COR',
            'PROFISSIONAL_QUILOMBOLA',
            'PROFISSIONAL_NACIONALIDADE',
            'PROFISSIONAL_TELEFONE',
            'PROFISSIONAL_E_MAIL',
            'CO_NIVEL_ESCOLARIDADE',
            'CO_TIPO_ENSINO_MEDIO',
            'NATUREZA_INSTITUICAO_MEDIO_PROFISSION',
            'PROFISSIONAL_CO_UF_RES',
            'PROFISSIONAL_CO_MUNICIPIO_RES',
            'PROFISSIONAL_DS_LOGRADOURO_RES',
            'PROFISSIONAL_NU_ENDERECO_RES',
            'PROFISSIONAL_BAIRRO_RES',
            'PROFISSIONAL_CEP_RES',
            'PROFISSIONAL_LOCALIZACAO_GEOGRAFICA',
            'PROFISSIONAL_LOCALIZACAO_DIFERENCIADA',
            'PROFISSIONAL_DEFICIENCIA',
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.servidor as s')
            ->join('pmieducar.servidor_alocacao as sa', 'sa.ref_cod_servidor', '=', 's.cod_servidor')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.raca as r', 'r.cod_raca', '=', 'fr.ref_cod_raca')
            ->leftJoin('cadastro.fone_pessoa as tel', function ($join) {
                $join->on('tel.idpes', '=', 'f.idpes')
                    ->where('tel.tipo', '=', 1);
            })
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'sa.ref_cod_escola')
            ->where('s.ativo', 1)
            ->where('sa.ativo', 1)
            ->where('sa.ano', $this->ano())
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->select(
                's.cod_servidor',
                'f.cpf',
                'p.nome',
                'f.nome_social',
                'f.data_nasc',
                'f.sexo',
                'r.raca_educacenso',
                'f.nacionalidade',
                'p.email',
                'tel.ddd',
                'tel.fone',
                's.ref_idesco',
                's.tipo_ensino_medio_cursado',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                'f.idpes'
            );

        $this->applySchoolFilter($query, 'sa.ref_cod_escola');
        $this->applySchoolAcademicYear($query, 'sa.ref_cod_escola');

        $profissionais = $query->distinct()->orderBy('p.nome')->get();
        $enderecos = SgpAddressHelper::carregar($profissionais->pluck('idpes')->unique()->filter()->all());
        $deficiencias = $this->deficiencias($profissionais->pluck('idpes')->unique()->filter()->all());

        $linhas = [];
        $exportados = [];

        foreach ($profissionais as $profissional) {
            $cpf = SgpCodeMappers::cpf($profissional->cpf);
            $chave = $cpf !== '' ? $cpf : 'id:' . $profissional->cod_servidor;

            if (isset($exportados[$chave])) {
                continue;
            }
            $exportados[$chave] = true;

            $endereco = $enderecos[$profissional->idpes] ?? [
                'logradouro' => '',
                'numero' => '00',
                'bairro' => '',
                'cep' => '',
                'municipio_ibge' => '',
                'uf_ibge' => '',
            ];

            $linhas[] = [
                $cpf,
                mb_substr((string) $profissional->nome, 0, 255),
                mb_substr((string) ($profissional->nome_social ?? ''), 0, 255),
                SgpCodeMappers::data($profissional->data_nasc),
                SgpCodeMappers::sexo($profissional->sexo),
                '0',
                SgpCodeMappers::racaCor($profissional->raca_educacenso ? (int) $profissional->raca_educacenso : null),
                '0',
                SgpCodeMappers::nacionalidade($profissional->nacionalidade ? (int) $profissional->nacionalidade : null),
                SgpCodeMappers::telefone($profissional->ddd, $profissional->fone),
                (string) ($profissional->email ?? ''),
                (string) ($profissional->ref_idesco ?? ''),
                (string) ($profissional->tipo_ensino_medio_cursado ?? ''),
                '0',
                $endereco['uf_ibge'],
                $endereco['municipio_ibge'],
                mb_substr($endereco['logradouro'], 0, 1000),
                $endereco['numero'],
                mb_substr($endereco['bairro'], 0, 1000),
                $endereco['cep'],
                SgpCodeMappers::localizacaoGeografica($profissional->zona_localizacao_censo ? (int) $profissional->zona_localizacao_censo : null),
                SgpCodeMappers::localizacaoDiferenciada($profissional->localizacao_diferenciada ? (int) $profissional->localizacao_diferenciada : null),
                $deficiencias[(int) $profissional->idpes] ?? '0',
            ];
        }

        return $linhas;
    }

    /**
     * @param  array<int>  $idpesList
     * @return array<int, string>
     */
    private function deficiencias(array $idpesList): array
    {
        if ($idpesList === []) {
            return [];
        }

        $registros = DB::table('cadastro.fisica_deficiencia as fd')
            ->leftJoin('cadastro.deficiencia as d', 'd.cod_deficiencia', '=', 'fd.ref_cod_deficiencia')
            ->whereIn('fd.ref_idpes', $idpesList)
            ->select('fd.ref_idpes', 'd.deficiencia_educacenso')
            ->get()
            ->groupBy('ref_idpes');

        $resultado = [];

        foreach ($registros as $idpes => $itens) {
            $codigos = $itens->pluck('deficiencia_educacenso')->filter()->unique()->values()->all();
            $resultado[(int) $idpes] = $codigos === [] ? '0' : implode(';', $codigos);
        }

        return $resultado;
    }
}
