<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentCatalog extends Model
{
    use HasFactory;

    protected $table = 'treatment_catalog';

    protected $fillable = [
        'company_id',
        'code',
        'name_ar',
        'name_en',
        'name_tr',
        'color',
        'default_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(TreatmentRecordTooth::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
