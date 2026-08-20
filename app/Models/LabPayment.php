<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompanyViaLabCase;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabPayment extends Model
{
    use BelongsToCompanyViaLabCase, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'lab_case_id',
        'payment_date',
        'amount',
        'payment_method',
        'expense_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
