<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class EstruturaEscolarExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'EstruturaEscolar';
    }

    public function export(): string
    {
        $builder = $this->builder();

        $escolas = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', 'eal.ref_cod_escola', '=', 'e.cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->where('e.ativo', 1)
            ->where('eal.ativo', 1)
            ->where('eal.ano', $this->ano)
            ->select(
                'inep.cod_escola_inep as inep',
                'e.salas_gerais',
                'e.salas_funcionais',
                'e.banheiros',
                'e.laboratorios',
                'e.salas_atividades',
                'e.dormitorios',
                'e.areas_externas'
            )
            ->distinct()
            ->get();

        foreach ($escolas as $escola) {
            foreach (SiapCodeMappers::estruturasDaEscola($escola) as $codigo) {
                $builder->addRecord('EstruturaEscolar', [
                    'INEP' => (string) $escola->inep,
                    'Estrutura' => (string) $codigo,
                ]);
            }
        }

        return $builder->toFormattedXml();
    }
}
