<?php

namespace Tests\Feature\Petshop;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Petshop\PetShopPet;
use Illuminate\Support\Str;

class PetShopPetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok(): void
    {
        PetShopPet::factory()->count(2)->create();
        $res = $this->getJson('/api/pet-shop-pets');
        $res->assertStatus(200);
    }

    public function test_store_validates_payload(): void
    {
        $res = $this->postJson('/api/pet-shop-pets', []);
        $res->assertStatus(422);
    }

    public function test_store_creates_record(): void
    {
        $payload = PetShopPet::factory()->make()->toArray();
        $res = $this->postJson('/api/pet-shop-pets', $payload);
        $res->assertCreated()->assertJsonStructure(['data']);
        $this->assertDatabaseHas('pet_shop_pets', ['id' => $payload['id'] ?? null] + collect($payload)->only(array_keys($payload))->toArray());
    }

    public function test_show_returns_record(): void
    {
        $model = PetShopPet::factory()->create();
        $res = $this->getJson('/api/pet-shop-pets/' . $model->getKey());
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_modifies_record(): void
    {
        $model = PetShopPet::factory()->create();
        $changes = PetShopPet::factory()->make()->toArray();
        $res = $this->putJson('/api/pet-shop-pets/' . $model->getKey(), $changes);
        $res->assertOk();
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at'])) continue;
            $this->assertDatabaseHas('pet_shop_pets', [$k => $v]);
        }
    }

    public function test_destroy_deletes_record(): void
    {
        $model = PetShopPet::factory()->create();
        $res = $this->deleteJson('/api/pet-shop-pets/' . $model->getKey());
        $res->assertNoContent();
        $this->assertSoftDeleted('pet_shop_pets', ['id' => $model->getKey()]);
    }
}
