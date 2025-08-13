<?php

namespace App\Http\Controllers\API\Petshop;

use App\Http\Controllers\Controller;
use App\Models\Petshop\PetShopPet;
use App\Services\Petshop\PetShopPetService;
use App\Http\Requests\Petshop\PetShopPet\StorePetShopPetRequest;
use App\Http\Requests\Petshop\PetShopPet\UpdatePetShopPetRequest;
use Illuminate\Http\Request;
use App\Http\Resources\Petshop\PetShopPetResource;

class PetShopPetController extends Controller
{
    public function __construct(private PetShopPetService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->paginate($request->all(), (int) $request->get('per_page', 15));
        $data->getCollection()->transform(fn($item) => new PetShopPetResource($item));
        return $data;
    }

    public function store(StorePetShopPetRequest $request)
    {
        $model = $this->service->create($request->validated());
        return (new PetShopPetResource($model))->response()->setStatusCode(201);
    }

    public function show(PetShopPet $model)
    {
        return new PetShopPetResource($model);
    }

    public function update(PetShopPet $model, UpdatePetShopPetRequest $request)
    {
        $model = $this->service->update($model, $request->validated());
        return new PetShopPetResource($model);
    }

    public function destroy(PetShopPet $model)
    {
        $this->service->delete($model);
        return response()->noContent();
    }
}
