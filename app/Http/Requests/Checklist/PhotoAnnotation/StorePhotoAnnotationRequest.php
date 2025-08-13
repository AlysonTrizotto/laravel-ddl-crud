<?php

namespace App\Http\Requests\Checklist\PhotoAnnotation;

use Illuminate\Foundation\Http\FormRequest;

class StorePhotoAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'checklist_id' => 'required|integer',
            'label' => 'required|string|max:150',
            'metadata' => 'sometimes|array',
        ];
    }
}
