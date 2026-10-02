<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the general_practice specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GeneralPracticeClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'general_practice_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'chronic_conditions',
        'current_medications',
        'allergies',
        'blood_type',
        'rh',
        'smoking',
        'alcohol',
        'height_cm',
        'adult_vaccination_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'chronic_conditions' => 'array',
            'height_cm' => 'decimal:1',
            'adult_vaccination_status' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
