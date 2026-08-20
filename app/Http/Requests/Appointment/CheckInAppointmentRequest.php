<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'create_payment_after_visit' => ['nullable', 'boolean'],
            'charge_items' => ['nullable', 'array'],
            'charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'charge_items.*.amount' => ['required', 'numeric'],
            'charge_items.*.treatment_catalog_id' => ['nullable', 'integer', Rule::exists('treatment_catalog', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))],
        ];
    }
}
