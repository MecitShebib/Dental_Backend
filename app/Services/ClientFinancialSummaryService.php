<?php

namespace App\Services;

use App\Models\Client;

class ClientFinancialSummaryService
{
    public function summary(Client $client): array
    {
        // treatment_records.total_services_amount is legacy plumbing for the retired
        // V1 odontogram (it only gets populated by a `teeth` payload the current V2
        // frontend never sends, and is zeroed out on every treatment-record save) --
        // treatment_charges is the single, current source of truth for what a client
        // owes: a manual fee, an AI-confirmed plan, a visit, or an appointment all
        // land here as one row each, so this total updates correctly on add/edit/delete
        // for all four without needing to touch this service again.
        $totalServices = (float) $client->treatmentCharges()->sum('amount');
        $totalPaid = (float) $client->payments()->sum('amount');

        return [
            'total_services_amount' => round($totalServices, 2),
            'total_paid_amount' => round($totalPaid, 2),
            'remaining_amount' => round($totalServices - $totalPaid, 2),
        ];
    }
}
