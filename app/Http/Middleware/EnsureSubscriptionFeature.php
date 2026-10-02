<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `subscription.feature:<key>` -- 403 unless the acting user's company has
 * that optional feature (Subscription::FEATURES) in an active subscription.
 * The SPA hides the same features, so this only fires for stale tabs or
 * hand-crafted requests. Project admins aren't tied to a company.
 */
class EnsureSubscriptionFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user && ! $user->isProjectAdmin() && ! $user->company?->hasFeature($feature)) {
            return response()->json([
                'message' => 'This feature is not included in your subscription.',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
