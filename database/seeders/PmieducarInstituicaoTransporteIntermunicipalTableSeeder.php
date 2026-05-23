<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// SEEDER PARA A INSTITUIÇÃO DE TRANSPORTE INTERMUNICIPAL
// php artisan db:seed --class=PmieducarInstituicaoTransporteIntermunicipalTableSeeder --force

class PmieducarInstituicaoTransporteIntermunicipalTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('pmieducar.instituicao')->insert([
            'cod_instituicao' => 2,
            'ref_usuario_cad' => 1,
            'ref_idtlog' => 'RUA',
            'ref_sigla_uf' => 'AL',
            'cep' => 57000000,
            'cidade' => 'Transporte Intermunicipal',
            'bairro' => 'Transporte Intermunicipal',
            'logradouro' => 'Transporte Intermunicipal',
            'nm_responsavel' => 'Transporte Intermunicipal',
            'data_cadastro' => now(),
            'nm_instituicao' => 'Transporte Intermunicipal',
        ]);
    }
}
