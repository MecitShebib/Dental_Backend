<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdvance extends Model
{
    use Auditable, BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'amount',
        'advance_date',
        'note',
        'settled_amount',
        'settled_by_salary_payment_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'advance_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * "Unsettled" now means "not yet fully recovered" rather than "never
     * touched by a payment" -- a salary payment that couldn't cover this
     * advance's full amount still leaves the remainder (amount -
     * settled_amount) outstanding for the next one to pick up. See
     * SalaryPaymentController::store()'s oldest-advance-first allocation.
     */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereColumn('settled_amount', '<', 'amount');
    }

    public function remainingAmount(): float
    {
        return round((float) $this->amount - (float) $this->settled_amount, 2);
    }

    public function auditSubjectLabel(): ?string
    {
        return $this->employee?->name;
    }
}
