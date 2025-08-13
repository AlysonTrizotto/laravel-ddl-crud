<?php

namespace Tests\Unit\Checklist;

use Tests\TestCase;
use App\Models\Checklist\PhotoAnnotation;

class PhotoAnnotationModelTest extends TestCase
{
    public function test_model_configuration_is_correct(): void
    {
        $m = new PhotoAnnotation();
        $this->assertIsArray($m->getFillable());
        $this->assertNotEmpty($m->getFillable());
        $this->assertIsArray($m->getCasts());
        $this->assertIsString($m->getTable());
        $this->assertTrue(property_exists($m, 'primaryKey'));
        $this->assertTrue(property_exists($m, 'incrementing'));
        $this->assertTrue(property_exists($m, 'keyType'));
    }

    public function test_has_factory_trait(): void
    {
        $traits = class_uses(PhotoAnnotation::class);
        $this->assertIsArray($traits);
        $this->assertArrayHasKey('Illuminate\\Database\\Eloquent\\Factories\\HasFactory', $traits);
    }
}
