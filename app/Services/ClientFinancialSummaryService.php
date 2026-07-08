<?php

namespace App\Services;

use App\Models\Client;

class ClientFinancialSummaryService
{
    public function summary(Client $client): array
    {
        $totalServices = (float) optional($client->treatmentRecord)->total_services_amount
            + (float) $client->aiTreatmentPlanCharges()->sum('amount');
        $totalPaid = (float) $client->payments()->sum('amount');

        return [
            'total_services_amount' => round($totalServices, 2),
            'total_paid_amount' => round($totalPaid, 2),
            'remaining_amount' => round($totalServices - $totalPaid, 2),
        ];
    }
}
