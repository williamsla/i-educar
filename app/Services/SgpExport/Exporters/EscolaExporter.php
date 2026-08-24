<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpAddressHelper;
use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class EscolaExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_escolas_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'CO_ENTIDADE',
            'NO_ENTIDADE',
            'DEP_ADMINISTRATIVA_INSTITUICAO',
            'SITUACAO_FUNCIONAMENTO_INSTITUICAO',
            'CNPJ_SECRETARIA_EDUCACAO',
            'CNPJ_UNIDADE_EXECUTORA',
            'INSTITUICAO_TELEFONE',
            'INSTITUICAO_E_MAIL',
            'CO_UF_RES_ENTIDADE',
            'CO_MUNICIPIO_ENTIDADE',
            'INSTITUICAO_DS_LOGRADOURO_RES',
            'INSTITUICAO_NU_ENDERECO_RES',
            'INSTITUICAO_BAIRRO_RES',
            'CEP_INSTITUICAO',
            'INSTITUICAO_LOCALIZACAO_GEOGRAFICA',
            'INSTITUICAO_LOCALIZACAO_DIFERENCIADA',
            'LONGITUDE_INSTITUICAO_ENSINO',
            'LATITUDE_INSTITUICAO_ENSINO',
            'INSTITUICAO_ETAPA_DE_ENSINO',
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', 'eal.ref_cod_escola', '=', 'e.cod_escola')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.fone_pessoa as tel', function ($join) {
                $join->on('tel.idpes', '=', 'e.ref_idpes')
                    ->where('tel.tipo', '=', 1);
            })
            ->where('e.ativo', 1)
            ->where('eal.ativo', 1)
            ->where('eal.ano', $this->ano())
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->select(
                'e.cod_escola',
                'e.ref_idpes',
                'inep.cod_escola_inep as inep',
                DB::raw('COALESCE(p.nome, j.fantasia, e.sigla) as nome'),
                'e.dependencia_administrativa',
                'e.situacao_funcionamento',
                'j.cnpj',
                'p.email',
                'e.email_gestor',
                'tel.ddd',
                'tel.fone',
                'e.zona_localizacao',
                'e.localizacao_diferenciada',
                'e.longitude',
                'e.latitude'
            );

        $this->applySchoolFilter($query, 'e.cod_escola');

        $escolas = $query->distinct()->orderBy('e.cod_escola')->get();
        $enderecos = SgpAddressHelper::carregar($escolas->pluck('ref_idpes')->unique()->filter()->all());
        $etapas = $this->etapasPorEscola($escolas->pluck('cod_escola')->all());
        $cnpjSecretaria = SgpCodeMappers::cnpj(config('sgp.cnpj_secretaria_educacao'));

        $linhas = [];

        foreach ($escolas as $escola) {
            $endereco = $enderecos[$escola->ref_idpes] ?? [
                'logradouro' => '',
                'numero' => '00',
                'bairro' => '',
                'cep' => '',
                'municipio_ibge' => '',
                'uf_ibge' => '',
            ];
            $cnpjEscola = SgpCodeMappers::cnpj($escola->cnpj);

            $linhas[] = [
                SgpCodeMappers::inep($escola->inep),
                mb_substr((string) $escola->nome, 0, 255),
                SgpCodeMappers::dependenciaAdministrativa($escola->dependencia_administrativa ? (int) $escola->dependencia_administrativa : null),
                SgpCodeMappers::situacaoFuncionamento($escola->situacao_funcionamento ? (int) $escola->situacao_funcionamento : null),
                $cnpjSecretaria !== '' ? $cnpjSecretaria : $cnpjEscola,
                $cnpjEscola,
                SgpCodeMappers::telefone($escola->ddd, $escola->fone),
                (string) ($escola->email ?: $escola->email_gestor ?: ''),
                $endereco['uf_ibge'],
                $endereco['municipio_ibge'],
                mb_substr($endereco['logradouro'], 0, 1000),
                $endereco['numero'],
                mb_substr($endereco['bairro'], 0, 1000),
                $endereco['cep'],
                SgpCodeMappers::localizacaoGeografica($escola->zona_localizacao ? (int) $escola->zona_localizacao : null),
                SgpCodeMappers::localizacaoDiferenciada($escola->localizacao_diferenciada ? (int) $escola->localizacao_diferenciada : null),
                (string) ($escola->longitude ?? ''),
                (string) ($escola->latitude ?? ''),
                $etapas[(int) $escola->cod_escola] ?? '',
            ];
        }

        return $linhas;
    }

    /**
     * @param  array<int>  $escolasIds
     * @return array<int, string>
     */
    private function etapasPorEscola(array $escolasIds): array
    {
        if ($escolasIds === []) {
            return [];
        }

        $registros = DB::table('pmieducar.turma as t')
            ->whereIn('t.ref_ref_cod_escola', $escolasIds)
            ->where('t.ano', $this->ano())
            ->where('t.ativo', 1)
            ->whereNotNull('t.etapa_educacenso')
            ->select('t.ref_ref_cod_escola as school_id', 't.etapa_educacenso')
            ->distinct()
            ->get()
            ->groupBy('school_id');

        $resultado = [];

        foreach ($registros as $schoolId => $etapas) {
            $resultado[(int) $schoolId] = SgpCodeMappers::etapasSeparadasPorPontoEVirgula(
                $etapas->pluck('etapa_educacenso')
            );
        }

        return $resultado;
    }
}
