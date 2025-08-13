<?php

namespace Tests\Unit\Petshop;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Petshop\PetShopPetService;
use App\Models\Petshop\PetShopPet;

class PetShopPetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginate_returns_paged(): void
    {
        PetShopPet::factory()->count(3)->create();
        $s = new PetShopPetService();
        $res = $s->paginate([], 2);
        $this->assertEquals(2, $res->perPage());
        $this->assertGreaterThanOrEqual(1, $res->total());
    }

    public function test_create_update_delete_flow(): void
    {
        $s = new PetShopPetService();
        $payload = PetShopPet::factory()->make()->toArray();
        $created = $s->create($payload);
        $this->assertDatabaseHas('pet_shop_pets', ['id' => $created->getKey()]);

        $changes = PetShopPet::factory()->make()->toArray();
        $updated = $s->update($created, $changes);
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at'])) continue;
            $this->assertDatabaseHas('pet_shop_pets', [$k => $v]);
        }

        $s->delete($updated);
        $this->assertSoftDeleted('pet_shop_pets', ['id' => $updated->getKey()]);
    }
}
