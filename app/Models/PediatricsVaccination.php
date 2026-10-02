<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Vaccination Card record for the pediatrics specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class PediatricsVaccination extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'pediatrics_vaccinations';

    protected $fillable = [
        'uuid',
        'client_id',
        'administered_at',
        'vaccine',
        'dose_number',
        'lot_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'dose_number' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
