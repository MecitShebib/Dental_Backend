<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'payment_id',
        'invoice_number',
        'amount',
        'issued_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_number' => 'integer',
            'amount' => 'decimal:2',
            'issued_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * "INV-000123" -- zero-padded to 6 digits, display-only formatting kept
     * out of the plain integer column so "next number" stays a trivial
     * locked max()+1 (see InvoiceService).
     */
    public function formattedNumber(): string
    {
        return 'INV-'.str_pad((string) $this->invoice_number, 6, '0', STR_PAD_LEFT);
    }
}
