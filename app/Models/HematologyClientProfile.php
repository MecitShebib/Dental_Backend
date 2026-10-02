<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the hematology specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class HematologyClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'hematology_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'blood_type',
        'rh',
        'primary_diagnosis',
        'diagnosis_notes',
        'anticoagulant',
        'inr_target_min',
        'inr_target_max',
        'splenectomy',
        'chemo_protocol',
        'chemo_cycles_planned',
        'chemo_cycles_completed',
        'bleeding_history',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'inr_target_min' => 'decimal:1',
            'inr_target_max' => 'decimal:1',
            'splenectomy' => 'boolean',
            'chemo_cycles_planned' => 'integer',
            'chemo_cycles_completed' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
