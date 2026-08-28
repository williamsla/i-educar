<?php

namespace App\Services\Siap\Exporters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CardapioExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'Cardapio';
    }

    public function export(): string
    {
        $builder = $this->builder();

        if (!Schema::hasTable('merenda_cardapios')) {
            $this->alert('Módulo merenda não instalado — Cardapio.xml apenas com cabeçalho.');

            return $builder->toFormattedXml();
        }

        if (class_exists(\Merenda\Services\Siap\CardapioSiapExporter::class)) {
            return (new \Merenda\Services\Siap\CardapioSiapExporter(
                $this->codigo,
                $this->ano,
                $this->mes
            ))->export($this->alerts);
        }

        $inicio = $this->inicioMes();
        $fim = $this->fimMes();
        $escolasIds = $this->idsEscolasInstituicao();

        $cardapios = DB::table('merenda_cardapios')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($escolasIds) {
                $q->whereNull('ref_cod_escola')
                    ->orWhere('ref_cod_escola', 0)
                    ->orWhereIn('ref_cod_escola', $escolasIds);
            })
            ->where(function ($q) use ($inicio, $fim) {
                $q->where(function ($q2) {
                    $q2->where('ano_referencia', $this->ano)
                        ->where('mes_referencia', $this->mes);
                })->orWhere(function ($q2) use ($inicio, $fim) {
                    $q2->whereDate('data_inicio', '<=', $fim)
                        ->whereDate('data_fim', '>=', $inicio);
                });
            })
            ->where(function ($q) {
                $q->where('status', 'ativo')->orWhereNull('status');
            })
            ->orderBy('id')
            ->get();

        foreach ($cardapios as $cardapio) {
            $metricas = $this->calcularMetricas((int) $cardapio->id, $inicio, $fim);
            $tipoCardapio = (stripos((string) ($cardapio->modalidade ?? ''), 'integral') !== false) ? '1' : '1';
            if (stripos((string) ($cardapio->modalidade ?? ''), 'integral') !== false) {
                $tipoCardapio = '2';
            }
            $especial = ((string) ($cardapio->tipo_refeicao ?? '') === 'merenda_especial') ? '1' : '2';

            $builder->addRecord('Cardapio', [
                'Codigo' => (string) $cardapio->id,
                'RealizadoTesteAceitabilidade' => '2',
                'QuantidadeEscolasTestadas' => '0',
                'QuantidadePreparacoes' => (string) $metricas['preparacoes'],
                'PercentualAceitacao' => '100',
                'QuantidadeDiasOferta' => (string) max(1, $metricas['dias_oferta']),
                'QuantidadeDiasFruta' => (string) $metricas['dias_fruta'],
                'QuantidadeDiasLegumesVerduras' => (string) $metricas['dias_legumes'],
                'TipoCardapio' => $tipoCardapio,
                'CardapioParaNecessidadesEspeciais' => $especial,
            ]);
        }

        if ($cardapios->isEmpty()) {
            $this->alert('Nenhum cardápio encontrado para o período.');
        }

        return $builder->toFormattedXml();
    }

    private function calcularMetricas(int $cardapioId, string $inicio, string $fim): array
    {
        $preparacoes = 0;
        $diasOferta = 0;
        $diasFruta = 0;
        $diasLegumes = 0;

        if (Schema::hasTable('merenda_cardapio_refeicoes')) {
            $preparacoes = (int) DB::table('merenda_cardapio_refeicoes')
                ->where('merenda_cardapio_id', $cardapioId)
                ->whereBetween('data', [$inicio, $fim])
                ->distinct('merenda_refeicao_id')
                ->count('merenda_refeicao_id');

            $diasOferta = (int) DB::table('merenda_cardapio_refeicoes')
                ->where('merenda_cardapio_id', $cardapioId)
                ->whereBetween('data', [$inicio, $fim])
                ->selectRaw('COUNT(DISTINCT DATE(data)) as total')
                ->value('total');

            if (Schema::hasTable('merenda_refeicao_ingrediente') && Schema::hasTable('merenda_produtos')) {
                $diasFruta = (int) DB::table('merenda_cardapio_refeicoes as cr')
                    ->join('merenda_refeicao_ingrediente as ri', 'ri.merenda_refeicao_id', '=', 'cr.merenda_refeicao_id')
                    ->join('merenda_produtos as p', 'p.id', '=', 'ri.merenda_produto_id')
                    ->where('cr.merenda_cardapio_id', $cardapioId)
                    ->whereBetween('cr.data', [$inicio, $fim])
                    ->where('p.categoria', 'frutas')
                    ->selectRaw('COUNT(DISTINCT DATE(cr.data)) as total')
                    ->value('total');

                $diasLegumes = (int) DB::table('merenda_cardapio_refeicoes as cr')
                    ->join('merenda_refeicao_ingrediente as ri', 'ri.merenda_refeicao_id', '=', 'cr.merenda_refeicao_id')
                    ->join('merenda_produtos as p', 'p.id', '=', 'ri.merenda_produto_id')
                    ->where('cr.merenda_cardapio_id', $cardapioId)
                    ->whereBetween('cr.data', [$inicio, $fim])
                    ->where('p.categoria', 'legumes_verduras')
                    ->selectRaw('COUNT(DISTINCT DATE(cr.data)) as total')
                    ->value('total');
            }
        }

        if ($preparacoes === 0 && Schema::hasTable('merenda_cardapio_refeicoes')) {
            $preparacoes = (int) DB::table('merenda_cardapio_refeicoes')
                ->where('merenda_cardapio_id', $cardapioId)
                ->distinct('merenda_refeicao_id')
                ->count('merenda_refeicao_id');
        }

        if ($diasOferta === 0) {
            $diasOferta = (int) date('t', strtotime($inicio));
        }

        return [
            'preparacoes' => max(1, $preparacoes),
            'dias_oferta' => $diasOferta,
            'dias_fruta' => $diasFruta,
            'dias_legumes' => $diasLegumes,
        ];
    }
}
