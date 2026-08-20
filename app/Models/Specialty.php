<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One medical specialty offered on the Doctovaria platform (Dentavaria =
 * dental, plus the not-yet-built Gynevaria/Medivaria/Orthovaria/Estevaria).
 * A Company subscribes per-specialty (see Subscription::specialty_id) and a
 * doctor belongs to exactly one (see User::specialty_id); non-doctor staff
 * (system manager/accountant/reception) have a null specialty_id and can
 * work across every specialty the company is subscribed to.
 */
class Specialty extends Model
{
    use HasFactory;
    use HasUuid;

    public const DENTAL = 'dental';

    public const GYNECOLOGY = 'gynecology';

    public const INTERNAL_MEDICINE = 'internal_medicine';

    public const ORTHOPEDICS = 'orthopedics';

    public const COSMETIC = 'cosmetic';

    protected $fillable = [
        'key',
        'brand_name',
        'name_ar',
        'name_en',
        'name_tr',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function treatmentCatalogItems(): HasMany
    {
        return $this->hasMany(TreatmentCatalog::class);
    }
}
