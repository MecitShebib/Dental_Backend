<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientGender;
use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['nullable', 'string', 'max:50', 'unique:clients,client_code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', Rule::enum(ClientGender::class)],
            'age' => ['nullable', 'integer', 'min:0'],
            'date_of_birth' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
        ];
    }
}
