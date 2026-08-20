<?php

namespace App\Http\Resources;

use App\Models\CariTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Looked up rather than stored on Expense itself -- the link lives
        // on the CariTransaction row (source_type=expense) that
        // ExpenseController::syncCari() posts, so the edit form can prefill
        // it and not silently drop it on the next save.
        $cariLink = CariTransaction::query()
            ->where('source_type', CariTransaction::SOURCE_EXPENSE)
            ->where('source_id', $this->id)
            ->first(['partyable_type', 'partyable_id', 'currency', 'exchange_rate']);

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
            'cari_partyable_type' => $cariLink?->partyable_type,
            'cari_partyable_id' => $cariLink?->partyable_id,
            'cari_currency' => $cariLink?->currency?->value,
            'cari_exchange_rate' => $cariLink ? (float) $cariLink->exchange_rate : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
