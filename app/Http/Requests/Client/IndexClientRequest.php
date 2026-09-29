<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Patients-page "Filter" popup -- see ClientQueryService::list().
            // gender/age/appointment are universal (plain Client columns);
            // the rest are per-specialty clinical-profile fields, harmless
            // to validate here even for a specialty that ignores them.
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:150'],
            'appointment_from' => ['nullable', 'date'],
            'appointment_to' => ['nullable', 'date'],
            'blood_type' => ['nullable', 'string', 'in:A,B,AB,0'],
            'menopause_status' => ['nullable', 'string', 'in:pre,peri,post'],
            'chronic_condition' => ['nullable', 'string'],
            'smoking' => ['nullable', 'string', 'in:never,former,current'],
            'affected_region' => ['nullable', 'string', 'in:shoulder,elbow,wrist_hand,hip,knee,ankle_foot,cervical_spine,thoracic_spine,lumbar_spine,other'],
            'side' => ['nullable', 'string', 'in:right,left,bilateral'],
            'fitzpatrick_skin_type' => ['nullable', 'string', 'in:I,II,III,IV,V,VI'],
            'keloid_tendency' => ['nullable', 'boolean'],
            'dietary_type' => ['nullable', 'string', 'in:omnivore,vegetarian,vegan,halal,kosher,other'],
            'feeding_type' => ['nullable', 'string', 'in:breast,formula,mixed,solid'],
            'primary_diagnosis' => ['nullable', 'string'],
            'asa_score' => ['nullable', 'string', 'in:I,II,III,IV,V,VI'],
        ];
    }
}
