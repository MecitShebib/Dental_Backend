<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateCrmIntegrationRequest;
use App\Http\Resources\CrmIntegrationResource;
use App\Services\ZohoCrmService;
use Illuminate\Http\Request;

/**
 * Lets a company admin connect their own Zoho CRM (OAuth client_id/secret +
 * refresh_token obtained from their Zoho API console) -- see CrmIntegration
 * for storage and ZohoCrmService/ClientObserver for how it's used to push
 * every new client as a Zoho Contact automatically.
 */
class CrmSettingsController extends Controller
{
    use AuthorizesAccounting;

    public function show(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $integration = $request->user()->company->crmIntegration;

        return $this->success($integration ? CrmIntegrationResource::make($integration) : null);
    }

    public function update(UpdateCrmIntegrationRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $company = $request->user()->company;
        $integration = $company->crmIntegration()->updateOrCreate([], [
            ...$request->validated(),
            'provider' => 'zoho',
            'status' => 'active',
            'connected_at' => now(),
            'access_token' => null,
            'access_token_expires_at' => null,
            'last_error' => null,
        ]);

        return $this->success(CrmIntegrationResource::make($integration), 'CRM connected successfully.');
    }

    public function destroy(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->user()->company->crmIntegration?->delete();

        return $this->success(null, 'CRM disconnected.');
    }

    public function test(Request $request, ZohoCrmService $crm)
    {
        $this->assertHasAccountingAccess($request);

        $token = $crm->accessToken($request->user()->company);

        return $token
            ? $this->success(null, 'Connection successful.')
            : $this->success(null, 'Failed to connect -- check your credentials.', 422);
    }
}
