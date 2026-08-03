<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models with a required `doctor_id` but no company_id column of their
 * own (DoctorSchedule). See BelongsToCompany for the guard/surface rationale.
 */
trait BelongsToCompanyViaDoctor
{
    public static function bootBelongsToCompanyViaDoctor(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if ($user = auth('sanctum')->user()) {
                $builder->whereHas('doctor', function (Builder $query) use ($user): void {
                    $query->where('company_id', $user->company_id);
                });
            }
        });
    }
}
