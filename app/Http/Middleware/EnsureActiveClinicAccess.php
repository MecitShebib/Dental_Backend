<?php

namespace App\Http\Middleware;

use App\Http\Resources\ApiTokenResource;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks the full login gate (user active, company active, subscription
 * active) on every authenticated mobile/SPA request, not just at login --
 * closes the gap where an admin deactivates a user/company or lets a
 * subscription lapse mid-session and the holder of an already-issued token
 * keeps working until it naturally expires. Revokes the token and returns
 * 401 with a specific reason so the SPA's existing 401 handler (logout +
 * redirect to /login, showing the message) takes over with no frontend
 * changes needed. Project admins aren't tied to a company, so they're exempt
 * -- same carve-out as SubscriptionAccessService::canLogin().
 */
class EnsureActiveClinicAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isProjectAdmin()) {
            return $next($request);
        }

        if (! $user->isActive()) {
            return $this->deny($user, 'Your account has been deactivated. Please contact your clinic administrator.');
        }

        $company = $user->company;

        if (! $company) {
            return $this->deny($user, 'Your account is no longer linked to a clinic.');
        }

        if ($company->status !== 'active') {
            return $this->deny($user, 'Your clinic account has been deactivated. Please contact support.');
        }

        if (! $company->currentSubscription()->exists()) {
            return $this->deny($user, 'Your clinic does not have an active subscription. Please contact your clinic administrator.');
        }

        // Integration tokens (Settings > API Token) only work while the
        // subscription includes API access. 403, not deny(): the token isn't
        // revoked, it starts working again if the feature is re-enabled.
        $token = $user->currentAccessToken();
        $tokenName = $token instanceof PersonalAccessToken ? (string) $token->name : '';
        if (str_starts_with($tokenName, ApiTokenResource::PREFIX) && ! $company->hasFeature('api_tokens')) {
            return response()->json(['message' => 'API access is not included in your clinic\'s subscription.'], 403);
        }

        return $next($request);
    }

    protected function deny($user, string $message): Response
    {
        $user->currentAccessToken()?->delete();

        return response()->json(['message' => $message], 401);
    }
}
