<?php

namespace App\Models;

use App\Enums\ClientGender;
use App\Enums\ClientStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_code',
        'name',
        'email',
        'phone',
        'gender',
        'age',
        'date_of_birth',
        'city',
        'address',
        'medical_notes',
        'status',
        'last_visit_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gender' => ClientGender::class,
            'status' => ClientStatus::class,
            'date_of_birth' => 'date',
            'last_visit_at' => 'datetime',
        ];
    }

    public function treatmentRecord(): HasOne
    {
        return $this->hasOne(TreatmentRecord::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function treatmentCharges(): HasMany
    {
        return $this->hasMany(TreatmentCharge::class);
    }

    public function labCases(): HasMany
    {
        return $this->hasMany(LabCase::class);
    }

    public function xrayImages(): HasMany
    {
        return $this->hasMany(XrayImage::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
