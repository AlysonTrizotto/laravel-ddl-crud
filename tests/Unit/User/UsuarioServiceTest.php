<?php

namespace Tests\Unit\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\User\UsuarioService;
use App\Models\User\Usuario;

class UsuarioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginate_returns_paged(): void
    {
        Usuario::factory()->count(3)->create();
        $s = new UsuarioService();
        $res = $s->paginate([], 2);
        $this->assertEquals(2, $res->perPage());
        $this->assertGreaterThanOrEqual(1, $res->total());
    }

    public function test_create_update_delete_flow(): void
    {
        $s = new UsuarioService();
        $payload = Usuario::factory()->make()->toArray();
        $created = $s->create($payload);
        $this->assertDatabaseHas('usuario', ['id' => $created->getKey()]);

        $changes = Usuario::factory()->make()->toArray();
        $updated = $s->update($created, $changes);
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at'])) continue;
            $this->assertDatabaseHas('usuario', [$k => $v]);
        }

        $s->delete($updated);
        $this->assertSoftDeleted('usuario', ['id' => $updated->getKey()]);
    }
}
