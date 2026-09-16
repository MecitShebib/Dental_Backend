<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;

class ExtractNutritionBodyMetricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Image only, not PDF -- OpenAI's vision API takes an
            // image_url/data-URI, the same constraint AnalyzeXrayImageJob
            // already works under. A PDF report still works fine through
            // the plain manual-entry path.
            'report' => ['required', 'image', 'max:20480'],
        ];
    }
}
