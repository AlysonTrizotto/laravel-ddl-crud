<?php

namespace App\Http\Requests\User\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'name' => 'required|string|max:200',
            'email' => 'required|string|max:150',
            'password' => 'required|string|max:200',
        ];
    }
}
