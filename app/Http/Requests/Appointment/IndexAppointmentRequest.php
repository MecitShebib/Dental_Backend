<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'gte:date_from'],
            'date'      => ['nullable', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'status'    => ['nullable', 'string'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->date_from && $this->date_to && $this->date_from > $this->date_to) {
                    $validator->errors()->add('date_from', 'date_from must be before or equal to date_to.');
                }
            },
        ];
    }
}
