<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaDoctor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorSchedule extends Model
{
    use BelongsToCompanyViaDoctor, HasFactory;

    protected $fillable = ['doctor_id', 'start_time', 'end_time', 'slot_minutes'];

    protected $casts = [
        'slot_minutes' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function workingDays(): HasMany
    {
        return $this->hasMany(DoctorScheduleDay::class);
    }
}
