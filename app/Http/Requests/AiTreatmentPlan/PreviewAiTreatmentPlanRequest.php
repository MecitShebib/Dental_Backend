<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAiTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => [Rule::requiredIf(fn () => ! $this->hasFile('audio')), 'nullable', 'string', 'max:2000'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg', 'max:20480'],
        ];
    }
}
