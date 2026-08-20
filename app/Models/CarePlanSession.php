<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanSession extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'care_plan_id',
        'appointment_id',
        'session_index',
        'title',
        'notes',
        'clinical_data',
    ];

    protected function casts(): array
    {
        return [
            'clinical_data' => 'array',
        ];
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
