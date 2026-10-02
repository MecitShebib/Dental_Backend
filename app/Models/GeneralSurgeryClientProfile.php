<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the general_surgery specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GeneralSurgeryClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'general_surgery_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'indication',
        'planned_operation',
        'asa_score',
        'previous_surgeries',
        'anticoagulant_use',
        'allergies',
        'blood_type',
        'rh',
        'preop_checklist',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'preop_checklist' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
