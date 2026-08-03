<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdvance extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'amount',
        'advance_date',
        'note',
        'settled_by_salary_payment_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'advance_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settled_by_salary_payment_id');
    }
}
