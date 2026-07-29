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

    // "company": manually managed by the clinic, shown in Settings > Pricing.
    // "odontogram": one row per procedure/condition the V2 odontogram widget can
    // select (code = "{category}:{value}", e.g. "fillingMaterial:composite"),
    // used only to compute a visit/appointment/AI-plan's cost -- never shown in
    // the company-facing product list.
    public const SCOPE_COMPANY = 'company';

    public const SCOPE_ODONTOGRAM = 'odontogram';

    protected $fillable = [
        'company_id',
        'scope',
        'code',
        'name_ar',
        'name_en',
        'name_tr',
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
