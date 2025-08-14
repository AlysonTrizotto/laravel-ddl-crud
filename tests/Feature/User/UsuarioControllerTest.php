<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User\Usuario;
use Illuminate\Support\Str;

class UsuarioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok(): void
    {
        Usuario::factory()->count(2)->create();
        $res = $this->getJson('/api/usuarios');
        $res->assertStatus(200);
    }

    public function test_store_validates_payload(): void
    {
        $res = $this->postJson('/api/usuarios', []);
        $res->assertStatus(422);
    }

    public function test_store_creates_record(): void
    {
        $payload = Usuario::factory()->make()->toArray();
        $res = $this->postJson('/api/usuarios', $payload);
        $res->assertCreated()->assertJsonStructure(['data']);
        $this->assertDatabaseHas('usuario', ['id' => $payload['id'] ?? null] + collect($payload)->only(array_keys($payload))->toArray());
    }

    public function test_show_returns_record(): void
    {
        $model = Usuario::factory()->create();
        $res = $this->getJson('/api/usuarios/' . $model->getKey());
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_modifies_record(): void
    {
        $model = Usuario::factory()->create();
        $changes = Usuario::factory()->make()->toArray();
        $res = $this->putJson('/api/usuarios/' . $model->getKey(), $changes);
        $res->assertOk();
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at'])) continue;
            $this->assertDatabaseHas('usuario', [$k => $v]);
        }
    }

    public function test_destroy_deletes_record(): void
    {
        $model = Usuario::factory()->create();
        $res = $this->deleteJson('/api/usuarios/' . $model->getKey());
        $res->assertNoContent();
        $this->assertSoftDeleted('usuario', ['id' => $model->getKey()]);
    }
}
