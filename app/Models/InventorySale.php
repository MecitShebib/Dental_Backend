<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header for one "sell inventory item(s)" event (Inventory page's "New Sale
 * Order" button) -- see InventorySaleService::create(). client_id is
 * optional: a walk-in sale with no patient posts straight to the company
 * fund. is_paid only means anything when a client is set -- it decides
 * whether that sale also posts to the fund (paid on the spot) or becomes a
 * treatment_charges row against the patient (unpaid, i.e. debt). Exists
 * mainly to give TreatmentChargeService::syncItems()/FundTransactionService
 * a real source_id to key their rows on, the same way a visit, appointment,
 * or AI plan session each supply their own id for that purpose.
 */
class InventorySale extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'branch_id',
        'specialty_id',
        'is_paid',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventorySaleItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
