<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the gynecology specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GynecologyClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'gynecology_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'last_menstrual_period',
        'gravida',
        'para',
        'abortus',
        'blood_type',
        'rh',
        'cycle_length_days',
        'cycle_regularity',
        'contraception_method',
        'last_pap_smear_date',
        'menopause_status',
        'previous_delivery_types',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'last_menstrual_period' => 'date',
            'gravida' => 'integer',
            'para' => 'integer',
            'abortus' => 'integer',
            'cycle_length_days' => 'integer',
            'last_pap_smear_date' => 'date',
            'previous_delivery_types' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
