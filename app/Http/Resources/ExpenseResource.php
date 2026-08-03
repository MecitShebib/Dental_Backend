<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'category' => $this->category?->value,
            'vendor_name' => $this->vendor_name,
            'invoice_number' => $this->invoice_number,
            'amount' => (float) $this->amount,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'description' => $this->description,
            'attachment_url' => $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
