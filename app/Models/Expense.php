<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'category',
        'vendor_name',
        'invoice_number',
        'amount',
        'expense_date',
        'description',
        'attachment_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
