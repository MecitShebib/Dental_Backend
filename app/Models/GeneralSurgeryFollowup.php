<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Post-op Follow-ups record for the general_surgery specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GeneralSurgeryFollowup extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'general_surgery_followups';

    protected $fillable = [
        'uuid',
        'client_id',
        'followup_date',
        'operation_id',
        'wound_status',
        'drain_removed_at',
        'sutures_removed_at',
        'complications',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'followup_date' => 'date',
            'drain_removed_at' => 'date',
            'sutures_removed_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(GeneralSurgeryOperation::class, 'operation_id');
    }
}
