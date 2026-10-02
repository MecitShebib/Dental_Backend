<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-patient clinical profile row for the physiotherapy specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class PhysiotherapyClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'physiotherapy_client_profiles';

    protected $fillable = [
        'uuid',
        'client_id',
        'diagnosis',
        'referring_physician',
        'affected_region',
        'side',
        'prescribed_session_count',
        'home_exercise_program',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_session_count' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
