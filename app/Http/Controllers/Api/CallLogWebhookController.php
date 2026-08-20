<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallLog\CallWebhookRequest;
use App\Http\Resources\CallLogResource;
use App\Models\Company;
use App\Services\CallLogService;
use Illuminate\Http\Request;

/**
 * Generic telephony-provider webhook -- any Virtual PBX/VoIP system able to
 * POST a call event to a URL can feed call logs in automatically, instead of
 * a receptionist typing every call by hand. Not tied to any specific vendor
 * (no vendor account was chosen), authenticated by a per-company secret the
 * admin generates and pastes into their provider's webhook config (see
 * Settings > Call Webhook / CallLogSettingsController).
 */
class CallLogWebhookController extends Controller
{
    public function __invoke(CallWebhookRequest $request, Company $company, CallLogService $calls)
    {
        $this->assertAuthorized($request, $company);

        $log = $calls->logFromWebhook($company, $request->validated());

        return $this->success(CallLogResource::make($log->load('client')), 'Call logged successfully.', 201);
    }

    protected function assertAuthorized(Request $request, Company $company): void
    {
        $secret = $request->header('X-Webhook-Secret') ?? $request->string('webhook_secret')->toString();

        abort_if(
            ! $company->call_webhook_secret || ! hash_equals($company->call_webhook_secret, (string) $secret),
            401,
            'Invalid webhook secret.'
        );
    }
}
