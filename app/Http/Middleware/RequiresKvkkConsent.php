<?php

namespace App\Http\Middleware;

use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every route that sends a patient's health data to OpenAI (AI
 * treatment-plan chat/generate/confirm, Whisper transcription) behind the
 * patient having signed the Company's KVKK Açık Rıza Beyanı (explicit
 * consent) template -- see KvkkConsentTemplateSeeder and the KVKK
 * compliance plan, docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md,
 * Görev 5.4. X-ray AI analysis is gated separately inside
 * AnalyzeXrayImageJob, since it is dispatched from a job, not a route.
 *
 * Relies on the `client` route parameter already being resolved to a
 * Client model -- true for every route this is attached to, since
 * SubstituteBindings (part of the framework's default api middleware
 * group) runs before route-specific middleware like this one.
 */
class RequiresKvkkConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->route('client');

        if ($client && ! $this->hasSignedExplicitConsent($client)) {
            if (! config('services.kvkk.ai_consent_required', true)) {
                // TEMPORARY bypass, see config/services.php's 'kvkk' block --
                // logged so this real compliance gap isn't invisible while
                // it's open.
                Log::warning('AI used without a signed KVKK consent (gate temporarily disabled).', [
                    'client_id' => $client->id,
                    'company_id' => $client->company_id,
                    'user_id' => $request->user()?->id,
                    'route' => $request->path(),
                ]);

                return $next($request);
            }

            throw ValidationException::withMessages([
                'client' => ['This patient has not signed the KVKK Açık Rıza Beyanı required before AI features can be used with their health data.'],
            ]);
        }

        return $next($request);
    }

    protected function hasSignedExplicitConsent($client): bool
    {
        return ClientConsent::query()
            ->where('client_id', $client->id)
            ->whereHas('template', fn ($query) => $query->where('kind', ConsentTemplate::KIND_KVKK_EXPLICIT_CONSENT))
            ->exists();
    }
}
