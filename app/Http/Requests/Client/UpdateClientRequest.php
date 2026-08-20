<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientGender;
use App\Enums\ClientLanguage;
use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client')->id;

        return [
            'client_code' => ['nullable', 'string', 'max:50', Rule::unique('clients', 'client_code')->ignore($clientId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'preferred_language' => ['nullable', Rule::enum(ClientLanguage::class)],
            'gender' => ['sometimes', 'required', Rule::enum(ClientGender::class)],
            'age' => ['nullable', 'integer', 'min:0'],
            'date_of_birth' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
        ];
    }
}
