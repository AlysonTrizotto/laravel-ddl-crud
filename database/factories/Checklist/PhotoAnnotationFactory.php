<?php

namespace Database\Factories\Checklist;

use App\Models\Checklist\PhotoAnnotation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhotoAnnotation>
 */
class PhotoAnnotationFactory extends Factory
{
    protected $model = PhotoAnnotation::class;

    public function definition(): array
    {
        return [
            'id' => fake()->numberBetween(1, 1000),
            'checklist_id' => fake()->numberBetween(1, 1000),
            'label' => fake()->text(50),
            'metadata' => [],
        ];
    }
}
