<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin wrapper over Meta's WhatsApp Cloud API, using each company's own
 * connected credentials (see WhatsAppIntegration/WhatsAppSettingsController)
 * instead of a single global account -- every clinic brings its own
 * WhatsApp Business number.
 */
class WhatsAppService
{
    public function enabledFor(Company $company): bool
    {
        $integration = $company->whatsappIntegration;

        return (bool) ($integration && $integration->status === 'active');
    }

    public function send(Company $company, string $to, string $text): bool
    {
        $integration = $company->whatsappIntegration;

        if (! $integration || $integration->status !== 'active') {
            return false;
        }

        $baseUrl = rtrim((string) config('services.whatsapp.graph_base_url'), '/');

        $response = Http::withToken($integration->access_token)
            ->timeout(15)
            ->post("{$baseUrl}/{$integration->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($to),
                'type' => 'text',
                'text' => ['body' => $text],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp send failed.', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $integration->update(['last_error' => Str::limit((string) $response->body(), 500)]);

            return false;
        }

        return true;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
