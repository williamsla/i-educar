<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapXmlBuilder;

abstract class AbstractSiapExporter
{
    protected array $alerts = [];

    public function __construct(
        protected readonly string $codigo,
        protected readonly int $ano,
        protected readonly int $mes,
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
}
