<?php

namespace App\Services\SgpExport\Exporters;

use App\Services\SgpExport\SgpCodeMappers;
use Illuminate\Support\Facades\DB;

class ComponenteCurricularExporter extends AbstractSgpExporter
{
    public function fileName(): string
    {
        return 'sgp_componentes_curriculares_' . $this->ano();
    }

    public function headings(): array
    {
        return [
            'CO_COMPONENTE_CURRICULAR_AREA_CONHECIMENTO',
            'NOME_AREA_CONHECIMENTO_INSTITUICAO_ENSINO',
            'CO_COMPONENTE_CURRICULAR',
            'NOME_COMPONENTE_CURRICULAR_INSTITUICAO_ENSINO',
            'COMPONENTE_CURRICULAR_CARGA_HORARIA',
            'SISTEMA_AVALIACAO_DESEMPENHO',
        ];
    }

    public function rows(): array
    {
        $ids = $this->idsDoAnoLetivo();
        if ($ids === []) {
            return [];
        }

        $componentes = DB::table('modules.componente_curricular as cc')
            ->leftJoin('modules.area_conhecimento as ac', 'ac.id', '=', 'cc.area_conhecimento_id')
            ->whereIn('cc.id', $ids)
            ->where('cc.instituicao_id', $this->institutionId())
            ->select(
                'cc.id',
                'cc.nome',
                'cc.codigo_educacenso',
                'ac.nome as area_nome'
            )
            ->orderBy('cc.nome')
            ->get();

        $cargas = DB::table('modules.componente_curricular_ano_escolar')
            ->whereIn('componente_curricular_id', $ids)
            ->whereRaw('? = ANY(anos_letivos)', [$this->ano()])
            ->select('componente_curricular_id', DB::raw('MAX(carga_horaria) as carga_horaria'), DB::raw('MAX(tipo_nota) as tipo_nota'))
            ->groupBy('componente_curricular_id')
            ->get()
            ->keyBy('componente_curricular_id');

        $linhas = [];

        foreach ($componentes as $componente) {
            $codigo = (int) $componente->codigo_educacenso;
            $area = SgpCodeMappers::areaConhecimento($codigo);
            $componenteCodigo = SgpCodeMappers::componenteCurricular($codigo);
            $carga = $cargas[$componente->id] ?? null;

            $linhas[] = [
                $area,
                SgpCodeMappers::nomeAreaConhecimento($area, $componente->area_nome),
                $componenteCodigo,
                SgpCodeMappers::nomeComponenteCurricular($componenteCodigo, $componente->nome),
                (string) (int) ($carga?->carga_horaria ?? 0),
                SgpCodeMappers::sistemaAvaliacao($carga?->tipo_nota !== null ? (int) $carga->tipo_nota : null),
            ];
        }

        return $linhas;
    }

    /**
     * @return array<int>
     */
    private function idsDoAnoLetivo(): array
    {
        return collect()
            ->merge($this->idsPorAnoEscolar())
            ->merge($this->idsPorEscolaSerie())
            ->merge($this->idsPorTurma())
            ->unique()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    private function idsPorAnoEscolar(): array
    {
        return DB::table('modules.componente_curricular_ano_escolar as ccae')
            ->join('modules.componente_curricular as cc', 'cc.id', '=', 'ccae.componente_curricular_id')
            ->where('cc.instituicao_id', $this->institutionId())
            ->whereRaw('? = ANY(ccae.anos_letivos)', [$this->ano()])
            ->pluck('ccae.componente_curricular_id')
            ->all();
    }

    /**
     * @return array<int>
     */
    private function idsPorEscolaSerie(): array
    {
        $query = DB::table('pmieducar.escola_serie_disciplina as esd')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'esd.ref_ref_cod_escola')
            ->where('esd.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->institutionId())
            ->whereRaw('? = ANY(esd.anos_letivos)', [$this->ano()]);

        $this->applySchoolFilter($query, 'esd.ref_ref_cod_escola');
        $this->applySchoolAcademicYear($query, 'esd.ref_ref_cod_escola');

        return $query->pluck('esd.ref_cod_disciplina')->all();
    }

    /**
     * @return array<int>
     */
    private function idsPorTurma(): array
    {
        $query = DB::table('modules.componente_curricular_turma as cct')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'cct.turma_id')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
            ->where('t.ano', $this->ano())
            ->where('t.ativo', 1)
            ->where('e.ref_cod_instituicao', $this->institutionId());

        $this->applySchoolFilter($query, 't.ref_ref_cod_escola');
        $this->applySchoolAcademicYear($query, 't.ref_ref_cod_escola');

        return $query->pluck('cct.componente_curricular_id')->all();
    }
}
