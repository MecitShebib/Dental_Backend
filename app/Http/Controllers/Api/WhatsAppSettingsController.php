<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesCompanySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\UpdateWhatsAppIntegrationRequest;
use App\Http\Resources\WhatsAppIntegrationResource;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

/**
 * Lets a company admin connect their own WhatsApp Business Cloud API
 * credentials -- see WhatsAppIntegration for storage and WhatsAppService/
 * MessagingService for how the credential is actually used to send.
 */
class WhatsAppSettingsController extends Controller
{
    use AuthorizesCompanySettings;

    public function show(Request $request)
    {
        $this->assertHasCompanySettingsAccess($request);

        $integration = $request->user()->company->whatsappIntegration;

        return $this->success($integration ? WhatsAppIntegrationResource::make($integration) : null);
    }

    public function update(UpdateWhatsAppIntegrationRequest $request)
    {
        $this->assertHasCompanySettingsAccess($request);

        $company = $request->user()->company;
        $integration = $company->whatsappIntegration()->updateOrCreate([], [
            ...$request->validated(),
            'status' => 'active',
            'connected_at' => now(),
            'last_error' => null,
        ]);

        return $this->success(WhatsAppIntegrationResource::make($integration), 'WhatsApp connected successfully.');
    }

    public function destroy(Request $request)
    {
        $this->assertHasCompanySettingsAccess($request);

        $request->user()->company->whatsappIntegration?->delete();

        return $this->success(null, 'WhatsApp disconnected.');
    }

    public function test(Request $request, WhatsAppService $whatsApp)
    {
        $this->assertHasCompanySettingsAccess($request);

        $data = $request->validate(['phone' => ['required', 'string']]);

        $sent = $whatsApp->send($request->user()->company, $data['phone'], 'This is a test message from your clinic system.');

        return $sent
            ? $this->success(null, 'Test message sent.')
            : $this->success(null, 'Failed to send test message -- check your credentials.', 422);
    }
}
