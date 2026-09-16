<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionBodyMetric extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasFactory, HasUuid;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DEVICE_IMPORT = 'device_import';

    protected $fillable = [
        'uuid',
        'client_id',
        'recorded_at',
        'source',
        'weight_kg',
        'bmi',
        'body_fat_percent',
        'muscle_mass_kg',
        'visceral_fat_rating',
        'water_percent',
        'bone_mass_kg',
        'basal_metabolic_rate',
        'waist_cm',
        'hip_cm',
        'notes',
        'report_path',
        'report_original_filename',
        'visit_id',
        'appointment_id',
        'recorded_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
            'weight_kg' => 'decimal:1',
            'bmi' => 'decimal:1',
            'body_fat_percent' => 'decimal:1',
            'muscle_mass_kg' => 'decimal:1',
            'visceral_fat_rating' => 'decimal:1',
            'water_percent' => 'decimal:1',
            'bone_mass_kg' => 'decimal:1',
            'waist_cm' => 'decimal:1',
            'hip_cm' => 'decimal:1',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
