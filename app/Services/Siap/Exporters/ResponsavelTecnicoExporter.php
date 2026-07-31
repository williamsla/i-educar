<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResponsavelTecnicoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'ResponsavelTecnico';
    }

    public function export(): string
    {
        $builder = $this->builder();

        if (class_exists(\Merenda\Services\Siap\ResponsavelTecnicoSiapExporter::class)) {
            return (new \Merenda\Services\Siap\ResponsavelTecnicoSiapExporter(
                $this->codigo,
                $this->ano,
                $this->mes
            ))->export($this->alerts);
        }

        if (!Schema::hasTable('merenda_config_prefeitura') && !Schema::hasTable('merenda_cardapios')) {
            $this->alert('Sem dados de responsável técnico de merenda — arquivo apenas com cabeçalho.');

            return $builder->toFormattedXml();
        }

        $nome = null;
        $crn = null;
        $cpf = null;
        $sigpnae = null;
        $matricula = null;
        $portaria = null;
        $contrato = null;
        $tipoVinculo = '1';
        $quadro = '1';
        $planoAnual = '2';
        $visitas = '0';

        if (Schema::hasTable('merenda_config_prefeitura')) {
            $configs = DB::table('merenda_config_prefeitura')->pluck('valor', 'chave');
            $nome = $configs['nutricionista_padrao'] ?? $configs['siap_rt_nome'] ?? null;
            $crn = $configs['crn_padrao'] ?? $configs['siap_rt_crn'] ?? null;
            $cpf = $configs['siap_rt_cpf'] ?? null;
            $sigpnae = $configs['siap_rt_sigpnae'] ?? null;
            $matricula = $configs['siap_rt_matricula'] ?? null;
            $portaria = $configs['siap_rt_portaria'] ?? null;
            $contrato = $configs['siap_rt_contrato'] ?? null;
            $tipoVinculo = $configs['siap_rt_tipo_vinculo'] ?? '1';
            $quadro = $configs['siap_rt_quadro_tecnico'] ?? '1';
            $planoAnual = $configs['siap_rt_plano_anual'] ?? '2';
            $visitas = $configs['siap_rt_visitas'] ?? '0';
        }

        if ((!$nome || !$crn) && Schema::hasTable('merenda_cardapios')) {
            $cardapio = DB::table('merenda_cardapios')
                ->whereNull('deleted_at')
                ->whereNotNull('nutricionistas')
                ->orderByDesc('id')
                ->value('nutricionistas');

            $lista = is_string($cardapio) ? json_decode($cardapio, true) : $cardapio;
            if (is_array($lista) && !empty($lista[0])) {
                $nome = $nome ?: ($lista[0]['nome'] ?? null);
                $crn = $crn ?: ($lista[0]['crn'] ?? null);
            }
        }

        if (!$nome || !$crn) {
            $this->alert('Responsável técnico incompleto (nome/CRN). Configure merenda_config_prefeitura (siap_rt_*).');

            return $builder->toFormattedXml();
        }

        $cpfLimpo = SiapCodeMappers::cpf($cpf);
        if (strlen($cpfLimpo) !== 11) {
            $this->alert('CPF do responsável técnico inválido/ausente — registro omitido. Configure siap_rt_cpf.');

            return $builder->toFormattedXml();
        }

        $builder->addRecord('ResponsavelTecnico', [
            'NumeroSIGPNAE' => (string) ($sigpnae ?: $cpfLimpo),
            'Nome' => mb_substr((string) $nome, 0, 255),
            'CPF' => $cpfLimpo,
            'NumeroCRN' => (string) $crn,
            'TipoVinculo' => in_array($tipoVinculo, ['1', '2', '3'], true) ? $tipoVinculo : '1',
            'Matricula' => (string) ($matricula ?? ''),
            'NumeroPortariaNomeacao' => (string) ($portaria ?? ''),
            'NumeroContrato' => (string) ($contrato ?? ''),
            'QuantidadeQuadroTecnico' => (string) max(1, (int) $quadro),
            'PossuiPlanoAnualTrabalho' => in_array($planoAnual, ['1', '2'], true) ? $planoAnual : '2',
            'QuantidadeVisitasRealizadas' => (string) max(0, (int) $visitas),
        ]);

        return $builder->toFormattedXml();
    }
}
