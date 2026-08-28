<?php

namespace App\Services\Siap\Exporters;

use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;

class FaltasProfissionalEducacaoExporter extends AbstractSiapExporter
{
    public function fileName(): string
    {
        return 'FaltasProfissionalEducacao';
    }

    public function export(): string
    {
        $builder = $this->builder();
        $inicio = $this->inicioMes();
        $fim = $this->fimMes();

        try {
            DB::table('pmieducar.falta_atraso')->limit(1)->exists();
        } catch (\Throwable $e) {
            $this->alert('Tabela de faltas não encontrada — arquivo exportado vazio.');

            return $builder->toFormattedXml();
        }

        $alocacoes = DB::table('pmieducar.servidor_alocacao as sa')
            ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
            ->join('pmieducar.escola as e', 'e.cod_escola', '=', 'sa.ref_cod_escola')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'sa.ref_cod_escola')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 's.cod_servidor')
            ->where('sa.ativo', 1)
            ->where('s.ativo', 1)
            ->where('sa.ano', $this->ano)
            ->where('e.ref_cod_instituicao', $this->instituicaoId)
            ->whereNotNull('f.cpf')
            ->select(
                's.cod_servidor',
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula'
            )
            ->distinct()
            ->get();

        foreach ($alocacoes as $alocacao) {
            $cpf = SiapCodeMappers::cpf($alocacao->cpf);
            if ($cpf === '') {
                continue;
            }

            // No i-Educar: justificada = 0 → Sim; qualquer outro → Não
            $faltas = DB::table('pmieducar.falta_atraso')
                ->where('ref_cod_servidor', $alocacao->cod_servidor)
                ->whereBetween('data_falta_atraso', [$inicio, $fim])
                ->selectRaw('SUM(CASE WHEN justificada = 0 THEN 1 ELSE 0 END) as justificadas')
                ->selectRaw('SUM(CASE WHEN justificada <> 0 THEN 1 ELSE 0 END) as injustificadas')
                ->first();

            $licencaMedica = 0;
            $licencaMaternidade = 0;

            try {
                $afastamentos = DB::table('pmieducar.servidor_afastamento as af')
                    ->leftJoin('pmieducar.motivo_afastamento as ma', 'ma.cod_motivo_afastamento', '=', 'af.ref_cod_motivo_afastamento')
                    ->where('af.ref_cod_servidor', $alocacao->cod_servidor)
                    ->where(function ($q) use ($inicio, $fim) {
                        $q->whereDate('af.data_retorno', '>=', $inicio)
                            ->orWhereNull('af.data_retorno');
                    })
                    ->whereDate('af.data_saida', '<=', $fim)
                    ->select('af.data_saida', 'af.data_retorno', 'ma.nm_motivo')
                    ->get();

                foreach ($afastamentos as $af) {
                    $dias = $this->diasNoMes($af->data_saida, $af->data_retorno, $inicio, $fim);
                    $motivo = mb_strtolower((string) ($af->nm_motivo ?? ''));
                    if (str_contains($motivo, 'matern') || str_contains($motivo, 'patern')) {
                        $licencaMaternidade += $dias;
                    } elseif (str_contains($motivo, 'médic') || str_contains($motivo, 'medic') || str_contains($motivo, 'saúde') || str_contains($motivo, 'saude')) {
                        $licencaMedica += $dias;
                    }
                }
            } catch (\Throwable $e) {
                // afastamento opcional
            }

            $justificadas = (int) ($faltas->justificadas ?? 0);
            $injustificadas = (int) ($faltas->injustificadas ?? 0);

            if ($justificadas + $injustificadas + $licencaMedica + $licencaMaternidade === 0) {
                continue;
            }

            $builder->addRecord('FaltasProfissionalEducacao', [
                'INEP' => (string) $alocacao->inep,
                'CPF' => $cpf,
                'Matricula' => (string) ($alocacao->matricula ?? ''),
                'FaltasJustificadas' => (string) min(999, $justificadas),
                'FaltasInjustificadas' => (string) min(999, $injustificadas),
                'LicencaMedica' => (string) min(999, $licencaMedica),
                'LicencaMaternidadePaternidade' => (string) min(999, $licencaMaternidade),
                'Abonos' => '0',
                'OutrasFaltas' => '0',
            ]);
        }

        return $builder->toFormattedXml();
    }

    private function diasNoMes($saida, $retorno, string $inicioMes, string $fimMes): int
    {
        $ini = max(strtotime($inicioMes), strtotime($saida));
        $fim = min(strtotime($fimMes), $retorno ? strtotime($retorno) : strtotime($fimMes));

        if ($fim < $ini) {
            return 0;
        }

        return (int) floor(($fim - $ini) / 86400) + 1;
    }
}
