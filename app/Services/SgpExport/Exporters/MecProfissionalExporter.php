<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpAddressHelper;
use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class MecProfissionalExporter extends AbstractSgpExporter
{
    public const DATA_START_ROW = 4;

    public function fileName(): string
    {
        return 'mec_gestao_presente_profissionais_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'PROFISSIONAL_CPF',
            'PROFISSIONAL_RG',
            'PROFISSIONAL_NOME',
            'PROFISSIONAL_NOME_SOCIAL',
            'PROFISSIONAL_DT_NASCIMENTO',
            'PROFISSIONAL_SEXO',
            'PROFISSIONAL_GENERO',
            'PROFISSIONAL_RACA_COR',
            'PROFISSIONAL_QUILOMBOLA',
            'PROFISSIONAL_NACIONALIDADE',
            'PROFISSIONAL_PAIS_NASCIMENTO',
            'PROFISSIONAL_ESTADO_NASCIMENTO',
            'PROFISSIONAL_MUNICIPIO_NASCIMENTO',
            'PROFISSIONAL_TELEFONE',
            'PROFISSIONAL_E_MAIL',
            'CO_NIVEL_ESCOLARIDADE',
            'CO_TIPO_ENSINO_MEDIO',
            'NATUREZA_INSTITUICAO_MEDIO_PROFISSIONAL',
            'PROFISSIONAL_CO_UF_RES',
            'PROFISSIONAL_CO_MUNICIPIO_RES',
            'PROFISSIONAL_DS_LOGRADOURO_RES',
            'PROFISSIONAL_NU_ENDERECO_RES',
            'PROFISSIONAL_BAIRRO_RES',
            'PROFISSIONAL_CEP_RES',
            'PROFISSIONAL_LOCALIZACAO_GEOGRAFICA',
            'PROFISSIONAL_LOCALIZACAO_DIFERENCIADA',
            'PROFISSIONAL_DEFICIENCIA',
            'CO_TIPO_FORMACAO_ACADEMICA',
            'CO_AREA_DO_CONHECIMENTO_FORMACAO_ACADEMICA',
            'CO_CURSO_FORMACAO_ACADEMICA',
            'NO_INSTITUICAO_ENSINO_FORMACAO_ACADEMICA',
            'NATUREZA_INSTITUICAO_FORMACAO_ACADEMICA',
            'ANO_INICIO_FORMACAO_ACADEMICA',
            'ANO_CONCLUSAO_FORMACAO_ACADEMICA',
            'CO_ENTIDADE_VINCULO',
            'CO_PROFISSIONAL_PERFIL_VINCULO',
            'CO_TIPO_VINCULO',
            'SITUACAO_VINCULO_PROFISSIONAL_REDE',
            'DATA_INICIO_VINCULO_PROFISSIONAL_REDE',
            'DATA_FIM_VINCULO_PROFISSIONAL_REDE',
            'CO_FUNCAO',
            'DATA_INGRESSO',
            'DATA_FIM',
            'CARGA_HORARIA_SEMANAL',
            'PROFISSIONAL_IDENTIFICACAO_INSTITUICAO',
            'AREA_CONHECIMENTO_VINCULO_PROFISSIONAL',
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.servidor_alocacao as sa')
            ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'sa.ref_cod_escola')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.raca as r', 'r.cod_raca', '=', 'fr.ref_cod_raca')
            ->leftJoin('cadastro.fone_pessoa as tel', function ($join) {
                $join->on('tel.idpes', '=', 'f.idpes')
                    ->where('tel.tipo', '=', 1);
            })
            ->leftJoin('cadastro.documento as doc', 'doc.idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.escolaridade as esc', 'esc.idesco', '=', 's.ref_idesco')
            ->leftJoin('cities as cnasc', 'cnasc.id', '=', 'f.idmun_nascimento')
            ->leftJoin('states as snasc', 'snasc.id', '=', 'cnasc.state_id')
            ->leftJoin('countries as paisnasc', 'paisnasc.id', '=', 'f.idpais_estrangeiro')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 's.cod_servidor')
            ->leftJoin('pmieducar.servidor_funcao as sf', 'sf.cod_servidor_funcao', '=', 'sa.ref_cod_servidor_funcao')
            ->leftJoin('pmieducar.funcao as fn', 'fn.cod_funcao', '=', 'sf.ref_cod_funcao')
            ->where('s.ativo', 1)
            ->where('sa.ativo', 1)
            ->where('sa.ano', $this->ano())
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->select(
                's.cod_servidor',
                'sa.ref_cod_escola',
                'sa.data_admissao',
                'sa.data_saida',
                'sa.ref_cod_funcionario_vinculo',
                'sa.carga_horaria as carga_horaria_horas',
                's.carga_horaria as carga_servidor',
                'f.cpf',
                'doc.rg',
                'p.nome',
                'f.nome_social',
                'f.data_nasc',
                'f.sexo',
                'r.raca_educacenso',
                'f.nacionalidade',
                'paisnasc.ibge_code as pais_nascimento',
                'snasc.ibge_code as uf_nascimento',
                'cnasc.ibge_code as municipio_nascimento',
                'p.email',
                'tel.ddd',
                'tel.fone',
                'esc.escolaridade',
                's.tipo_ensino_medio_cursado',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                'f.idpes',
                'inep.cod_escola_inep as inep',
                'func.matricula',
                'fn.professor',
                'fn.nm_funcao'
            );

        $this->applySchoolFilter($query, 'sa.ref_cod_escola');
        $this->applySchoolAcademicYear($query, 'sa.ref_cod_escola');

        $vinculos = $query->orderBy('p.nome')->get();
        $idpesList = $vinculos->pluck('idpes')->unique()->filter()->all();
        $servidorIds = $vinculos->pluck('cod_servidor')->unique()->filter()->all();

        $enderecos = SgpAddressHelper::carregar($idpesList);
        $deficiencias = $this->deficiencias($idpesList);
        $formacoes = $this->formacoes($servidorIds);
        $turmas = $this->vinculosTurma($servidorIds);

        $linhas = [];
        $exportados = [];

        foreach ($vinculos as $vinculo) {
            $cpf = SgpCodeMappers::cpfOnzeDigitos($vinculo->cpf);
            $chave = ($cpf !== '' ? $cpf : 'id:' . $vinculo->cod_servidor) . '|' . $vinculo->ref_cod_escola;

            if (isset($exportados[$chave])) {
                continue;
            }
            $exportados[$chave] = true;

            $endereco = $enderecos[$vinculo->idpes] ?? [
                'logradouro' => '',
                'numero' => '00',
                'bairro' => '',
                'cep' => '',
                'municipio_ibge' => '',
                'uf_ibge' => '',
            ];

            $formacao = $formacoes[(int) $vinculo->cod_servidor] ?? $this->formacaoVazia();
            $turma = $turmas[$vinculo->cod_servidor . '|' . $vinculo->ref_cod_escola] ?? null;

            $coFuncao = SgpCodeMappers::funcaoMec(
                $turma['funcao_exercida'] ?? null,
                (bool) ($vinculo->professor ?? $turma),
                $vinculo->nm_funcao ?? null
            );

            $dataInicio = SgpCodeMappers::data($vinculo->data_admissao)
                ?: sprintf('01/01/%04d', $this->ano());
            $dataIngresso = SgpCodeMappers::data($turma['data_inicial'] ?? null) ?: $dataInicio;
            $dataFimVinculo = SgpCodeMappers::data($vinculo->data_saida);
            $dataFimFuncao = SgpCodeMappers::data($turma['data_fim'] ?? null) ?: $dataFimVinculo;

            $areaVinculo = '';
            if (!empty($turma['areas'])) {
                $areaVinculo = SgpCodeMappers::areaConhecimentoVinculo((int) $turma['areas'][0]);
            }

            $inep = SgpCodeMappers::inep($vinculo->inep);
            $identificacao = trim((string) ($vinculo->matricula ?? ''));
            if ($identificacao === '') {
                $identificacao = (string) $vinculo->cod_servidor;
            }

            $linhas[] = [
                $cpf,
                SgpCodeMappers::rg($vinculo->rg),
                mb_substr((string) $vinculo->nome, 0, 1000),
                mb_substr((string) ($vinculo->nome_social ?? ''), 0, 1000),
                SgpCodeMappers::data($vinculo->data_nasc),
                SgpCodeMappers::sexo($vinculo->sexo),
                '0',
                SgpCodeMappers::racaCor($vinculo->raca_educacenso ? (int) $vinculo->raca_educacenso : null),
                '0',
                SgpCodeMappers::nacionalidadeMec($vinculo->nacionalidade ? (int) $vinculo->nacionalidade : null),
                SgpCodeMappers::paisIso($vinculo->pais_nascimento, $vinculo->nacionalidade ? (int) $vinculo->nacionalidade : null),
                $vinculo->uf_nascimento ? (string) $vinculo->uf_nascimento : '',
                SgpCodeMappers::municipioIbge($vinculo->municipio_nascimento),
                SgpCodeMappers::telefone($vinculo->ddd, $vinculo->fone),
                (string) ($vinculo->email ?? ''),
                SgpCodeMappers::nivelEscolaridadeMec(
                    $vinculo->escolaridade ? (int) $vinculo->escolaridade : null,
                    $formacao['tipo'] ?: null
                ),
                (string) ($vinculo->tipo_ensino_medio_cursado ?? ''),
                '',
                $endereco['uf_ibge'],
                $endereco['municipio_ibge'],
                mb_substr($endereco['logradouro'], 0, 1000),
                $endereco['numero'],
                mb_substr($endereco['bairro'], 0, 1000),
                $endereco['cep'],
                SgpCodeMappers::localizacaoGeografica($vinculo->zona_localizacao_censo ? (int) $vinculo->zona_localizacao_censo : null),
                SgpCodeMappers::localizacaoDiferenciada($vinculo->localizacao_diferenciada ? (int) $vinculo->localizacao_diferenciada : null),
                $deficiencias[(int) $vinculo->idpes] ?? '0',
                $formacao['tipo'],
                $formacao['area'],
                $formacao['curso'],
                $formacao['ies'],
                $formacao['natureza'],
                $formacao['ano_inicio'],
                $formacao['ano_conclusao'],
                $inep !== '' ? $inep : '99999999',
                SgpCodeMappers::perfilVinculoMec($coFuncao),
                SgpCodeMappers::tipoVinculoMec($turma['tipo_vinculo'] ?? null, $vinculo->ref_cod_funcionario_vinculo),
                '1',
                $dataInicio,
                $dataFimVinculo,
                $coFuncao,
                $dataIngresso,
                $dataFimFuncao,
                SgpCodeMappers::cargaHorariaSemanal($vinculo->carga_horaria_horas, $vinculo->carga_servidor),
                $identificacao,
                $areaVinculo,
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
            $resultado[(int) $idpes] = SgpCodeMappers::deficienciaMec(
                $itens->pluck('deficiencia_educacenso')->filter()->all()
            );
        }

        return $resultado;
    }

    /**
     * @param  array<int>  $servidorIds
     * @return array<int, array<string, string>>
     */
    private function formacoes(array $servidorIds): array
    {
        if ($servidorIds === []) {
            return [];
        }

        $posgraduacoes = DB::table('employee_posgraduate')
            ->whereIn('employee_id', $servidorIds)
            ->select('employee_id', 'type_id', 'area_id', 'completion_year')
            ->get()
            ->groupBy('employee_id');

        $graduacoes = DB::table('employee_graduations as eg')
            ->leftJoin('modules.educacenso_curso_superior as cs', 'cs.id', '=', 'eg.course_id')
            ->leftJoin('modules.educacenso_ies as ies', 'ies.id', '=', 'eg.college_id')
            ->whereIn('eg.employee_id', $servidorIds)
            ->select(
                'eg.employee_id',
                'eg.completion_year',
                'cs.curso_id',
                'cs.grau_academico',
                'cs.classe_id',
                'ies.nome as ies_nome',
                'ies.dependencia_administrativa_id'
            )
            ->get()
            ->groupBy('employee_id');

        $resultado = [];

        foreach ($servidorIds as $servidorId) {
            $resultado[(int) $servidorId] = $this->escolherFormacao(
                $posgraduacoes->get($servidorId),
                $graduacoes->get($servidorId)
            );
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private function escolherFormacao($posList, $gradList): array
    {
        $resultado = $this->formacaoVazia();
        $melhorPos = null;
        $rankPos = [3 => 3, 2 => 2, 1 => 1];

        foreach ($posList ?? [] as $pos) {
            $rank = $rankPos[(int) $pos->type_id] ?? 0;
            $ano = (int) $pos->completion_year;

            if ($melhorPos === null || $rank > $melhorPos['rank'] || ($rank === $melhorPos['rank'] && $ano > $melhorPos['year'])) {
                $melhorPos = ['rank' => $rank, 'year' => $ano, 'row' => $pos];
            }
        }

        $melhorGrad = null;

        foreach ($gradList ?? [] as $grad) {
            $ano = (int) $grad->completion_year;

            if ($melhorGrad === null || $ano > $melhorGrad['year']) {
                $melhorGrad = ['year' => $ano, 'row' => $grad];
            }
        }

        if ($melhorGrad !== null) {
            $grad = $melhorGrad['row'];
            $resultado['tipo'] = SgpCodeMappers::tipoFormacaoAcademicaGrau($grad->grau_academico);
            $resultado['area'] = $grad->classe_id ? (string) $grad->classe_id : '';
            $resultado['curso'] = (string) ($grad->curso_id ?? '');
            $resultado['ies'] = mb_substr((string) ($grad->ies_nome ?? ''), 0, 150);
            $resultado['natureza'] = SgpCodeMappers::naturezaInstituicao($grad->dependencia_administrativa_id);
            $resultado['ano_conclusao'] = $melhorGrad['year'] > 0 ? (string) $melhorGrad['year'] : '';
        }

        if ($melhorPos !== null && $melhorPos['rank'] > 0) {
            $pos = $melhorPos['row'];
            $resultado['tipo'] = SgpCodeMappers::tipoFormacaoAcademicaPos($pos->type_id);

            if ($pos->area_id) {
                $resultado['area'] = (string) $pos->area_id;
            }

            if ($melhorPos['year'] > 0) {
                $resultado['ano_conclusao'] = (string) $melhorPos['year'];
            }
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private function formacaoVazia(): array
    {
        return [
            'tipo' => '',
            'area' => '',
            'curso' => '',
            'ies' => '',
            'natureza' => '',
            'ano_inicio' => '',
            'ano_conclusao' => '',
        ];
    }

    /**
     * @param  array<int>  $servidorIds
     * @return array<string, array<string, mixed>>
     */
    private function vinculosTurma(array $servidorIds): array
    {
        if ($servidorIds === []) {
            return [];
        }

        $registros = DB::table('modules.professor_turma as pt')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
            ->leftJoin('modules.professor_turma_disciplina as ptd', 'ptd.professor_turma_id', '=', 'pt.id')
            ->leftJoin('modules.componente_curricular as cc', 'cc.id', '=', 'ptd.componente_curricular_id')
            ->where('pt.ano', $this->ano())
            ->whereIn('pt.servidor_id', $servidorIds)
            ->select(
                'pt.servidor_id',
                't.ref_ref_cod_escola as escola_id',
                'pt.funcao_exercida',
                'pt.tipo_vinculo',
                'pt.data_inicial',
                'pt.data_fim',
                'cc.codigo_educacenso'
            )
            ->get();

        $resultado = [];

        foreach ($registros as $row) {
            $chave = $row->servidor_id . '|' . $row->escola_id;

            if (!isset($resultado[$chave])) {
                $resultado[$chave] = [
                    'funcao_exercida' => $row->funcao_exercida ? (int) $row->funcao_exercida : null,
                    'tipo_vinculo' => $row->tipo_vinculo ? (int) $row->tipo_vinculo : null,
                    'data_inicial' => $row->data_inicial,
                    'data_fim' => $row->data_fim,
                    'areas' => [],
                ];
            }

            if ($row->codigo_educacenso) {
                $resultado[$chave]['areas'][] = (int) $row->codigo_educacenso;
            }
        }

        return $resultado;
    }
}
