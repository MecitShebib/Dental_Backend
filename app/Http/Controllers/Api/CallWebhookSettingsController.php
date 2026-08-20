<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lets a company admin generate the secret their telephony provider needs to
 * post call events to CallLogWebhookController -- see Company::callWebhookUrl().
 */
class CallWebhookSettingsController extends Controller
{
    use AuthorizesAccounting;

    public function show(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $company = $request->user()->company;

        return $this->success([
            'webhook_url' => $company->callWebhookUrl(),
            'has_secret' => (bool) $company->call_webhook_secret,
        ]);
    }

    public function regenerate(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $company = $request->user()->company;
        $secret = Str::random(40);
        $company->update(['call_webhook_secret' => $secret]);

        return $this->success([
            'webhook_url' => $company->callWebhookUrl(),
            // Only ever returned once, right after generation -- same
            // one-time-reveal convention as Sanctum API tokens.
            'webhook_secret' => $secret,
        ], 'Webhook secret regenerated. Copy it now -- it will not be shown again.');
    }
}
