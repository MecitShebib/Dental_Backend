<?php

namespace App\Http\Middleware;

use App\Http\Resources\ApiTokenResource;
use App\Support\LegalContent;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the clinic API until the signed-in user has personally accepted the
 * current Terms of Service (POST /api/auth/accept-terms). Acceptance is what
 * binds "one account = one named person": the person who accepts confirms
 * that only they will use the account and that everything done under it is
 * attributed to them. 403 with code=terms_not_accepted (not 401) -- the token
 * stays valid, the SPA just shows its acceptance screen.
 *
 * Integration tokens (Settings > API Token) are machine clients, not a person
 * signing in, so they're exempt; project admins aren't clinic users.
 */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasAcceptedCurrentTerms()) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        $tokenName = $token instanceof PersonalAccessToken ? (string) $token->name : '';
        if (str_starts_with($tokenName, ApiTokenResource::PREFIX)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must accept the Terms of Service before using the application.',
            'code' => 'terms_not_accepted',
            'terms_version' => LegalContent::TERMS_VERSION,
        ], 403);
    }
}
