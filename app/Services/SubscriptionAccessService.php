<?php

namespace App\Services;

use App\Models\User;

class SubscriptionAccessService
{
    public function hasActiveSubscription(User $user): bool
    {
        return $user->company !== null
            && $user->company->status === 'active'
            && $user->company->currentSubscription()->exists();
    }

    public function canLogin(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $this->hasActiveSubscription($user);
    }

    public function loginErrorMessage(User $user): string
    {
        if (! $user->isActive()) {
            return 'This user is inactive and cannot log in.';
        }

        return 'This user does not have an active subscription.';
    }
}
