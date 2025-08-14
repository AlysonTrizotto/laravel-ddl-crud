<?php

namespace Database\Factories\User;

use App\Models\User\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<App\Models\User\Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = \App\Models\User\Usuario::class;

    public function definition(): array
    {
        return [
            'id' => fake()->word(),
            'name' => fake()->text(50),
            'email' => fake()->text(50),
            'password' => fake()->text(50)
        ];
    }
}
