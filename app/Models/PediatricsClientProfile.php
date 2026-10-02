<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the pediatrics specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class PediatricsClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'pediatrics_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'gestational_age_weeks_at_birth',
        'birth_weight_g',
        'birth_length_cm',
        'birth_head_circumference_cm',
        'guardian_name',
        'guardian_phone',
        'guardian_relation',
        'feeding_type',
        'blood_type',
        'rh',
        'allergies',
        'chronic_conditions',
        'developmental_milestones',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gestational_age_weeks_at_birth' => 'integer',
            'birth_weight_g' => 'integer',
            'birth_length_cm' => 'decimal:1',
            'birth_head_circumference_cm' => 'decimal:1',
            'developmental_milestones' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
