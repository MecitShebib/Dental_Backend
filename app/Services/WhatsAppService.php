<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
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

        try {
            $response = Http::withToken($integration->access_token)
                ->timeout(15)
                ->post("{$baseUrl}/{$integration->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalizePhone($to),
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);
        } catch (ConnectionException $e) {
            // Transport-level failure (DNS, TLS, timeout, refused): the Http
            // client throws these regardless of ->throw(), so without this
            // the documented "returns false, never throws" contract broke
            // and the exception escaped into whatever was fanning out
            // messages (reminders, recalls, booking confirmations).
            return $this->fail($company, $integration, 'WhatsApp send failed: could not reach the WhatsApp Cloud API.', $e);
        } catch (\Throwable $e) {
            return $this->fail($company, $integration, 'WhatsApp send failed unexpectedly.', $e);
        }

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

    /**
     * Logs a failed send and records it on the integration the same way the
     * non-2xx branch above does, then reports failure to the caller.
     */
    protected function fail(Company $company, $integration, string $message, \Throwable $e): bool
    {
        Log::error($message, [
            'company_id' => $company->id,
            'exception' => $e->getMessage(),
        ]);

        $integration->update(['last_error' => Str::limit($e->getMessage(), 500)]);

        return false;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
