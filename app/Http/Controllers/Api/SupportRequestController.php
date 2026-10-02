<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportRequestMail;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Services\CompanyUserLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Backs the in-app "Help & Support" page: how to renew/upgrade, add a
 * specialty, or top up AI tokens, plus a form that emails the platform's
 * support inbox.
 */
class SupportRequestController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit) {}

    public function info(Request $request)
    {
        $company = $request->user()->company;
        $subscriptions = $company->activeSubscriptions()->load('specialty');

        return $this->success([
            'contact' => [
                'email' => config('services.support.email'),
                'phone' => config('services.support.phone'),
                'whatsapp' => config('services.support.whatsapp') ?: config('services.support.phone'),
            ],
            'ai_token_price_per_million' => config('services.support.ai_token_price_per_million'),
            'subscriptions' => $subscriptions->map(fn (Subscription $subscription) => [
                'plan_name' => $subscription->plan_name,
                'specialty' => $subscription->specialty?->brand_name,
                'specialty_key' => $subscription->specialty?->key,
                'ends_at' => $subscription->ends_at?->format('Y-m-d'),
            ])->values(),
            'seats' => $this->companyUserLimit->seatUsage($company),
            'ai_tokens' => [
                'used' => $company->aggregatedSubscriptionUsage('ai_tokens_used'),
                'limit' => $company->aggregatedSubscriptionLimit('max_ai_tokens'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(SupportRequestMail::TYPE_LABELS))],
            'message' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'specialty_key' => ['nullable', 'string', 'exists:specialties,key'],
            'token_amount' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user()->loadMissing('company');
        $specialtyName = isset($data['specialty_key'])
            ? Specialty::query()->where('key', $data['specialty_key'])->value('brand_name')
            : null;

        // Sent synchronously, not queued: production has no worker/cron
        // running (see the shared-hosting deploy notes), so a queued mail
        // would silently never leave.
        try {
            Mail::to(config('services.support.email'))->send(new SupportRequestMail(
                $user,
                $data['type'],
                $data['message'] ?? null,
                $data['contact_phone'] ?? null,
                $specialtyName,
                $data['token_amount'] ?? null,
            ));
        } catch (\Throwable $exception) {
            Log::error('Support request email failed to send.', ['user_id' => $user->id, 'error' => $exception->getMessage()]);

            return response()->json([
                'message' => 'Your request could not be sent right now. Please contact us by phone or email instead.',
            ], 502);
        }

        return $this->success(null, 'Your request has been sent. Our team will contact you shortly.', 201);
    }
}
