<?php

namespace Tests\Unit\Modules\Addressing;

use App\Models\PersonHasPlace;
use App\Models\Place;
use Database\Factories\LegacyPersonFactory;
use Database\Factories\PlaceFactory;
use iEducar\Modules\Addressing\LegacyAddressingFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LegacyAddressingFieldsTest extends TestCase
{
    use DatabaseTransactions;

    public function testSaveAddressDoesNotUpdateSharedPlace(): void
    {
        $person1 = LegacyPersonFactory::new()->create();
        $person2 = LegacyPersonFactory::new()->create();

        $sharedPlace = PlaceFactory::new()->create([
            'address' => 'Rua Original',
        ]);

        PersonHasPlace::query()->create([
            'person_id' => $person1->idpes,
            'place_id' => $sharedPlace->id,
            'type' => 1,
        ]);

        PersonHasPlace::query()->create([
            'person_id' => $person2->idpes,
            'place_id' => $sharedPlace->id,
            'type' => 1,
        ]);

        $handler = new class($sharedPlace) {
            use LegacyAddressingFields;

            public function __construct(private Place $sharedPlace)
            {
            }

            public function save(int $personId): void
            {
                $this->address = 'Rua Nova';
                $this->number = '123';
                $this->complement = null;
                $this->neighborhood = 'Centro';
                $this->city_id = $this->sharedPlace->city_id;
                $this->postal_code = '12345678';

                $this->saveAddress($personId, optionalFields: true);
            }
        };

        $handler->save($person1->idpes);

        $sharedPlace->refresh();

        $this->assertSame('Rua Original', $sharedPlace->address);

        $person1Place = PersonHasPlace::query()->where('person_id', $person1->idpes)->first();
        $person2Place = PersonHasPlace::query()->where('person_id', $person2->idpes)->first();

        $this->assertNotSame($person1Place->place_id, $person2Place->place_id);
        $this->assertSame('Rua Nova', Place::query()->find($person1Place->place_id)->address);
        $this->assertSame('Rua Original', Place::query()->find($person2Place->place_id)->address);
    }

    public function testSaveAddressUpdatesPlaceWhenNotShared(): void
    {
        $person = LegacyPersonFactory::new()->create();

        $place = PlaceFactory::new()->create([
            'address' => 'Rua Original',
        ]);

        PersonHasPlace::query()->create([
            'person_id' => $person->idpes,
            'place_id' => $place->id,
            'type' => 1,
        ]);

        $handler = new class($place) {
            use LegacyAddressingFields;

            public function __construct(private Place $place)
            {
            }

            public function save(int $personId): void
            {
                $this->address = 'Rua Atualizada';
                $this->number = '456';
                $this->complement = null;
                $this->neighborhood = 'Centro';
                $this->city_id = $this->place->city_id;
                $this->postal_code = '12345678';

                $this->saveAddress($personId, optionalFields: true);
            }
        };

        $handler->save($person->idpes);

        $place->refresh();

        $this->assertSame('Rua Atualizada', $place->address);
        $this->assertSame(
            $place->id,
            PersonHasPlace::query()->where('person_id', $person->idpes)->value('place_id')
        );
    }
}
