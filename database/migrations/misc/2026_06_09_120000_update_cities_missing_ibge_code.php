<?php

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $this->createOrUpdate('TO', 'Couto Magalhães', '1706001', 'Couto de Magalhaes');
        $this->createOrUpdate('TO', 'São Valério', '1720499', 'Sao Valerio da Natividade');
        $this->createOrUpdate('MA', 'Pindaré-Mirim', '2108504', 'Pindare Mirim');
        $this->createOrUpdate('PI', 'Aroeiras do Itaim', '2200954', 'Aroeira do Itaim');
        $this->createOrUpdate('PI', 'Nazária', '2206720');
        $this->createOrUpdate('PB', 'Campo de Santana', '2503704');
        $this->createOrUpdate('PB', 'Santarém', '2513806', 'Santarem');
        $this->createOrUpdate('PB', 'São Domingos', '2513968', 'Sao Domingos de Pombal');
        $this->createOrUpdate('PE', 'Belém de São Francisco', '2601607', 'Belem de Sao Francisco');
        $this->createOrUpdate('PE', 'Ilha de Itamaracá', '2607604', 'Itamaraca');
        $this->createOrUpdate('PE', 'Lagoa de Itaenga', '2608503', 'Lagoa do Itaenga');
        $this->createOrUpdate('BA', 'Governador Lomão Júnior', '2916851', 'Governador Lomanto Junior');
        $this->createOrUpdate('MG', 'Brazópolis', '3108909', 'Brasopolis');
        $this->createOrUpdate('MG', 'Passa-Vinte', '3147808', 'Passa Vinte');
        $this->createOrUpdate('MG', 'Pingo-d\'Água', '3150539', 'Pingo D Agua');
        $this->createOrUpdate('MG', 'Sem-Peixe', '3165560', 'Sem Peixe');
        $this->createOrUpdate('MG', 'Tocos do Moji', '3169059', 'Tocos do Mogi');
        $this->createOrUpdate('RJ', 'Paraty', '3303807', 'Parati');
        $this->createOrUpdate('RJ', 'Trajano de Moraes', '3305901', 'Trajano de Morais');
        $this->createOrUpdate('SP', 'Embu das Artes', '3515004', 'Embu');
        $this->createOrUpdate('SP', 'Mogi Mirim', '3530805');
        $this->createOrUpdate('PR', 'Bela Vista da Caroba', '4102752', 'Bela Vista do Caroba');
        $this->createOrUpdate('PR', 'Goioerê', '4108601', 'Goio-ere');
        $this->createOrUpdate('MS', 'Batayporã', '5002001', 'Bataipora');
        $this->createOrUpdate('MT', 'Curvelândia', '5103437', 'Cuverlandia');
    }

    public function createOrUpdate($state_abbreviation, $name, $ibge_code, $old_name = null)
    {
        if (City::query()->where('ibge_code', $ibge_code)->exists()) {
            return;
        }

        $city = City::query()
            ->whereRaw('unaccent(name) ILIKE unaccent(?)', $old_name ?? $name)
            ->whereHas('state', fn ($q) => $q->where('abbreviation', $state_abbreviation))
            ->whereNull('ibge_code')
            ->first();

        if ($city) {
            $city->update(['ibge_code' => $ibge_code, 'name' => $name]);

            return;
        }

        if ($state_id = State::query()->where('abbreviation', $state_abbreviation)->value('id')) {
            City::create(compact('state_id', 'name', 'ibge_code'));
        }
    }

    public function down() {}
};
