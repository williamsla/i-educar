<?php

namespace Database\Factories;

use App\Models\Religion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReligionFactory extends Factory
{
    protected $model = Religion::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Católica',
                'Evangélica',
                'Espírita',
                'Umbanda',
                'Candomblé',
                'Outra',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
