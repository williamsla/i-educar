<?php

class clsPmieducarFuncao
{
    public function lista()
    {
        $retorno = [];
        $db = new clsBanco;

        $db->Consulta('SELECT cod_funcao, nm_funcao FROM pmieducar.funcao WHERE ativo=1 ORDER BY nm_funcao ASC;');

        while ($db->ProximoRegistro()) {
            $item = $db->Tupla();

            $retorno[$item['cod_funcao']] = $item['nm_funcao'];
        }

        return $retorno;
    }
}
