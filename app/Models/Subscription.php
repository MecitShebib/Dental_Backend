<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Optional features a subscription can include or leave out (the
     * `features` column). Anything not listed here is always available.
     */
    public const FEATURES = ['consent_templates', 'api_tokens', 'whatsapp', 'crm', 'call_webhook'];

    /** The features this row grants -- a NULL column (pre-feature rows) grants all. */
    public function enabledFeatures(): array
    {
        return $this->features === null
            ? self::FEATURES
            : array_values(array_intersect(self::FEATURES, $this->features));
    }

    protected $fillable = [
        'company_id',
        'specialty_id',
        'plan_name',
        'status',
        'starts_at',
        'ends_at',
        'max_doctors',
        'max_assistants',
        'active_users',
        'max_branches',
        'max_ai_tokens',
        'ai_tokens_used',
        'features',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_doctors' => 'integer',
            'max_assistants' => 'integer',
            'active_users' => 'integer',
            'max_branches' => 'integer',
            'max_ai_tokens' => 'integer',
            'ai_tokens_used' => 'integer',
            'features' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function isCurrentlyActive(): bool
    {
        $today = now()->startOfDay();

        return ($this->status === SubscriptionStatus::Active || $this->status === SubscriptionStatus::Active->value)
            && $this->starts_at?->startOfDay()?->lte($today)
            && ($this->ends_at === null || $this->ends_at->endOfDay()->gte($today));
    }
}
