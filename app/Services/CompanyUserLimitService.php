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
        $subscription = $company->currentSubscription()->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company does not have an active subscription.'],
            ]);
        }

        $activeUsers = $company->users()
            ->where('status', 'active')
            ->when($ignoreUser, fn ($query) => $query->whereKeyNot($ignoreUser->id))
            ->count();

        if (($activeUsers + 1) > $subscription->max_users) {
            throw ValidationException::withMessages([
                'status' => ['Active users cannot exceed the company subscription max users limit.'],
            ]);
        }
    }

    public function syncActiveUsers(Company $company): void
    {
        $subscription = $company->currentSubscription()->first();

        if ($subscription) {
            $subscription->update([
                'active_users' => $company->users()->where('status', 'active')->count(),
            ]);
        }
    }

    public function syncSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'active_users' => $subscription->company->users()->where('status', 'active')->count(),
        ]);
    }
}
