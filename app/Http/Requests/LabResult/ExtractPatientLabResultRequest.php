<?php

namespace App\Http\Requests\LabResult;

use Illuminate\Foundation\Http\FormRequest;

class ExtractPatientLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Image only, not PDF -- OpenAI's vision API takes an
            // image_url/data-URI, same constraint as the nutrition body-metric
            // extraction and AnalyzeXrayImageJob already work under.
            'report' => ['required', 'image', 'max:20480'],
        ];
    }
}
