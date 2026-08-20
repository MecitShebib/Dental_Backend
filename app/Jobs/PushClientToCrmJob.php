<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\ZohoCrmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushClientToCrmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Client $client) {}

    public function handle(ZohoCrmService $crm): void
    {
        $company = $this->client->company;

        if (! $company) {
            return;
        }

        $crm->pushContact($company, $this->client);
    }
}
