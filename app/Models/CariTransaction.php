<?php

namespace App\Models;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Enums\ExpenseCategory;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One "cari hesap" ledger row against a party (CariParty | User | LabPartner
 * -- see the morph map in AppServiceProvider::boot()). debit/credit are two
 * separate columns (not one signed amount, unlike FundTransaction) to match
 * how every "cari" screen in the industry displays them, and because a
 * transaction is never both at once. source_type/source_id mirror
 * FundTransaction's pattern for rows CariLedgerService posts automatically
 * on behalf of another record (an Expense, a LabCase invoice, a LabPayment,
 * ...) so they can be kept in sync or removed when that record changes.
 */
class CariTransaction extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_LAB_CASE = 'lab_case';

    public const SOURCE_LAB_PAYMENT = 'lab_payment';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SALARY_PAYMENT = 'salary_payment';

    protected $fillable = [
        'uuid',
        'company_id',
        'partyable_type',
        'partyable_id',
        'invoice_date',
        'payment_date',
        'description',
        'debit',
        'credit',
        'currency',
        'exchange_rate',
        'transaction_type',
        'expense_category',
        'source_type',
        'source_id',
        'reference_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'payment_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'currency' => CariCurrency::class,
            'transaction_type' => CariTransactionType::class,
            'expense_category' => ExpenseCategory::class,
        ];
    }

    public function partyable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
