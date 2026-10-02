<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Ultrasound record for the gynecology specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class GynecologyUltrasoundExam extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'gynecology_ultrasound_exams';

    protected $fillable = [
        'uuid',
        'client_id',
        'exam_date',
        'gestational_week',
        'gestational_day',
        'bpd_mm',
        'hc_mm',
        'ac_mm',
        'fl_mm',
        'efw_grams',
        'fetal_heart_rate',
        'placenta_location',
        'amniotic_fluid',
        'image_path',
        'image_original_filename',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'gestational_week' => 'integer',
            'gestational_day' => 'integer',
            'bpd_mm' => 'decimal:1',
            'hc_mm' => 'decimal:1',
            'ac_mm' => 'decimal:1',
            'fl_mm' => 'decimal:1',
            'efw_grams' => 'integer',
            'fetal_heart_rate' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
