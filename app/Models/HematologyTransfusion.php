<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repeating Transfusions record for the hematology specialty.
 * Generated 2026-09-27 from the per-specialty clinical field spec
 * (docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md,
 * Aşama 3) -- same shape as NutritionClientProfile / NutritionBodyMetric.
 */
class HematologyTransfusion extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $table = 'hematology_transfusions';

    protected $fillable = [
        'uuid',
        'client_id',
        'transfused_at',
        'product',
        'units',
        'reaction',
        'reaction_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transfused_at' => 'date',
            'units' => 'integer',
            'reaction' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
