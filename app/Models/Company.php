<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'booking_slug',
        'call_webhook_secret',
        'email',
        'phone',
        'address',
        'status',
        'notes',
        'recall_interval_days',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function treatmentCatalog(): HasMany
    {
        return $this->hasMany(TreatmentCatalog::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function fundTransactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class);
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function labPartners(): HasMany
    {
        return $this->hasMany(LabPartner::class);
    }

    public function xrayImages(): HasMany
    {
        return $this->hasMany(XrayImage::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function cariParties(): HasMany
    {
        return $this->hasMany(CariParty::class);
    }

    public function cariTransactions(): HasMany
    {
        return $this->hasMany(CariTransaction::class);
    }

    public function whatsappIntegration(): HasOne
    {
        return $this->hasOne(WhatsAppIntegration::class);
    }

    public function crmIntegration(): HasOne
    {
        return $this->hasOne(CrmIntegration::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->latestOfMany('starts_at');
    }

    /**
     * Specialty-aware counterpart to currentSubscription() -- a company can
     * now hold one active Subscription per Specialty (see
     * Subscription::specialty_id), so "the" current subscription is only
     * unambiguous once a specialty is given. currentSubscription() itself is
     * untouched (still "the latest active subscription regardless of
     * specialty") because today every company only has dental subscriptions
     * and ~13 call sites (SubscriptionAccessService, AiTokenUsageService,
     * CompanyUserLimitService, EnsureActiveClinicAccess, AuthController,
     * etc.) still assume that single-specialty reality; migrating each of
     * those to resolve a specialty from the request (route prefix once
     * /app/{specialty} routing exists) and call this method instead is the
     * next phase, not done here.
     */
    public function currentSubscriptionFor(Specialty|string $specialty): ?Subscription
    {
        $specialtyId = $specialty instanceof Specialty
            ? $specialty->id
            : Specialty::query()->where('key', $specialty)->value('id');

        if (! $specialtyId) {
            return null;
        }

        return $this->subscriptions()
            ->where('specialty_id', $specialtyId)
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Every Specialty this company currently holds an active subscription
     * for, ordered for display (e.g. the Zoho-style launcher screen once it
     * exists). Empty for a company with no active subscriptions at all.
     */
    public function activeSpecialties(): Collection
    {
        $specialtyIds = $this->activeSubscriptions()->pluck('specialty_id')->filter()->unique();

        return Specialty::query()->whereIn('id', $specialtyIds)->orderBy('sort_order')->get();
    }

    /**
     * Every currently-active Subscription row (one per specialty the
     * company is subscribed to), same active-window definition as
     * currentSubscription()/currentSubscriptionFor().
     */
    public function activeSubscriptions(): Collection
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->get();
    }

    /**
     * Company-wide policy for every per-subscription capacity limit
     * (max_users/max_ai_tokens/max_branches): a company with multiple active
     * specialty subscriptions is capped by the SUM of that column across all
     * of them, not by any single one -- confirmed with the product owner
     * 2026-08-17 ("şirket genelinde tek bir toplam", a single company-wide
     * total) rather than gating each specialty separately. If ANY active
     * subscription has an unlimited (null) value for that column, the whole
     * company is treated as unlimited for it too, rather than silently
     * capping at the sum of just the non-null ones.
     */
    public function aggregatedSubscriptionLimit(string $column): ?int
    {
        $subscriptions = $this->activeSubscriptions();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        if ($subscriptions->contains(fn (Subscription $subscription) => $subscription->{$column} === null)) {
            return null;
        }

        return (int) $subscriptions->sum($column);
    }

    /**
     * Company-wide sum of a per-subscription usage counter (currently only
     * ai_tokens_used -- active_users is intentionally not read through this,
     * see CompanyUserLimitService, which counts real User rows directly
     * instead of trusting the denormalized counter).
     */
    public function aggregatedSubscriptionUsage(string $column): int
    {
        return (int) $this->activeSubscriptions()->sum($column);
    }

    public function activeUsersCount(): int
    {
        return $this->users()->where('status', 'active')->count();
    }

    /**
     * The public booking page's URL for this company (see routes/web.php's
     * "book" route and PublicBookingController) -- null if no slug is set.
     */
    public function bookingUrl(): ?string
    {
        return $this->booking_slug ? url('/book/'.$this->booking_slug) : null;
    }

    /**
     * The URL a telephony provider (Virtual PBX) should POST call events to,
     * or null until the company has generated a webhook secret from
     * Settings > Call Webhook (see CallLogWebhookController).
     */
    public function callWebhookUrl(): ?string
    {
        return $this->call_webhook_secret ? url('/api/public/companies/'.$this->booking_slug.'/calls/webhook') : null;
    }

    /**
     * Slugifies the given name and appends a numeric suffix on collision.
     * Called once at company creation (see Admin\CompanyController::store).
     */
    public static function generateBookingSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'clinic';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('booking_slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Null means recalls are disabled for this company. 0 is stored as an
     * explicit opt-out; an unset column falls back to the configured default.
     */
    public function recallIntervalDays(): ?int
    {
        if ($this->recall_interval_days === null) {
            return (int) config('services.patient_recall.default_interval_days');
        }

        return $this->recall_interval_days > 0 ? (int) $this->recall_interval_days : null;
    }
}
