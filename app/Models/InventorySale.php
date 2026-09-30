<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header for one "sell inventory items to a patient" event (Client Details
 * page's "Add From Inventory" button) -- see InventorySaleService::create().
 * Exists mainly to give TreatmentChargeService::syncItems() a real source_id
 * to key the resulting treatment_charges rows on, the same way a visit,
 * appointment, or AI plan session each supply their own id for that purpose.
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
        'created_by',
    ];

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
