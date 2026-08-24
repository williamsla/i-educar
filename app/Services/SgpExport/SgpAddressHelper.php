<?php

namespace App\Services\SgpExport;

use Illuminate\Support\Facades\DB;

class SgpAddressHelper
{
    /**
     * @param  array<int|string>  $idpesList
     * @return array<int|string, array<string, string>>
     */
    public static function carregar(array $idpesList): array
    {
        $idpesList = array_values(array_unique(array_filter($idpesList)));
        if (empty($idpesList)) {
            return [];
        }

        $resultado = [];

        foreach (array_chunk($idpesList, 500) as $chunk) {
            self::carregarModerno($chunk, $resultado);

            $faltantes = array_diff($chunk, array_keys($resultado));
            if (!empty($faltantes)) {
                self::carregarLegado($faltantes, $resultado);
            }
        }

        return $resultado;
    }

    /**
     * @param  array<int|string>  $chunk
     * @param  array<int|string, array<string, string>>  $resultado
     */
    private static function carregarModerno(array $chunk, array &$resultado): void
    {
        try {
            $places = DB::table('cadastro.pessoa_has_place as php')
                ->join('addresses.places as pl', 'pl.id', '=', 'php.place_id')
                ->leftJoin('cities as c', 'c.id', '=', 'pl.city_id')
                ->leftJoin('states as st', 'st.id', '=', 'c.state_id')
                ->whereIn('php.person_id', $chunk)
                ->select(
                    'php.person_id',
                    'pl.address',
                    'pl.number',
                    'pl.neighborhood',
                    'pl.postal_code',
                    'c.ibge_code as city_ibge',
                    'st.abbreviation as uf'
                )
                ->get();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($places as $row) {
            if (isset($resultado[$row->person_id])) {
                continue;
            }

            $resultado[$row->person_id] = self::normalizar([
                'logradouro' => $row->address,
                'numero' => $row->number,
                'bairro' => $row->neighborhood,
                'cep' => $row->postal_code,
                'municipio_ibge' => $row->city_ibge,
                'uf' => $row->uf,
            ]);
        }
    }

    /**
     * @param  array<int|string>  $chunk
     * @param  array<int|string, array<string, string>>  $resultado
     */
    private static function carregarLegado(array $chunk, array &$resultado): void
    {
        try {
            $legado = DB::table('cadastro.endereco_pessoa as ep')
                ->leftJoin('public.logradouro as l', 'l.idlog', '=', 'ep.idlog')
                ->leftJoin('public.bairro as b', 'b.idbai', '=', 'ep.idbai')
                ->leftJoin('public.municipio as m', 'm.idmun', '=', 'b.idmun')
                ->whereIn('ep.idpes', $chunk)
                ->select(
                    'ep.idpes',
                    'l.nome as address',
                    'ep.numero as number',
                    'b.nome as neighborhood',
                    'ep.cep as postal_code',
                    'm.cod_ibge as city_ibge',
                    'm.sigla_uf as uf'
                )
                ->get();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($legado as $row) {
            if (isset($resultado[$row->idpes])) {
                continue;
            }

            $resultado[$row->idpes] = self::normalizar([
                'logradouro' => $row->address,
                'numero' => $row->number,
                'bairro' => $row->neighborhood,
                'cep' => $row->postal_code,
                'municipio_ibge' => $row->city_ibge,
                'uf' => $row->uf,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, string>
     */
    private static function normalizar(array $dados): array
    {
        $numero = trim((string) ($dados['numero'] ?? ''));

        return [
            'logradouro' => trim((string) ($dados['logradouro'] ?? '')),
            'numero' => $numero !== '' ? mb_substr($numero, 0, 6) : '00',
            'bairro' => trim((string) ($dados['bairro'] ?? '')),
            'cep' => SgpCodeMappers::cep($dados['cep'] ?? null),
            'municipio_ibge' => SgpCodeMappers::municipioIbge($dados['municipio_ibge'] ?? null),
            'uf_ibge' => SgpCodeMappers::ufIbge($dados['uf'] ?? null),
        ];
    }
}
