<?php

namespace App\Services\Siap\Exporters;

/**
 * Exporter genérico para arquivos SIAP sem fonte de dados no sistema
 * (gera apenas o cabeçalho Codigo/Exercicio/Mes, válido pelo XSD).
 */
class EmptyHeaderExporter extends AbstractSiapExporter
{
    public function __construct(
        string $codigo,
        int $ano,
        int $mes,
        int $instituicaoId,
        private readonly string $arquivo,
        private readonly string $motivo = 'Sem cadastro no sistema — arquivo exportado apenas com cabeçalho.',
    ) {
        parent::__construct($codigo, $ano, $mes, $instituicaoId);
    }

    public function fileName(): string
    {
        return $this->arquivo;
    }

    public function export(): string
    {
        $this->alert($this->motivo);

        return $this->builder()->toFormattedXml();
    }
}
