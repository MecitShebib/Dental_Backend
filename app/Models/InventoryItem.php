<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'name',
        'unit',
        'quantity_on_hand',
        'reorder_threshold',
        'reorder_quantity',
        'unit_cost',
        'status',
        'notes',
        'supplier_name',
        'supplier_contact',
        'reorder_alert_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_threshold' => 'decimal:2',
            'reorder_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'reorder_alert_sent_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrder::class);
    }

    public function catalogLinks(): HasMany
    {
        return $this->hasMany(TreatmentCatalogInventoryLink::class);
    }

    public function needsReorder(): bool
    {
        return $this->reorder_threshold !== null && $this->quantity_on_hand <= $this->reorder_threshold;
    }
}
