<?php

namespace App\Http\Resources;

use App\Models\CariTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CariTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            // Stored as the short morph-map alias ('cari_party'/'user'/'lab_partner'),
            // not a full class name -- see Relation::enforceMorphMap() in AppServiceProvider.
            'partyable_type' => $this->partyable_type,
            'partyable_id' => $this->partyable_id,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'description' => $this->description,
            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
            'currency' => $this->currency?->value,
            'exchange_rate' => (float) $this->exchange_rate,
            'transaction_type' => $this->transaction_type?->value,
            'expense_category' => $this->expense_category?->value,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'is_editable' => $this->source_type === null || $this->source_type === CariTransaction::SOURCE_MANUAL,
            'reference_number' => $this->reference_number,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
