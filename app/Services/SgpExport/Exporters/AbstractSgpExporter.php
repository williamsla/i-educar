<?php

namespace App\Services\SgpExport\Exporters;

abstract class AbstractSgpExporter
{
    public function __construct(protected array $filters) {}

    abstract public function fileName(): string;

    abstract public function headings(): array;

    abstract public function rows(): array;

    protected function ano(): int
    {
        return (int) ($this->filters['ano'] ?? date('Y'));
    }

    protected function institutionId(): ?int
    {
        $id = $this->filters['institution_id'] ?? null;

        return $id ? (int) $id : null;
    }

    protected function applySchoolFilter($query, string $column)
    {
        $schoolId = $this->filters['school_id'] ?? null;
        $schoolIds = $this->filters['school_ids'] ?? null;

        if ($schoolId) {
            if (is_array($schoolIds) && $schoolIds !== [] && !in_array((int) $schoolId, array_map('intval', $schoolIds), true)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where($column, (int) $schoolId);
        }

        if (is_array($schoolIds) && $schoolIds !== []) {
            return $query->whereIn($column, $schoolIds);
        }

        return $query;
    }

    protected function applySchoolAcademicYear($query, string $schoolColumn)
    {
        return $query->join('pmieducar.escola_ano_letivo as eal_sgp', function ($join) use ($schoolColumn) {
            $join->on('eal_sgp.ref_cod_escola', '=', $schoolColumn)
                ->where('eal_sgp.ano', '=', $this->ano())
                ->where('eal_sgp.ativo', '=', 1);
        });
    }
}
