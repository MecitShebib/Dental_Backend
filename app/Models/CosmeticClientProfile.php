<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the cosmetic specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class CosmeticClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'cosmetic_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'fitzpatrick_skin_type',
        'aesthetic_goals',
        'areas_of_interest',
        'previous_procedures',
        'allergies',
        'keloid_tendency',
        'skincare_products',
        'isotretinoin_use',
        'pregnancy_breastfeeding',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'areas_of_interest' => 'array',
            'keloid_tendency' => 'boolean',
            'pregnancy_breastfeeding' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
