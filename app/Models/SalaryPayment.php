<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryPayment extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'period_year',
        'period_month',
        'base_salary',
        'treatment_revenue',
        'commission_percentage',
        'commission_amount',
        'advances_total',
        'net_amount',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'base_salary' => 'decimal:2',
            'treatment_revenue' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'advances_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settledAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class, 'settled_by_salary_payment_id');
    }
}
