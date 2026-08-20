<?php

namespace App\Observers;

use App\Jobs\PushClientToCrmJob;
use App\Models\Client;

/**
 * Single point every Client-creation path (staff-created, online-booked,
 * CSV-imported) funnels through for CRM sync, so nothing needs to remember
 * to call it explicitly -- see PushClientToCrmJob/ZohoCrmService.
 */
class ClientObserver
{
    public function created(Client $client): void
    {
        $company = $client->company;

        if ($company && $company->crmIntegration?->status === 'active') {
            PushClientToCrmJob::dispatch($client);
        }
    }
}
