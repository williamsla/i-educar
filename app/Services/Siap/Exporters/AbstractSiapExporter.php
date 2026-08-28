<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapXmlBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class AbstractSiapExporter
{
    protected array $alerts = [];

    /** @var array<int, int>|null */
    private ?array $idsEscolasInstituicao = null;

    public function __construct(
        protected readonly string $codigo,
        protected readonly int $ano,
        protected readonly int $mes,
        protected readonly int $instituicaoId,
        protected readonly bool $somenteAlunosComInep = false,
    ) {
    }

    abstract public function fileName(): string;

    abstract public function export(): string;

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    protected function alert(string $message): void
    {
        $this->alerts[] = '[' . $this->fileName() . '] ' . $message;
    }

    protected function builder(): SiapXmlBuilder
    {
        return new SiapXmlBuilder($this->codigo, (string) $this->ano, (string) $this->mes);
    }

    protected function inicioMes(): string
    {
        return sprintf('%04d-%02d-01', $this->ano, $this->mes);
    }

    protected function fimMes(): string
    {
        return date('Y-m-t', strtotime($this->inicioMes()));
    }

    protected function aplicarFiltroInstituicao(Builder $query, string $escolaAlias = 'e'): void
    {
        $query->where("{$escolaAlias}.ref_cod_instituicao", $this->instituicaoId);
    }

    /**
     * @return array<int, int>
     */
    protected function idsEscolasInstituicao(): array
    {
        if ($this->idsEscolasInstituicao === null) {
            $this->idsEscolasInstituicao = DB::table('pmieducar.escola')
                ->where('ref_cod_instituicao', $this->instituicaoId)
                ->where('ativo', 1)
                ->pluck('cod_escola')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->idsEscolasInstituicao;
    }
}
