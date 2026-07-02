<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('price') && $this->has('unit_price')) {
            $this->merge([
                'price' => $this->input('unit_price'),
            ]);
        }

        if ($this->has('name') && ! $this->has('name_en')) {
            $this->merge([
                'name_en' => $this->input('name'),
            ]);
        }

        if ($this->has('name') && ! $this->has('name_ar')) {
            $this->merge([
                'name_ar' => $this->input('name'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_tr' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
