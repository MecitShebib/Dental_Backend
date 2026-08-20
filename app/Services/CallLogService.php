<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\Company;

class CallLogService
{
    /**
     * @param  array{phone_number: string, direction: string, status: string, duration_seconds?: ?int, occurred_at: string, notes?: ?string}  $data
     */
    public function log(Company $company, array $data, ?int $createdBy): CallLog
    {
        return CallLog::create([
            ...$data,
            'company_id' => $company->id,
            'client_id' => $this->matchClient($company, $data['phone_number'])?->id,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Called from the public telephony webhook -- unlike log(), this is
     * idempotent on external_id (a provider retrying a delivery must not
     * create a duplicate row) and never has an acting user.
     *
     * @param  array{phone_number: string, direction: string, status: string, duration_seconds?: ?int, recording_url?: ?string, occurred_at: string, external_id: string}  $data
     */
    public function logFromWebhook(Company $company, array $data): CallLog
    {
        $existing = CallLog::query()
            ->where('company_id', $company->id)
            ->where('external_id', $data['external_id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return CallLog::create([
            ...$data,
            'company_id' => $company->id,
            'client_id' => $this->matchClient($company, $data['phone_number'])?->id,
        ]);
    }

    public function markFollowedUp(CallLog $callLog): CallLog
    {
        $callLog->update(['followed_up_at' => now()]);

        return $callLog;
    }

    protected function matchClient(Company $company, string $phoneNumber): ?Client
    {
        $normalized = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if ($normalized === '') {
            return null;
        }

        return Client::query()
            ->where('company_id', $company->id)
            ->get(['id', 'phone'])
            ->first(fn (Client $client) => preg_replace('/\D+/', '', (string) $client->phone) === $normalized);
    }
}
