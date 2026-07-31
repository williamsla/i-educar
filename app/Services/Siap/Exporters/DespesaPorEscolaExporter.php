<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DespesaPorEscolaExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'DespesaPorEscola';
    }

    public function export(): string
    {
        $builder = $this->builder();

        if (class_exists(\iEducar\Packages\DespesaEscolar\Services\Siap\DespesaPorEscolaSiapExporter::class)) {
            return (new \iEducar\Packages\DespesaEscolar\Services\Siap\DespesaPorEscolaSiapExporter(
                $this->codigo,
                $this->ano,
                $this->mes
            ))->export($this->alerts);
        }

        if (!Schema::hasTable('despesas_escolares')) {
            $this->alert('Módulo despesas-escolar não instalado — DespesaPorEscola.xml apenas com cabeçalho.');

            return $builder->toFormattedXml();
        }

        $despesas = DB::table('despesas_escolares as d')
            ->join('fornecedores as f', 'f.id', '=', 'd.fornecedor_id')
            ->where('d.ano', $this->ano)
            ->where('d.mes', $this->mes)
            ->whereNotNull('d.inep')
            ->select(
                'd.inep',
                'd.matricula_responsavel',
                'd.tipo_despesa',
                'd.observacao',
                'd.numero_nota_fiscal',
                'f.cnpj as fornecedor',
                'd.numero_empenho',
                'd.numero_processo',
                'd.quantidade',
                'd.unidade_medida',
                'd.valor'
            )
            ->orderBy('d.inep')
            ->get();

        foreach ($despesas as $despesa) {
            $fornecedor = SiapCodeMappers::apenasDigitos($despesa->fornecedor);
            $objeto = trim((string) ($despesa->observacao ?: 'DESPESA ESCOLAR'));
            $matricula = trim((string) ($despesa->matricula_responsavel ?: '0'));
            $nota = substr((string) $despesa->numero_nota_fiscal, 0, 10);

            if ($matricula === '') {
                $this->alert("Despesa NF {$nota} sem MatriculaResponsavel — usando 0.");
                $matricula = '0';
            }

            $builder->addRecord('DespesaPorEscola', [
                'INEP' => SiapCodeMappers::apenasDigitos($despesa->inep),
                'MatriculaResponsavel' => $matricula,
                'TipoDespesa' => SiapCodeMappers::tipoDespesa((string) $despesa->tipo_despesa),
                'Objeto' => mb_substr($objeto, 0, 1024),
                'NotaFiscal' => $nota,
                'Fornecedor' => $fornecedor,
                'NumeroEmpenho' => (string) ($despesa->numero_empenho ?: '000'),
                'NumeroProcesso' => (string) ($despesa->numero_processo ?: '000'),
                'Quantidade' => number_format((float) $despesa->quantidade, 2, '.', ''),
                'UnidadeMedida' => (string) ($despesa->unidade_medida ?: 'UNIDADE'),
                'Valor' => number_format((float) $despesa->valor, 2, '.', ''),
            ]);
        }

        if ($despesas->isEmpty()) {
            $this->alert('Nenhuma despesa encontrada para o período.');
        }

        return $builder->toFormattedXml();
    }
}
