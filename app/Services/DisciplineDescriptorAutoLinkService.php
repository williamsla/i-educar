<?php

namespace App\Services;

use App\Models\LegacyDiscipline;
use App\Models\LegacySchoolClass;
use Illuminate\Support\Collection;

class DisciplineDescriptorAutoLinkService
{
    /**
     * Expande a seleção de componentes do vínculo professor×turma:
     * - inclui descritores das áreas vinculadas às âncoras selecionadas (e oferecidos na turma);
     * - remove descritores cuja âncora não está na seleção (ficha não existe sem o principal).
     *
     * @param  array<int|string|null>  $selectedComponentIds
     * @return array<int>
     */
    public function expandForSchoolClass(array $selectedComponentIds, int $schoolClassId): array
    {
        $schoolClass = LegacySchoolClass::query()->find($schoolClassId);
        $offeredIds = $schoolClass
            ? $schoolClass->getDisciplines()->pluck('id')->all()
            : [];

        return $this->expand($selectedComponentIds, $offeredIds);
    }

    /**
     * @param  array<int|string|null>  $selectedComponentIds
     * @param  array<int|string>  $offeredComponentIds
     * @return array<int>
     */
    public function expand(array $selectedComponentIds, array $offeredComponentIds): array
    {
        $selected = $this->normalizeIds($selectedComponentIds);
        $offered = $this->normalizeIds($offeredComponentIds);

        if ($selected->isEmpty()) {
            return [];
        }

        $descriptorToAnchor = $this->descriptorToAnchorMap($offered);

        $selected = $selected
            ->reject(function (int $id) use ($descriptorToAnchor, $selected) {
                if (!$descriptorToAnchor->has($id)) {
                    return false;
                }

                return !$selected->contains((int) $descriptorToAnchor->get($id));
            })
            ->values();

        $descriptorsToAdd = $descriptorToAnchor
            ->filter(fn ($anchorId) => $selected->contains((int) $anchorId))
            ->keys();

        return $selected
            ->merge($descriptorsToAdd)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Descritores que ainda faltam no vínculo (diferença entre expansão e seleção atual).
     *
     * @param  array<int|string|null>  $selectedComponentIds
     * @return array<int>
     */
    public function missingDescriptorsForSchoolClass(array $selectedComponentIds, int $schoolClassId): array
    {
        $current = $this->normalizeIds($selectedComponentIds);
        $expanded = collect($this->expandForSchoolClass($selectedComponentIds, $schoolClassId));

        return $expanded
            ->diff($current)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $offeredIds
     * @return Collection<int, int> descriptorId => anchorId
     */
    private function descriptorToAnchorMap(Collection $offeredIds): Collection
    {
        if ($offeredIds->isEmpty()) {
            return collect();
        }

        return LegacyDiscipline::query()
            ->select([
                'componente_curricular.id',
                'area_conhecimento.componente_vinculo_id',
            ])
            ->join(
                'modules.area_conhecimento',
                'area_conhecimento.id',
                '=',
                'componente_curricular.area_conhecimento_id'
            )
            ->where('area_conhecimento.agrupar_descritores', true)
            ->whereNotNull('area_conhecimento.componente_vinculo_id')
            ->whereIn('componente_curricular.id', $offeredIds->all())
            ->pluck('componente_vinculo_id', 'id')
            ->map(fn ($anchorId) => (int) $anchorId);
    }

    /**
     * @param  array<int|string|null>  $ids
     * @return Collection<int, int>
     */
    private function normalizeIds(array $ids): Collection
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
