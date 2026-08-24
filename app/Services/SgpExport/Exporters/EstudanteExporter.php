<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpAddressHelper;
use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class EstudanteExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_estudantes_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'ESTUDANTE_CPF',
            'ESTUDANTE_NIS',
            'ESTUDANTE_NOME',
            'ESTUDANTE_NOME_SOCIAL',
            'ESTUDANTE_DT_NASCIMENTO',
            'ESTUDANTE_SEXO',
            'ESTUDANTE_RACA_COR',
            'ESTUDANTE_QUILOMBOLA',
            'ESTUDANTE_NACIONALIDADE',
            'ESTUDANTE_TELEFONE',
            'ESTUDANTE_EMAIL',
            'ESTUDANTE_SITUACAO_RUA',
            'ESTUDANTE_RESPONSAVEL_1_NOME',
            'ESTUDANTE_VINCULO_RESPONSAVEL_1',
            'RESPONSAVEL_1_CPF',
            'RESPONSAVEL_1_TELEFONE',
            'RESPONSAVEL_1_EMAIL',
            'ESTUDANTE_RESPONSAVEL_2_NOME',
            'ESTUDANTE_VINCULO_RESPONSAVEL_2',
            'RESPONSAVEL_2_CPF',
            'ESTUDANTE_CO_UF_RES',
            'ESTUDANTE_CO_MUNICIPIO_RES',
            'ESTUDANTE_LOGRADOURO_RES',
            'ESTUDANTE_NU_ENDERECO_RES',
            'ESTUDANTE_BAIRRO_RES',
            'ESTUDANTE_CEP_RES',
            'ESTUDANTE_LOCALIZACAO_GEOGRAFICA',
            'ESTUDANTE_LOCALIZACAO_DIFERENCIADA',
            'ESTUDANTE_NECESSIDADE_NUTRICIONAL',
            'ESTUDANTE_DEFICIENCIA',
            'ESTUDANTE_REFORCO',
            'ESTUDANTE_APOIO_PEDAGOGICO',
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
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'm.ref_ref_cod_escola')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'a.ref_idpes')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.pessoa as pe', 'pe.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.raca as r', 'r.cod_raca', '=', 'fr.ref_cod_raca')
            ->leftJoin('cadastro.fone_pessoa as tel', function ($join) {
                $join->on('tel.idpes', '=', 'f.idpes')
                    ->where('tel.tipo', '=', 1);
            })
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.fisica as mae_f', 'mae_f.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.fone_pessoa as mae_tel', function ($join) {
                $join->on('mae_tel.idpes', '=', 'f.idpes_mae')
                    ->where('mae_tel.tipo', '=', 1);
            })
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->leftJoin('cadastro.fisica as pai_f', 'pai_f.idpes', '=', 'f.idpes_pai')
            ->where('m.ano', $this->ano())
            ->where('t.ano', $this->ano())
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('a.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->select(
                'a.cod_aluno',
                'f.cpf',
                'f.nis_pis_pasep',
                'p.nome',
                'f.nome_social',
                'f.data_nasc',
                'f.sexo',
                'r.raca_educacenso',
                'f.nacionalidade',
                'p.email',
                'tel.ddd',
                'tel.fone',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                'f.idpes',
                'mae.nome as nome_mae',
                'mae_f.cpf as cpf_mae',
                'mae.email as email_mae',
                'mae_tel.ddd as ddd_mae',
                'mae_tel.fone as fone_mae',
                'pai.nome as nome_pai',
                'pai_f.cpf as cpf_pai',
                'inep.cod_escola_inep as inep',
                DB::raw('COALESCE(pe.nome, j.fantasia, e.sigla) as nome_escola'),
                'm.cod_matricula',
                'm.data_matricula',
                't.cod_turma',
                't.etapa_educacenso',
                't.turma_turno_id',
                'm.ano'
            );

        $this->applySchoolFilter($query, 'm.ref_ref_cod_escola');
        $this->applySchoolAcademicYear($query, 'm.ref_ref_cod_escola');

        $estudantes = $query->distinct()->orderBy('p.nome')->get();
        $enderecos = SgpAddressHelper::carregar($estudantes->pluck('idpes')->unique()->filter()->all());
        $deficiencias = $this->deficiencias($estudantes->pluck('idpes')->unique()->filter()->all());

        $linhas = [];

        foreach ($estudantes as $estudante) {
            $endereco = $enderecos[$estudante->idpes] ?? [
                'logradouro' => '',
                'numero' => '00',
                'bairro' => '',
                'cep' => '',
                'municipio_ibge' => '',
                'uf_ibge' => '',
            ];

            $responsavel1Nome = (string) ($estudante->nome_mae ?: $estudante->nome_pai ?: '');
            $responsavel1Cpf = $estudante->nome_mae ? $estudante->cpf_mae : $estudante->cpf_pai;
            $temMae = !empty($estudante->nome_mae);
            $responsavel2Nome = $temMae ? (string) ($estudante->nome_pai ?? '') : '';

            $linhas[] = [
                SgpCodeMappers::cpf($estudante->cpf),
                SgpCodeMappers::apenasDigitos($estudante->nis_pis_pasep),
                mb_substr((string) $estudante->nome, 0, 255),
                mb_substr((string) ($estudante->nome_social ?? ''), 0, 255),
                SgpCodeMappers::data($estudante->data_nasc),
                SgpCodeMappers::sexo($estudante->sexo),
                SgpCodeMappers::racaCor($estudante->raca_educacenso ? (int) $estudante->raca_educacenso : null),
                ((int) $estudante->localizacao_diferenciada === 2) ? '1' : '2',
                SgpCodeMappers::nacionalidade($estudante->nacionalidade ? (int) $estudante->nacionalidade : null),
                SgpCodeMappers::telefone($estudante->ddd, $estudante->fone),
                (string) ($estudante->email ?? ''),
                '0',
                mb_substr($responsavel1Nome, 0, 255),
                $responsavel1Nome !== '' ? '1' : '5',
                SgpCodeMappers::cpf($responsavel1Cpf),
                SgpCodeMappers::telefone($estudante->ddd_mae, $estudante->fone_mae),
                (string) ($estudante->email_mae ?? ''),
                mb_substr($responsavel2Nome, 0, 255),
                $responsavel2Nome !== '' ? '1' : '',
                $responsavel2Nome !== '' ? SgpCodeMappers::cpf($estudante->cpf_pai) : '',
                $endereco['uf_ibge'],
                $endereco['municipio_ibge'],
                mb_substr($endereco['logradouro'], 0, 1000),
                $endereco['numero'],
                mb_substr($endereco['bairro'], 0, 1000),
                $endereco['cep'],
                SgpCodeMappers::localizacaoGeografica($estudante->zona_localizacao_censo ? (int) $estudante->zona_localizacao_censo : null),
                SgpCodeMappers::localizacaoDiferenciada($estudante->localizacao_diferenciada ? (int) $estudante->localizacao_diferenciada : null),
                '0',
                $deficiencias[(int) $estudante->idpes] ?? '0',
                '0',
                '0',
                SgpCodeMappers::inep($estudante->inep),
                mb_substr((string) $estudante->nome_escola, 0, 255),
                (string) $estudante->cod_matricula,
                '',
                (string) ($estudante->etapa_educacenso ?: ''),
                (string) $estudante->ano,
                SgpCodeMappers::data($estudante->data_matricula),
                ((int) $estudante->turma_turno_id === 4) ? '1' : '2',
                '',
                (string) $estudante->cod_turma,
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
