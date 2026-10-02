<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Operations record for the general_surgery specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GeneralSurgeryOperation extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'general_surgery_operations';

    protected $fillable = [
        'uuid',
        'client_id',
        'operated_at',
        'operation_name',
        'duration_minutes',
        'anesthesia_type',
        'surgeon_notes',
        'complications',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'operated_at' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
