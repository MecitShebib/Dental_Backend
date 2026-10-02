<?php

namespace App\Http\Requests\Hematology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHematologyClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', '0'])],
            'rh' => ['nullable', Rule::in(['positive', 'negative'])],
            'primary_diagnosis' => ['nullable', Rule::in(['iron_deficiency_anemia', 'b12_folate_deficiency', 'thalassemia', 'sickle_cell', 'hemophilia', 'von_willebrand', 'itp', 'leukemia', 'lymphoma', 'myeloma', 'mds', 'polycythemia', 'other'])],
            'diagnosis_notes' => ['nullable', 'string', 'max:5000'],
            'anticoagulant' => ['nullable', Rule::in(['none', 'warfarin', 'doac', 'heparin', 'other'])],
            'inr_target_min' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'inr_target_max' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'splenectomy' => ['nullable', 'boolean'],
            'chemo_protocol' => ['nullable', 'string', 'max:5000'],
            'chemo_cycles_planned' => ['nullable', 'integer', 'min:0', 'max:100'],
            'chemo_cycles_completed' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bleeding_history' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
