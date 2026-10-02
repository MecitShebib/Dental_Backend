<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Assessments record for the orthopedics specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class OrthopedicsAssessment extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'orthopedics_assessments';

    protected $fillable = [
        'uuid',
        'client_id',
        'assessed_at',
        'pain_vas',
        'rom_measurements',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assessed_at' => 'date',
            'pain_vas' => 'integer',
            'rom_measurements' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
