<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Blood Counts record for the hematology specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class HematologyBloodCount extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'hematology_blood_counts';

    protected $fillable = [
        'uuid',
        'client_id',
        'measured_at',
        'hb',
        'hct',
        'wbc',
        'plt',
        'mcv',
        'ferritin',
        'inr',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'hb' => 'decimal:1',
            'hct' => 'decimal:1',
            'wbc' => 'decimal:2',
            'plt' => 'integer',
            'mcv' => 'decimal:1',
            'ferritin' => 'decimal:1',
            'inr' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
