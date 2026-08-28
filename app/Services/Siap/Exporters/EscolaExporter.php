<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EscolaExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'Escola';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $kitDefault = (string) config('siap.defaults.kit_escolar', '2');
        $dataKitDefault = (string) config('siap.defaults.data_entrega_kit_escolar', '');

        $query = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', 'eal.ref_cod_escola', '=', 'e.cod_escola')
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->where('e.ativo', 1)
            ->where('eal.ativo', 1)
            ->where('eal.ano', $this->ano)
            ->whereNotNull('inep.cod_escola_inep')
            ->select(
                'e.cod_escola',
                'inep.cod_escola_inep as inep',
                DB::raw('COALESCE(p.nome, j.fantasia, e.sigla) as nome_escola'),
                'e.zona_localizacao',
                'e.localizacao_diferenciada',
                'e.situacao_funcionamento',
                'e.conveniada_com_poder_publico',
                'e.poder_publico_parceria_convenio'
            );

        if (Schema::hasColumn('pmieducar.escola', 'kit_escolar')) {
            $query->addSelect('e.kit_escolar', 'e.data_entrega_kit_escolar');
        }

        $this->aplicarFiltroInstituicao($query);

        $escolas = $query->distinct()->get();

        foreach ($escolas as $escola) {
            $endereco = $this->montarEndereco((int) $escola->cod_escola);
            $cep = SiapCodeMappers::cep($endereco['cep'] ?? null);

            if ($cep === '00000000') {
                $this->alert("Escola INEP {$escola->inep} sem CEP válido.");
            }

            $kit = isset($escola->kit_escolar) && $escola->kit_escolar
                ? (string) $escola->kit_escolar
                : $kitDefault;

            $dataKit = '';
            if (!empty($escola->data_entrega_kit_escolar ?? null)) {
                $dataKit = date('Y-m-d', strtotime($escola->data_entrega_kit_escolar));
            } elseif ($kit === '1') {
                $dataKit = $dataKitDefault;
            }

            $builder->addRecord('Escola', [
                'INEP' => (string) $escola->inep,
                'NomeEscola' => mb_substr((string) $escola->nome_escola, 0, 255),
                'Localizacao' => SiapCodeMappers::localizacao($escola->zona_localizacao),
                'LocalizacaoDiferenciada' => SiapCodeMappers::localizacaoDiferenciada($escola->localizacao_diferenciada),
                'EnderecoEscola' => mb_substr($endereco['texto'] ?: 'NÃO INFORMADO', 0, 255),
                'CEP' => $cep,
                'SituacaoEscola' => SiapCodeMappers::situacaoEscola($escola->situacao_funcionamento),
                'ParceriaPoderPublico' => SiapCodeMappers::parceriaPoderPublico(
                    $escola->poder_publico_parceria_convenio ?? $escola->conveniada_com_poder_publico
                ),
                'KitEscolar' => in_array($kit, ['1', '2', '3'], true) ? $kit : '2',
                'DataEntregaKitEscolar' => $dataKit,
            ]);
        }

        if ($escolas->isEmpty()) {
            $this->alert('Nenhuma escola com INEP encontrada para o ano.');
        }

        return $builder->toFormattedXml();
    }

    private function montarEndereco(int $codEscola): array
    {
        try {
            $place = DB::table('pmieducar.escola as e')
                ->join('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
                ->leftJoin('cadastro.pessoa_has_place as php', 'php.person_id', '=', 'p.idpes')
                ->leftJoin('addresses.places as pl', 'pl.id', '=', 'php.place_id')
                ->where('e.cod_escola', $codEscola)
                ->select(
                    'pl.address',
                    'pl.number',
                    'pl.complement',
                    'pl.neighborhood',
                    'pl.postal_code',
                    'pl.city',
                    'pl.state_abbreviation'
                )
                ->first();
        } catch (\Throwable $e) {
            $place = null;
        }

        if (!$place || empty($place->address)) {
            try {
                $place = DB::table('pmieducar.escola as e')
                    ->leftJoin('cadastro.endereco_pessoa as ep', 'ep.idpes', '=', 'e.ref_idpes')
                    ->leftJoin('public.logradouro as l', 'l.idlog', '=', 'ep.idlog')
                    ->leftJoin('public.bairro as b', 'b.idbai', '=', 'ep.idbai')
                    ->leftJoin('public.municipio as m', 'm.idmun', '=', 'b.idmun')
                    ->where('e.cod_escola', $codEscola)
                    ->select(
                        'l.nome as address',
                        'ep.numero as number',
                        'ep.complemento as complement',
                        'b.nome as neighborhood',
                        'ep.cep as postal_code',
                        'm.nome as city',
                        'm.sigla_uf as state_abbreviation'
                    )
                    ->first();
            } catch (\Throwable $e) {
                $place = null;
            }
        }

        if (!$place) {
            return ['texto' => '', 'cep' => ''];
        }

        $partes = array_filter([
            trim(implode(', ', array_filter([
                $place->address ?? null,
                isset($place->number) && $place->number !== null && $place->number !== '' ? (string) $place->number : null,
                $place->complement ?? null,
            ]))),
            $place->neighborhood ?? null,
            trim(($place->city ?? '') . (!empty($place->state_abbreviation) ? ' - ' . $place->state_abbreviation : '')),
        ]);

        return [
            'texto' => implode(' - ', $partes),
            'cep' => $place->postal_code ?? '',
        ];
    }
}
