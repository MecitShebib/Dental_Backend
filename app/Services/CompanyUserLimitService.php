<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CompanyUserLimitService
{
    public function assertCanHaveAnotherActiveUser(Company $company, ?User $ignoreUser = null): void
    {
        if (! $company->currentSubscription()->exists()) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company does not have an active subscription.'],
            ]);
        }

        $activeUsers = $company->users()
            ->where('status', 'active')
            ->when($ignoreUser, fn ($query) => $query->whereKeyNot($ignoreUser->id))
            ->count();

        $maxUsers = $company->aggregatedSubscriptionLimit('max_users');

        if ($maxUsers !== null && ($activeUsers + 1) > $maxUsers) {
            throw ValidationException::withMessages([
                'status' => ['Active users cannot exceed the company subscription max users limit.'],
            ]);
        }
    }

    public function syncActiveUsers(Company $company): void
    {
        // active_users is a denormalized display counter (the real check in
        // assertCanHaveAnotherActiveUser() above counts User rows directly)
        // -- written to every active subscription, not just one, since the
        // count it represents is now a company-wide total, not a per-
        // specialty one.
        $count = $company->users()->where('status', 'active')->count();
        $company->activeSubscriptions()->each(fn (Subscription $subscription) => $subscription->update(['active_users' => $count]));
    }

    public function syncSubscription(Subscription $subscription): void
    {
        $this->syncActiveUsers($subscription->company);
    }
}
