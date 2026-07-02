<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentRecordTooth extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_record_id',
        'tooth_number',
        'treatment_catalog_id',
        'unit_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    public function treatmentRecord(): BelongsTo
    {
        return $this->belongsTo(TreatmentRecord::class);
    }

    public function treatmentCatalog(): BelongsTo
    {
        return $this->belongsTo(TreatmentCatalog::class);
    }
}
