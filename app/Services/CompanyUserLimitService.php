<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CompanyUserLimitService
{
    /**
     * $isDoctor is the type of the seat being taken (the new user, or the
     * edited user's type after the edit) -- checked against max_doctors or
     * max_assistants (the only seat caps since max_users was removed). "Assistant" means
     * every non-doctor user, System Managers included.
     */
    public function assertCanHaveAnotherActiveUser(Company $company, ?User $ignoreUser = null, bool $isDoctor = false): void
    {
        if (! $company->currentSubscription()->exists()) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company does not have an active subscription.'],
            ]);
        }

        $column = $isDoctor ? 'max_doctors' : 'max_assistants';
        $limit = $company->aggregatedSubscriptionLimit($column);
        $sameTypeUsers = $this->activeUsersQuery($company, $ignoreUser)->where('is_doctor', $isDoctor)->count();

        if ($limit !== null && ($sameTypeUsers + 1) > $limit) {
            throw ValidationException::withMessages([
                'is_doctor' => [$isDoctor
                    ? "Your subscription allows up to {$limit} active doctor(s). Upgrade your plan to add more doctors."
                    : "Your subscription allows up to {$limit} active assistant (non-doctor) user(s). Upgrade your plan to add more users."],
            ]);
        }
    }

    /**
     * Current seat usage vs. limits, company-wide (null limit = uncapped).
     *
     * @return array{doctors: array{used: int, limit: ?int}, assistants: array{used: int, limit: ?int}}
     */
    public function seatUsage(Company $company): array
    {
        $doctors = $this->activeUsersQuery($company)->where('is_doctor', true)->count();
        $assistants = $this->activeUsersQuery($company)->where('is_doctor', false)->count();

        return [
            'doctors' => ['used' => $doctors, 'limit' => $company->aggregatedSubscriptionLimit('max_doctors')],
            'assistants' => ['used' => $assistants, 'limit' => $company->aggregatedSubscriptionLimit('max_assistants')],
        ];
    }

    protected function activeUsersQuery(Company $company, ?User $ignoreUser = null)
    {
        return $company->users()
            ->where('status', 'active')
            ->when($ignoreUser, fn ($query) => $query->whereKeyNot($ignoreUser->id));
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
