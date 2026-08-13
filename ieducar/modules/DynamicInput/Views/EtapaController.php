<?php

class EtapaController extends ApiCoreController
{
    protected function canGetEtapas()
    {
        return $this->validatesId('escola') &&
           $this->validatesId('curso') &&
           $this->validatesId('turma') &&
           $this->validatesPresenceOf('ano');
    }

    protected function canGetEtapasEscola()
    {
        return $this->validatesId('escola');
    }

    protected function getEtapas()
    {
        if ($this->canGetEtapas()) {
            $cursoId = $this->getRequest()->curso_id;

            $sql = 'select padrao_ano_escolar from pmieducar.curso where cod_curso = $1 and ativo = 1';
            $padraoAnoLetivo = $this->fetchPreparedQuery($sql, [$cursoId], true, 'first-field');

            if ($padraoAnoLetivo == 1) {
                $escolaId = $this->getRequest()->escola_id;
                $ano = $this->getRequest()->ano;

                $sql = 'select padrao.sequencial as etapa, modulo.nm_tipo as nome from pmieducar.ano_letivo_modulo
                as padrao, pmieducar.modulo where padrao.ref_ano = $1 and padrao.ref_ref_cod_escola = $2
                and padrao.ref_cod_modulo = modulo.cod_modulo and modulo.ativo = 1 order by padrao.sequencial';

                $etapas = $this->fetchPreparedQuery($sql, [$ano, $escolaId]);
            } else {
                $sql = 'select turma.sequencial as etapa, modulo.nm_tipo as nome from pmieducar.turma_modulo as turma,
                pmieducar.modulo where turma.ref_cod_turma = $1 and turma.ref_cod_modulo = modulo.cod_modulo
                and modulo.ativo = 1 order by turma.sequencial';

                $etapas = $this->fetchPreparedQuery($sql, $this->getRequest()->turma_id);
            }

            $etapas = is_array($etapas) ? $etapas : [];

            $options = [];
            foreach ($etapas as $etapa) {
                $options['__' . $etapa['etapa']] = $etapa['etapa'] . 'º ' . mb_strtoupper($etapa['nome'], 'UTF-8');
            }

            $options = $this->appendReleasedStagesToOptions(
                $options,
                (int) $this->getRequest()->turma_id,
                ($etapas[0] ?? [])['nome'] ?? 'Etapa'
            );

            return ['options' => $options];
        }
    }

    protected function getEtapasEscola()
    {
        if ($this->canGetEtapasEscola()) {
            $escolaId = $this->getRequest()->escola_id;
            $ano = $this->getRequest()->ano;

            $sql = 'select padrao.sequencial as etapa, modulo.nm_tipo as nome from pmieducar.ano_letivo_modulo
                as padrao, pmieducar.modulo where padrao.ref_ano = $1 and padrao.ref_ref_cod_escola = $2
                and padrao.ref_cod_modulo = modulo.cod_modulo and modulo.ativo = 1 order by padrao.sequencial';

            $etapas = $this->fetchPreparedQuery($sql, [$ano, $escolaId]);

            $options = [];
            foreach ($etapas as $etapa) {
                $options['__' . $etapa['etapa']] = $etapa['etapa'] . 'º ' . $this->toUtf8($etapa['nome']);
            }

            return ['options' => $options];
        }
    }

    private function appendReleasedStagesToOptions(array $options, int $schoolClassId, string $stageTypeName): array
    {
        $releasedStages = app(\App\Services\SchoolClassStageService::class)
            ->getReleasedStageNumbers($schoolClassId);

        foreach ($releasedStages as $releasedStage) {
            $key = '__' . $releasedStage;

            if (!isset($options[$key])) {
                $options[$key] = $releasedStage . 'º ' . mb_strtoupper($stageTypeName, 'UTF-8') . ' (lançamentos)';
            }
        }

        uksort($options, static function ($left, $right) {
            return (int) ltrim((string) $left, '_') <=> (int) ltrim((string) $right, '_');
        });

        return $options;
    }

    public function Gerar()
    {
        if ($this->isRequestFor('get', 'etapas')) {
            $this->appendResponse($this->getEtapas());
        } elseif ($this->isRequestFor('get', 'etapasEscola')) {
            $this->appendResponse($this->getEtapasEscola());
        } else {
            $this->notImplementedOperationError();
        }
    }
}
