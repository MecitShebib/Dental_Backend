<?php

namespace App\Http\Requests\Cosmetic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCosmeticClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fitzpatrick_skin_type' => ['nullable', Rule::in(['I', 'II', 'III', 'IV', 'V', 'VI'])],
            'aesthetic_goals' => ['nullable', 'string', 'max:5000'],
            'areas_of_interest' => ['nullable', 'array'],
            'areas_of_interest.*' => ['string', Rule::in(['forehead', 'glabella', 'crows_feet', 'lips', 'cheeks', 'nasolabial', 'jawline', 'neck', 'body', 'hair'])],
            'previous_procedures' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'keloid_tendency' => ['nullable', 'boolean'],
            'skincare_products' => ['nullable', 'string', 'max:5000'],
            'isotretinoin_use' => ['nullable', Rule::in(['none', 'current', 'past_6_months'])],
            'pregnancy_breastfeeding' => ['nullable', 'boolean'],
        ];
    }
}
