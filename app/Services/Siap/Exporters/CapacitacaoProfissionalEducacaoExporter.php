<?php

namespace App\Services\Siap\Exporters;

/**
 * Capacitação detalhada (data/CH/instituição) não possui cadastro completo no i-Educar.
 * Gera arquivo válido apenas com cabeçalho, como no exemplo real do município.
 */
class CapacitacaoProfissionalEducacaoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'CapacitacaoProfissionalEducacao';
    }

    public function export(): string
    {
        $this->alert('Sem fonte de capacitação com data/carga horária/instituição — arquivo exportado vazio (somente cabeçalho).');

        return $this->builder()->toFormattedXml();
    }
}
