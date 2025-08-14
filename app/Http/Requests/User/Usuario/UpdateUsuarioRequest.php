<?php

namespace App\Http\Requests\User\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'sometimes|string',
            'name' => 'sometimes|string|max:200',
            'email' => 'sometimes|string|max:150',
            'password' => 'sometimes|string|max:200',
        ];
    }
}
