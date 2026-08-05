<?php

class ViewController extends Core_Controller_Page_ViewController
{
    protected $_dataMapper = 'AreaConhecimento_Model_AreaDataMapper';

    protected $_titulo = 'Detalhes de área de conhecimento';

    protected $_processoAp = 945;

    protected $_tableMap = [
        'Nome' => 'nome',
        'Seção' => 'secao',
        'Agrupa descritores' => 'agrupar_descritores',
        'Disciplina vinculada' => 'componente_vinculo',
    ];

    protected function _preRender()
    {
        parent::_preRender();

        $this->breadcrumb('Detalhe da área de conhecimento', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
    }

    public function getEntry()
    {
        $area = $this->getDataMapper()->find($this->getRequest()->id);
        $componenteVinculoId = $area->componente_vinculo;
        $area->agrupar_descritores = $area->agrupar_descritores ? 'Sim' : 'Não';
        $area->componente_vinculo = '-';

        if ($componenteVinculoId) {
            $mapper = new ComponenteCurricular_Model_ComponenteDataMapper;
            $componente = $mapper->find(['id' => $componenteVinculoId]);
            $area->componente_vinculo = $componente->nome;
        }

        return $area;
    }
}
