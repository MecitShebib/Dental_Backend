<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Growth record for the pediatrics specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class PediatricsGrowthMeasurement extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'pediatrics_growth_measurements';

    protected $fillable = [
        'uuid',
        'client_id',
        'measured_at',
        'weight_kg',
        'height_cm',
        'head_circumference_cm',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'head_circumference_cm' => 'decimal:1',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
