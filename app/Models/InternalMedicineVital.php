<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Vital Signs record for the internal_medicine specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class InternalMedicineVital extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'internal_medicine_vitals';

    protected $fillable = [
        'uuid',
        'client_id',
        'measured_at',
        'systolic',
        'diastolic',
        'pulse',
        'temperature_c',
        'spo2',
        'blood_glucose',
        'weight_kg',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'systolic' => 'integer',
            'diastolic' => 'integer',
            'pulse' => 'integer',
            'temperature_c' => 'decimal:1',
            'spo2' => 'integer',
            'blood_glucose' => 'integer',
            'weight_kg' => 'decimal:1',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
