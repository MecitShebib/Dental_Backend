<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryPurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                InventoryPurchaseOrder::STATUS_ORDERED,
                InventoryPurchaseOrder::STATUS_RECEIVED,
                InventoryPurchaseOrder::STATUS_CANCELLED,
            ])],
        ];
    }
}
