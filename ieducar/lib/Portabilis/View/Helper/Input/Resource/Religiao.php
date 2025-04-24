<?php

use App\Models\Religion;

class Portabilis_View_Helper_Input_Resource_Religiao extends Portabilis_View_Helper_Input_CoreSelect
{
    protected function inputOptions($options)
    {
        $resources = $options['resources'];

        if (empty($options['resources'])) {
            // $resources = Religion::query()
            //     ->orderBy('nm_religiao')
            //     ->pluck('nm_religiao', 'cod_religiao')
            //     ->prepend('Selecione', '');
        }

        return $this->insertOption(null, 'Religião', $resources);
    }

    public function religiao($options = [])
    {
        parent::select($options);
    }
}
