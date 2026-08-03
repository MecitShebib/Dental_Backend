<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'appointment_id',
        'visit_date',
        'start_time',
        'duration_minutes',
        'summary',
        'notes',
        'odontogram_image_path',
        'attendance_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'attendance_status' => AttendanceStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
