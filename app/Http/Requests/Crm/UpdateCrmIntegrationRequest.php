<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
            // Zoho accounts are region-locked (.com/.eu/.in/.com.cn/.jp...);
            // left blank, ZOHO_ACCOUNTS_BASE_URL/ZOHO_API_BASE_URL apply.
            'accounts_base_url' => ['nullable', 'url', 'max:255'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
