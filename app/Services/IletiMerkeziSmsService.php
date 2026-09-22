<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over İleti Merkezi's SMS API (single global account for the
 * whole SaaS, same "one shared provider" shape as the Infobip integration
 * it replaces -- not per-company credentials like WhatsApp/Zoho).
 *
 * Chosen specifically over a foreign provider (previously Infobip, based in
 * Croatia) so that this data flow -- phone numbers + OTP/appointment-
 * reminder message content -- is a domestic transfer under KVKK m.8 rather
 * than a cross-border one under KVKK m.9. See docs/kvkk-veri-envanteri.md.
 */
class IletiMerkeziSmsService
{
    protected const ENDPOINT = 'https://api.iletimerkezi.com/v1/send-sms/json';

    public function enabled(): bool
    {
        return (bool) config('services.iletimerkezi.enabled');
    }

    public function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\D+/', '', trim($mobile)) ?? '';
    }

    /**
     * Send free-form text to a mobile number via İleti Merkezi.
     * Returns whether the send succeeded (does not throw), so callers
     * that fan out to many recipients can log-and-continue on failure.
     */
    public function send(string $mobile, string $text): bool
    {
        $key = (string) config('services.iletimerkezi.api_key');
        $hash = (string) config('services.iletimerkezi.api_hash');

        if ($key === '' || $hash === '') {
            Log::error('İleti Merkezi API key or hash is not configured.');

            return false;
        }

        try {
            $response = Http::post(self::ENDPOINT, [
                'request' => [
                    'authentication' => [
                        'key' => $key,
                        'hash' => $hash,
                    ],
                    'order' => [
                        'sender' => (string) config('services.iletimerkezi.sender', 'Dentavaria'),
                        // '0': a transactional message tied to an existing
                        // relationship (OTP, appointment reminder) rather than
                        // marketing -- exempt from İYS opt-in checking.
                        'iys' => '0',
                        'message' => [
                            'text' => $text,
                            'receipents' => [
                                'number' => [$this->normalizeMobile($mobile)],
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (ConnectionException $e) {
            // Transport-level failure (DNS, TLS, timeout, refused). The Http
            // client throws these independently of ->throw(), so without this
            // catch the "does not throw" contract above was a lie and an OTP
            // login or a reminder fan-out died on a flaky network instead of
            // degrading to a logged failure.
            Log::error('İleti Merkezi SMS request could not reach the gateway.', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('İleti Merkezi SMS request failed unexpectedly.', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::error('İleti Merkezi SMS request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $payload = $response->json();
        $code = (int) ($payload['response']['status']['code'] ?? 0);

        if ($code !== 200) {
            Log::error('İleti Merkezi SMS response indicates the message was rejected.', [
                'payload' => $payload,
            ]);

            return false;
        }

        return true;
    }
}
