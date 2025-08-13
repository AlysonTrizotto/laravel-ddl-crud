<?php

namespace Tests\Unit\Checklist;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Checklist\PhotoAnnotationService;
use App\Models\Checklist\PhotoAnnotation;

class PhotoAnnotationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginate_returns_paged(): void
    {
        PhotoAnnotation::factory()->count(3)->create();
        $s = new PhotoAnnotationService();
        $res = $s->paginate([], 2);
        $this->assertEquals(2, $res->perPage());
        $this->assertGreaterThanOrEqual(1, $res->total());
    }

    public function test_create_update_delete_flow(): void
    {
        $s = new PhotoAnnotationService();
        $payload = PhotoAnnotation::factory()->make()->toArray();
        $created = $s->create($payload);
        $this->assertDatabaseHas('photo_annotations', ['id' => $created->getKey()]);

        $changes = PhotoAnnotation::factory()->make()->toArray();
        $updated = $s->update($created, $changes);
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at','metadata'])) continue;
            $this->assertDatabaseHas('photo_annotations', [$k => $v]);
        }

        $s->delete($updated);
        $this->assertSoftDeleted('photo_annotations', ['id' => $updated->getKey()]);
    }
}
