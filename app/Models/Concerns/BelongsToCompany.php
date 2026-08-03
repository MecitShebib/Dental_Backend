<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models with their own `company_id` column. Scopes every query to the
 * authenticated mobile/SPA API user's company and auto-fills company_id on
 * create. Only activates for the `sanctum` guard, so it never touches the
 * Admin Panel's `web`-guard queries (which legitimately span companies),
 * artisan commands, seeders, or tests.
 *
 * On the User model itself, resolving auth('sanctum')->user() requires
 * Sanctum to query the users table, which re-enters this same scope before
 * the guard has cached its result -- an infinite loop. The static flag below
 * breaks that cycle: while a resolution is already in flight, nested calls
 * skip scoping instead of re-triggering another resolution.
 */
trait BelongsToCompany
{
    protected static bool $resolvingBelongsToCompanyActor = false;

    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if ($user = static::resolveBelongsToCompanyActor()) {
                $builder->where($builder->getModel()->qualifyColumn('company_id'), $user->company_id);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->company_id) && $user = static::resolveBelongsToCompanyActor()) {
                $model->company_id = $user->company_id;
            }
        });
    }

    protected static function resolveBelongsToCompanyActor(): ?User
    {
        if (static::$resolvingBelongsToCompanyActor) {
            return null;
        }

        static::$resolvingBelongsToCompanyActor = true;

        try {
            return auth('sanctum')->user();
        } finally {
            static::$resolvingBelongsToCompanyActor = false;
        }
    }
}
