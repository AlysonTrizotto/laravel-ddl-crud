<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\User\Usuario;
use App\Services\User\UsuarioService;
use App\Http\Requests\User\Usuario\StoreUsuarioRequest;
use App\Http\Requests\User\Usuario\UpdateUsuarioRequest;
use Illuminate\Http\Request;
use App\Http\Resources\User\UsuarioResource;

class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->paginate($request->all(), (int) $request->get('per_page', 15));
        $data->getCollection()->transform(fn($item) => new UsuarioResource($item));
        return $data;
    }

    public function store(StoreUsuarioRequest $request)
    {
        $model = $this->service->create($request->validated());
        return (new UsuarioResource($model))->response()->setStatusCode(201);
    }

    public function show(Usuario $model)
    {
        return new UsuarioResource($model);
    }

    public function update(Usuario $model, UpdateUsuarioRequest $request)
    {
        $model = $this->service->update($model, $request->validated());
        return new UsuarioResource($model);
    }

    public function destroy(Usuario $model)
    {
        $this->service->delete($model);
        return response()->noContent();
    }
}
