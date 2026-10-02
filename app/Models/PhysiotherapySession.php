<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Sessions record for the physiotherapy specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class PhysiotherapySession extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'physiotherapy_sessions';

    protected $fillable = [
        'uuid',
        'client_id',
        'session_date',
        'session_number',
        'pain_vas',
        'rom_measurements',
        'muscle_strength',
        'modalities',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'session_number' => 'integer',
            'pain_vas' => 'integer',
            'rom_measurements' => 'array',
            'muscle_strength' => 'array',
            'modalities' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
