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
            // Image or PDF -- OpenAiClient::buildVisionContentBlock() sends an
            // image straight through as image_url, and a PDF as a file content
            // block (the Chat Completions API accepts both for vision-capable
            // models).
            'report' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
        ];
    }
}
