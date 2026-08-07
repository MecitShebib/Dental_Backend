<?php

namespace App\Http\Requests\XrayImage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateXrayImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // sometimes+nullable: omit to leave unchanged, send null to
            // unlink the image from whatever client it's currently attached
            // to, or send an id to (re)link it -- this is the "Save" action
            // from the picker modal.
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
