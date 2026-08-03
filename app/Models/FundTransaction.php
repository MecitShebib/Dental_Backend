<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The single ledger behind the company fund/cash box: every patient payment,
 * expense, capital injection, owner withdrawal, salary advance, and salary
 * payment posts exactly one row here (amount signed: positive = inflow,
 * negative = outflow). CompanyFundService sums this table for the balance,
 * mirroring how ClientFinancialSummaryService sums treatment_charges.
 */
class FundTransaction extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_CAPITAL = 'capital';

    public const SOURCE_SALARY_ADVANCE = 'salary_advance';

    public const SOURCE_SALARY_PAYMENT = 'salary_payment';

    protected $fillable = [
        'uuid',
        'company_id',
        'source_type',
        'source_id',
        'amount',
        'description',
        'occurred_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
