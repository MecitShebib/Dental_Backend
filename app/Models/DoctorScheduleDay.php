<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorScheduleDay extends Model
{
    use HasFactory;

    protected $fillable = ['doctor_schedule_id', 'weekday'];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
        ];
    }

    public function doctorSchedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class);
    }
}
