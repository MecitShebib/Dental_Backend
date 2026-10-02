<?php

namespace App\Http\Requests\Physiotherapy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePhysiotherapySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'session_number' => ['nullable', 'integer', 'min:1', 'max:500'],
            'pain_vas' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rom_measurements' => ['nullable', 'array', 'max:50'],
            'rom_measurements.*.joint' => ['nullable', 'string', 'max:255'],
            'rom_measurements.*.movement' => ['nullable', 'string', 'max:255'],
            'rom_measurements.*.degrees' => ['nullable', 'integer', 'min:0', 'max:360'],
            'muscle_strength' => ['nullable', 'array', 'max:50'],
            'muscle_strength.*.muscle' => ['nullable', 'string', 'max:255'],
            'muscle_strength.*.mmt' => ['nullable', 'integer', 'min:0', 'max:5'],
            'modalities' => ['nullable', 'array'],
            'modalities.*' => ['string', Rule::in(['tens', 'therapeutic_ultrasound', 'hot_pack', 'cold_pack', 'laser', 'shortwave', 'manual_therapy', 'exercise', 'dry_needling', 'kinesio_taping', 'traction'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
