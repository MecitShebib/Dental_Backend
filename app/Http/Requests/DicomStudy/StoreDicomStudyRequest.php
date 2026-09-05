<?php

namespace App\Http\Requests\DicomStudy;

use Illuminate\Foundation\Http\FormRequest;

class StoreDicomStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archive' => ['required_without:files', 'file', 'mimes:zip'],
            'files' => ['required_without:archive', 'array', 'min:1'],
            'files.*' => ['file'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
