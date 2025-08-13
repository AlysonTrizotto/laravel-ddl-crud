<?php

namespace Tests\Feature\Checklist;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Checklist\PhotoAnnotation;
use Illuminate\Support\Str;

class PhotoAnnotationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok(): void
    {
        PhotoAnnotation::factory()->count(2)->create();
        $res = $this->getJson('/api/photo-annotations');
        $res->assertStatus(200);
    }

    public function test_store_validates_payload(): void
    {
        $res = $this->postJson('/api/photo-annotations', []);
        $res->assertStatus(422);
    }

    public function test_store_creates_record(): void
    {
        $payload = PhotoAnnotation::factory()->make()->toArray();
        $res = $this->postJson('/api/photo-annotations', $payload);
        $res->assertCreated()->assertJsonStructure(['data']);
        $this->assertDatabaseHas('photo_annotations', ['id' => $payload['id'] ?? null] + collect($payload)->except(['metadata'])->toArray());
    }

    public function test_show_returns_record(): void
    {
        $model = PhotoAnnotation::factory()->create();
        $res = $this->getJson('/api/photo-annotations/' . $model->getKey());
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_modifies_record(): void
    {
        $model = PhotoAnnotation::factory()->create();
        $changes = PhotoAnnotation::factory()->make()->toArray();
        $res = $this->putJson('/api/photo-annotations/' . $model->getKey(), $changes);
        $res->assertOk();
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at','metadata'])) continue;
            $this->assertDatabaseHas('photo_annotations', [$k => $v]);
        }
    }

    public function test_destroy_deletes_record(): void
    {
        $model = PhotoAnnotation::factory()->create();
        $res = $this->deleteJson('/api/photo-annotations/' . $model->getKey());
        $res->assertNoContent();
        $this->assertSoftDeleted('photo_annotations', ['id' => $model->getKey()]);
    }
}
