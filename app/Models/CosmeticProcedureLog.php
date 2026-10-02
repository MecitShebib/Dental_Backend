<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Procedure Log record for the cosmetic specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class CosmeticProcedureLog extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'cosmetic_procedure_logs';

    protected $fillable = [
        'uuid',
        'client_id',
        'performed_at',
        'procedure_type',
        'product_name',
        'lot_number',
        'amount',
        'unit',
        'treatment_area',
        'before_photo_path',
        'before_photo_original_filename',
        'after_photo_path',
        'after_photo_original_filename',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
