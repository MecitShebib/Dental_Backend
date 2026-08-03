<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models that always have a required `client_id` but no company_id
 * column of their own (Visit, Payment, TreatmentRecord, TreatmentCharge).
 * Scopes queries to the authenticated sanctum API user's company via the
 * related client. See BelongsToCompany for the guard/surface rationale.
 */
trait BelongsToCompanyViaClient
{
    public static function bootBelongsToCompanyViaClient(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if ($user = auth('sanctum')->user()) {
                $builder->whereHas('client', function (Builder $query) use ($user): void {
                    $query->where('company_id', $user->company_id);
                });
            }
        });
    }
}
