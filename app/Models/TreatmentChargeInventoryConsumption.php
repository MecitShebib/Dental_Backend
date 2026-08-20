<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Live current-state row for "how much of this inventory item is currently
 * consumed because of this charge source" -- not a ledger (that's
 * InventoryTransaction). See InventoryService::syncConsumptionForSource().
 */
class TreatmentChargeInventoryConsumption extends Model
{
    use HasFactory;

    protected $table = 'treatment_charge_inventory_consumptions';

    protected $fillable = [
        'source_type',
        'source_id',
        'inventory_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
