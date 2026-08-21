<?php

namespace App\Services\Siap;

use Illuminate\Support\Facades\DB;

class SiapAddressHelper
{
    /**
     * Carrega CEP por idpes, preferindo addresses.places quando existir;
     * caso contrário usa cadastro.endereco_pessoa (legado).
     *
     * @param  array<int|string>  $idpesList
     * @return array<int|string, string>
     */
    public static function carregarCeps(array $idpesList): array
    {
        $idpesList = array_values(array_unique(array_filter($idpesList)));
        if (empty($idpesList)) {
            return [];
        }

        $resultado = [];

        foreach (array_chunk($idpesList, 500) as $chunk) {
            try {
                $places = DB::table('cadastro.pessoa_has_place as php')
                    ->join('addresses.places as pl', 'pl.id', '=', 'php.place_id')
                    ->whereIn('php.person_id', $chunk)
                    ->select('php.person_id', 'pl.postal_code')
                    ->get();

                foreach ($places as $row) {
                    if (!empty($row->postal_code)) {
                        $resultado[$row->person_id] = $row->postal_code;
                    }
                }
            } catch (\Throwable $e) {
                // Schema moderno de endereço ausente nesta instalação
            }

            $faltantes = array_diff($chunk, array_keys($resultado));
            if (empty($faltantes)) {
                continue;
            }

            try {
                $legado = DB::table('cadastro.endereco_pessoa')
                    ->whereIn('idpes', $faltantes)
                    ->select('idpes', 'cep')
                    ->get();

                foreach ($legado as $row) {
                    if (!empty($row->cep)) {
                        $resultado[$row->idpes] = $row->cep;
                    }
                }
            } catch (\Throwable $e) {
                // Sem endereço legado
            }
        }

        return $resultado;
    }
}
