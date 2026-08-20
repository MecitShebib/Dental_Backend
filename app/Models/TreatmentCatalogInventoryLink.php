<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Performing this procedure consumes this much of this inventory item" --
 * see InventoryService::syncConsumptionForSource() for how a treatment
 * charge sync (visit/appointment/AI-plan save) uses this to auto-decrement
 * stock. No company_id of its own: scoped transitively through
 * treatment_catalog_id, which already belongs to a company.
 */
class TreatmentCatalogInventoryLink extends Model
{
    use HasFactory;

    protected $table = 'treatment_catalog_inventory_links';

    protected $fillable = [
        'treatment_catalog_id',
        'inventory_item_id',
        'quantity_per_use',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_use' => 'decimal:2',
        ];
    }

    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(TreatmentCatalog::class, 'treatment_catalog_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
