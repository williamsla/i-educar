<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class TurmaExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_turmas_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'CO_ENTIDADE',
            'NO_ENTIDADE',
            'CO_TURMA_REDE',
            'NOME_TURMA',
            'TURNO_TURMA',
            'TIPO_TURMA',
            'TURMA_ETAPA_DE_ENSINO',
            'ORGANIZACAO_CURRICULAR_TURMA',
            'TURMA_VAGA',
            'TURMA_LOCALIZACAO',
            'TURMA_FORMA_ORGANIZACAO',
            'TURMA_ORGANIZACAO_QUANTIDADE_TOTAL',
            'TURMA_ORGANIZACAO_QUANTIDADE_ATUAL',
            'MODALIDADE_ENSINO_ESTUDANTE',
            'TURMA_BILINGUE',
            'TIPO_MEDIACAO_DIDATICO_PEDAGOGICA',
            'DATA_INICIO_PERIODO_LETIVO',
            'DATA_INICIO_TURMA',
            'DATA_FIM_TURMA',
            'ID_SGP_COMPONENTE_CURRICULAR',
        ];
    }

    public function rows(): array
    {
        $query = DB::table('pmieducar.turma as t')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->where('t.ano', $this->ano())
            ->where('t.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->institutionId());

        $this->applySchoolFilter($query, 't.ref_ref_cod_escola');
        $this->applySchoolAcademicYear($query, 't.ref_ref_cod_escola');

        $query->select(
            't.cod_turma',
            't.nm_turma',
            't.turma_turno_id',
            't.tipo_atendimento',
            't.etapa_educacenso',
            't.max_aluno',
            't.tipo_mediacao_didatico_pedagogico',
            'c.modalidade_curso',
            'inep.cod_escola_inep as inep',
            DB::raw('COALESCE(p.nome, j.fantasia, e.sigla) as nome_escola')
        );

        $turmas = $query->orderBy('t.nm_turma')->get();
        $periodos = $this->periodos($turmas->pluck('cod_turma')->all());

        $linhas = [];

        foreach ($turmas as $turma) {
            $periodo = $periodos[(int) $turma->cod_turma] ?? ['inicio' => '', 'fim' => '', 'quantidade' => ''];

            $linhas[] = [
                SgpCodeMappers::inep($turma->inep),
                mb_substr((string) $turma->nome_escola, 0, 255),
                (string) $turma->cod_turma,
                mb_substr((string) $turma->nm_turma, 0, 255),
                SgpCodeMappers::turno($turma->turma_turno_id ? (int) $turma->turma_turno_id : null),
                SgpCodeMappers::tipoTurma($turma->tipo_atendimento),
                (string) ($turma->etapa_educacenso ?: ''),
                '1',
                (string) (int) ($turma->max_aluno ?? 0),
                '1',
                '1',
                $periodo['quantidade'],
                '',
                SgpCodeMappers::modalidadeEnsino($turma->modalidade_curso ? (int) $turma->modalidade_curso : null),
                '0',
                SgpCodeMappers::tipoMediacao($turma->tipo_mediacao_didatico_pedagogico ? (int) $turma->tipo_mediacao_didatico_pedagogico : null),
                $periodo['inicio'],
                $periodo['inicio'],
                $periodo['fim'],
                '',
            ];
        }

        return $linhas;
    }

    /**
     * @param  array<int>  $turmasIds
     * @return array<int, array{inicio: string, fim: string, quantidade: string}>
     */
    private function periodos(array $turmasIds): array
    {
        if ($turmasIds === []) {
            return [];
        }

        $modulos = DB::table('pmieducar.turma_modulo')
            ->whereIn('ref_cod_turma', $turmasIds)
            ->select('ref_cod_turma', DB::raw('MIN(data_inicio) as inicio'), DB::raw('MAX(data_fim) as fim'), DB::raw('COUNT(*) as quantidade'))
            ->groupBy('ref_cod_turma')
            ->get()
            ->keyBy('ref_cod_turma');

        $resultado = [];

        foreach ($turmasIds as $turmaId) {
            $modulo = $modulos[$turmaId] ?? null;
            $resultado[(int) $turmaId] = [
                'inicio' => SgpCodeMappers::data($modulo->inicio ?? null),
                'fim' => SgpCodeMappers::data($modulo->fim ?? null),
                'quantidade' => $modulo ? (string) $modulo->quantidade : '',
            ];
        }

        return $resultado;
    }
}
