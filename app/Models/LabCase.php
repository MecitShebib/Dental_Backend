<?php

namespace App\Models;

use App\Enums\LabCaseStatus;
use App\Enums\LabCaseWorkType;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCase extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid, SoftDeletes;

    protected $attributes = [
        'status' => 'sent',
    ];

    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'lab_partner_id',
        'appointment_id',
        'work_type',
        'teeth',
        'material',
        'shade',
        'status',
        'sent_date',
        'expected_return_date',
        'received_date',
        'lab_cost',
        'expense_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'work_type' => LabCaseWorkType::class,
            'status' => LabCaseStatus::class,
            'teeth' => 'array',
            'sent_date' => 'date',
            'expected_return_date' => 'date',
            'received_date' => 'date',
            'lab_cost' => 'decimal:2',
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

    public function labPartner(): BelongsTo
    {
        return $this->belongsTo(LabPartner::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
