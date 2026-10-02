<?php

namespace App\Http\Requests\Gynecology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGynecologyUltrasoundExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_date' => ['required', 'date'],
            'gestational_week' => ['nullable', 'integer', 'min:0', 'max:45'],
            'gestational_day' => ['nullable', 'integer', 'min:0', 'max:6'],
            'bpd_mm' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'hc_mm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'ac_mm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'fl_mm' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'efw_grams' => ['nullable', 'integer', 'min:0', 'max:7000'],
            'fetal_heart_rate' => ['nullable', 'integer', 'min:0', 'max:250'],
            'placenta_location' => ['nullable', Rule::in(['anterior', 'posterior', 'fundal', 'lateral', 'previa'])],
            'amniotic_fluid' => ['nullable', Rule::in(['normal', 'oligohydramnios', 'polyhydramnios'])],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
