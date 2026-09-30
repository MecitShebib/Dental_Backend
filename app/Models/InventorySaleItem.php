<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an InventorySale -- unit_price/unit_cost are snapshotted from
 * the inventory item at sale time (see InventorySaleService::create()) so a
 * later price change never distorts a past sale's revenue/profit in
 * Reports > Inventory. No company_id of its own: scoped transitively through
 * inventory_sale_id, same pattern as TreatmentCatalogInventoryLink.
 */
class InventorySaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_sale_id',
        'inventory_item_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function inventorySale(): BelongsTo
    {
        return $this->belongsTo(InventorySale::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
