<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

/**
 * Mirrors CompanyUserLimitService: max_branches is set on the Subscription
 * (by the project admin, at subscription time -- not on the Company itself),
 * the company admin creates the actual Branch records against that cap.
 */
class CompanyBranchLimitService
{
    public function assertCanHaveAnotherBranch(Company $company, ?Branch $ignoreBranch = null): void
    {
        if (! $company->currentSubscription()->exists()) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company does not have an active subscription.'],
            ]);
        }

        $branchCount = $company->branches()
            ->when($ignoreBranch, fn ($query) => $query->whereKeyNot($ignoreBranch->id))
            ->count();

        $maxBranches = $company->aggregatedSubscriptionLimit('max_branches');

        if ($maxBranches !== null && ($branchCount + 1) > $maxBranches) {
            throw ValidationException::withMessages([
                'name' => ['Branches cannot exceed the company subscription max branches limit.'],
            ]);
        }
    }
}
