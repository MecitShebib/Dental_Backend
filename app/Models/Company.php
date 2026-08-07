<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'status',
        'notes',
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

    public function activeUsersCount(): int
    {
        return $this->users()->where('status', 'active')->count();
    }
}
