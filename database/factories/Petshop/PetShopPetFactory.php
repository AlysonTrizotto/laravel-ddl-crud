<?php

namespace Database\Factories\Petshop;

use App\Models\Petshop\PetShopPet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PetShopPet>
 */
class PetShopPetFactory extends Factory
{
    protected $model = PetShopPet::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => fake()->text(50),
            'species' => fake()->text(50),
            'breed' => fake()->text(50),
            'birth_date' => fake()->date(),
            'weight' => fake()->randomFloat(2, 0, 200),
            'owner_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'vaccinated' => fake()->boolean(),
        ];
    }
}
