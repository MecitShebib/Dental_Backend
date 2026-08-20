<?php

namespace App\Models;

use App\Enums\ClientGender;
use App\Enums\ClientLanguage;
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
        'branch_id',
        'client_code',
        'name',
        'email',
        'phone',
        'preferred_language',
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
            'preferred_language' => ClientLanguage::class,
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

    public function specialtyRecords(): HasMany
    {
        return $this->hasMany(ClientSpecialtyRecord::class);
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

    public function labResults(): HasMany
    {
        return $this->hasMany(PatientLabResult::class);
    }

    public function xrayImages(): HasMany
    {
        return $this->hasMany(XrayImage::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function aiConversation(): HasOne
    {
        return $this->hasOne(AiConversation::class);
    }

    public function patientRecalls(): HasMany
    {
        return $this->hasMany(PatientRecall::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ClientConsent::class);
    }

    public function satisfactionSurveys(): HasMany
    {
        return $this->hasMany(SatisfactionSurvey::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
