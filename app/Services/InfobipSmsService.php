<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over Infobip's SMS API (single global account for the whole
 * SaaS, same "one shared provider" shape as the Turkey SMS integration it
 * replaces -- not per-company credentials like WhatsApp/Zoho).
 */
class InfobipSmsService
{
    public function enabled(): bool
    {
        return (bool) config('services.infobip.enabled');
    }

    public function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\D+/', '', trim($mobile)) ?? '';
    }

    /**
     * Send free-form text to a mobile number via Infobip.
     * Returns whether the send succeeded (does not throw), so callers
     * that fan out to many recipients can log-and-continue on failure.
     */
    public function send(string $mobile, string $text): bool
    {
        $apiKey = (string) config('services.infobip.api_key');
        $baseUrl = (string) config('services.infobip.base_url');

        if ($apiKey === '' || $baseUrl === '') {
            Log::error('Infobip API key or base URL is not configured.');

            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'App '.$apiKey,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->post(rtrim($baseUrl, '/').'/sms/2/text/advanced', [
                'messages' => [[
                    'destinations' => [['to' => $this->normalizeMobile($mobile)]],
                    'from' => (string) config('services.infobip.sender', 'Dentavaria'),
                    'text' => $text,
                ]],
            ]);

        if (! $response->successful()) {
            Log::error('Infobip SMS request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $payload = $response->json();

        // Infobip's status.groupId: 1=PENDING, 2=UNDELIVERABLE, 3=DELIVERED,
        // 4=EXPIRED, 5=REJECTED -- only REJECTED means the message was never
        // actually queued for delivery (everything else is "accepted", the
        // rest of the lifecycle happens async on Infobip's side).
        $groupId = $payload['messages'][0]['status']['groupId'] ?? null;

        if (! is_array($payload) || empty($payload['messages']) || $groupId === 5) {
            Log::error('Infobip SMS response indicates the message was rejected.', [
                'payload' => $payload,
            ]);

            return false;
        }

        return true;
    }
}
