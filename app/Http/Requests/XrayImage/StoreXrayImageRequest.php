<?php

namespace App\Http\Requests\XrayImage;

use Illuminate\Foundation\Http\FormRequest;

class StoreXrayImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Array so one call can batch-upload a machine's whole capture
            // session, or a staff member's multi-file picker selection.
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            // Optional: lets an integration tag the client directly if it
            // already knows one. Almost always omitted -- images normally
            // land unlinked and get attached later from the picker.
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
