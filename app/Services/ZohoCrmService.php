<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pushes clients into a company's own connected Zoho CRM as Contacts, using
 * their own OAuth client_id/client_secret/refresh_token (see CrmIntegration/
 * CrmSettingsController) -- every clinic authorizes its own Zoho account,
 * there's no shared/global Zoho connection.
 */
class ZohoCrmService
{
    public function enabledFor(Company $company): bool
    {
        $integration = $company->crmIntegration;

        return (bool) ($integration && $integration->status === 'active');
    }

    /**
     * Returns a valid access token, refreshing via the stored refresh_token
     * if the cached one is missing/expired. Null on failure (also records
     * last_error on the integration for the settings page to surface).
     */
    public function accessToken(Company $company): ?string
    {
        $integration = $company->crmIntegration;

        if (! $integration) {
            return null;
        }

        if ($integration->access_token && $integration->access_token_expires_at?->isFuture()) {
            return $integration->access_token;
        }

        $accountsBaseUrl = rtrim($integration->accounts_base_url ?: (string) config('services.zoho_crm.accounts_base_url'), '/');

        $response = Http::asForm()->timeout(15)->post("{$accountsBaseUrl}/oauth/v2/token", [
            'grant_type' => 'refresh_token',
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'refresh_token' => $integration->refresh_token,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            Log::error('Zoho CRM token refresh failed.', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $integration->update(['last_error' => Str::limit((string) $response->body(), 500)]);

            return null;
        }

        $integration->update([
            'access_token' => $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->subMinute(),
            'last_error' => null,
        ]);

        return $response->json('access_token');
    }

    public function pushContact(Company $company, Client $client): bool
    {
        $integration = $company->crmIntegration;

        if (! $integration || $integration->status !== 'active') {
            return false;
        }

        $token = $this->accessToken($company);

        if (! $token) {
            return false;
        }

        $apiBaseUrl = rtrim($integration->api_base_url ?: (string) config('services.zoho_crm.api_base_url'), '/');
        [$firstName, $lastName] = $this->splitName($client->name);

        $response = Http::withHeaders(['Authorization' => "Zoho-oauthtoken {$token}"])
            ->timeout(15)
            ->post("{$apiBaseUrl}/crm/v3/Contacts", [
                'data' => [[
                    'First_Name' => $firstName,
                    'Last_Name' => $lastName,
                    'Phone' => $client->phone,
                    'Email' => $client->email,
                ]],
            ]);

        if (! $response->successful()) {
            Log::error('Zoho CRM contact push failed.', [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $integration->update(['last_error' => Str::limit((string) $response->body(), 500)]);

            return false;
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string} [firstName, lastName]
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? '-'];
    }
}
