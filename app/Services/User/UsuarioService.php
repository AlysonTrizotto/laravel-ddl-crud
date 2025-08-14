<?php

namespace App\Services\User;

use App\Models\User\Usuario;

class UsuarioService
{
    public function paginate(array $filters = [], int $perPage = 15)
    {
        return Usuario::query()->filter($filters)->paginate($perPage);
    }

    public function find($id): ?Usuario
    {
        return Usuario::find($id);
    }

    public function create(array $data): Usuario
    {
        // Regras de negócio antes de persistir (se necessário)
        return Usuario::store($data);
    }

    public function update(Usuario $model, array $data): Usuario
    {
        // Regras de negócio antes de atualizar (se necessário)
        return $model->applyUpdate($data);
    }

    public function delete(Usuario $model): void
    {
        // Regras de negócio antes de deletar (se necessário)
        $model->delete();
    }
}
