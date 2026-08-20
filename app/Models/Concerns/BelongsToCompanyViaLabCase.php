<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models that hang off a `lab_case_id` (no client_id or company_id of
 * their own). Same rationale as BelongsToCompanyViaClient, just one hop
 * further: scopes to the authenticated sanctum API user's company via
 * labCase.client.company_id.
 */
trait BelongsToCompanyViaLabCase
{
    public static function bootBelongsToCompanyViaLabCase(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if ($user = auth('sanctum')->user()) {
                $builder->whereHas('labCase.client', function (Builder $query) use ($user): void {
                    $query->where('company_id', $user->company_id);
                });
            }
        });
    }
}
