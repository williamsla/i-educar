<?php

class Portabilis_View_Helper_DynamicInput_Escola extends Portabilis_View_Helper_DynamicInput_CoreSelect
{
    protected function inputValue($value = null)
    {
        return $this->getEscolaId($value);
    }

    protected function inputName()
    {
        return 'ref_cod_escola';
    }

    protected function inputOptions($options)
    {
        $resources = $options['resources'];
        $instituicaoId = $this->getInstituicaoId($options['instituicaoId'] ?? null);
        $userId = $this->getCurrentUserId();
        $ano = is_numeric($options['ano'] ?? null) ? (int) $options['ano'] : null;

        if ($instituicaoId && empty($resources)) {
            $permissao = new clsPermissoes;
            $nivel = $permissao->nivel_acesso($userId);

            if (
                $nivel == App_Model_NivelTipoUsuario::ESCOLA
                || $nivel == App_Model_NivelTipoUsuario::BIBLIOTECA
            ) {
                $escolas_usuario = [];
                $escolasUser = App_Model_IedFinder::getEscolasUser($userId);

                foreach ($escolasUser as $e) {
                    $escolas_usuario[$e['ref_cod_escola']] = $e['nome'];
                }

                if ($ano) {
                    $escolas_usuario = App_Model_IedFinder::filtrarEscolasAnoLetivoAtivo($escolas_usuario, $ano);
                }

                return $this->insertOption(null, 'Selecione uma escola', $escolas_usuario);
            }

            $resources = App_Model_IedFinder::getEscolas($instituicaoId, $ano);
        }

        return $this->insertOption(null, 'Selecione uma escola', $resources);
    }

    public function escola($options = [])
    {
        $this->select($options);

        if (!empty($options['filtraAnoLetivo'])) {
            Portabilis_View_Helper_Application::embedJavascript(
                $this->viewInstance,
                "var campoEscola = document.getElementById('ref_cod_escola'); if (campoEscola) { campoEscola.setAttribute('filtra_ano_letivo', 'true'); }"
            );
        }

        Portabilis_View_Helper_Application::loadChosenLib($this->viewInstance);
        Portabilis_View_Helper_Application::loadJavascript($this->viewInstance, '/vendor/legacy/DynamicInput/Assets/Javascripts/Escola.js');
    }
}
