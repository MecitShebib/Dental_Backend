<?php

namespace App\Http\Requests\DicomStudy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDicomStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
