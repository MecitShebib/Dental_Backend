<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentCatalogInventoryLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'links' => ['present', 'array'],
            'links.*.inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id)),
            ],
            'links.*.quantity_per_use' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
