<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class EquipamentoEscolaExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'EquipamentoEscola';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $dataDefault = config('siap.defaults.data_ultima_compra_equipamento')
            ?: sprintf('%04d-%02d-01', $this->ano, max(1, $this->mes - 1));

        $escolas = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', 'eal.ref_cod_escola', '=', 'e.cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->where('e.ativo', 1)
            ->where('eal.ativo', 1)
            ->where('eal.ano', $this->ano)
            ->select(
                'inep.cod_escola_inep as inep',
                'e.televisoes',
                'e.dvds',
                'e.antenas_parabolicas',
                'e.copiadoras',
                'e.retroprojetores',
                'e.impressoras',
                'e.aparelhos_de_som',
                'e.projetores_digitais',
                'e.faxs',
                'e.maquinas_fotograficas',
                'e.computadores',
                'e.computadores_alunos',
                'e.impressoras_multifuncionais',
                'e.quantidade_computadores_alunos_mesa',
                'e.quantidade_computadores_alunos_tablets',
                'e.lousas_digitais'
            )
            ->distinct()
            ->get();

        $usouDefault = false;

        foreach ($escolas as $escola) {
            foreach (SiapCodeMappers::equipamentosDaEscola($escola) as $item) {
                $builder->addRecord('EquipamentoEscola', [
                    'INEP' => (string) $escola->inep,
                    'Equipamento' => (string) $item['codigo'],
                    'Quantidade' => (string) $item['quantidade'],
                    'QuantidadeUso' => (string) $item['quantidade'],
                    'DataUltimaCompra' => $dataDefault,
                ]);
                $usouDefault = true;
            }
        }

        if ($usouDefault) {
            $this->alert("DataUltimaCompra preenchida com padrão {$dataDefault} (sem histórico de compra no sistema).");
        }

        return $builder->toFormattedXml();
    }
}
