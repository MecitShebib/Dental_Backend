<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'visit_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'charge_items' => ['nullable', 'array'],
            'charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'charge_items.*.amount' => ['required', 'numeric'],
        ];
    }
}
