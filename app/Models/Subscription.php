<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_name',
        'status',
        'starts_at',
        'ends_at',
        'max_users',
        'active_users',
        'max_ai_tokens',
        'ai_tokens_used',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_users' => 'integer',
            'active_users' => 'integer',
            'max_ai_tokens' => 'integer',
            'ai_tokens_used' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isCurrentlyActive(): bool
    {
        $today = now()->startOfDay();

        return ($this->status === SubscriptionStatus::Active || $this->status === SubscriptionStatus::Active->value)
            && $this->starts_at?->startOfDay()?->lte($today)
            && ($this->ends_at === null || $this->ends_at->endOfDay()->gte($today));
    }
}
