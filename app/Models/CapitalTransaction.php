<?php

namespace App\Models;

use App\Enums\CapitalTransactionType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapitalTransaction extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'type',
        'amount',
        'party_name',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CapitalTransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
