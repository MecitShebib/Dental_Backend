<?php

namespace App\Services;

use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ClientConsentResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\VisitResource;
use App\Http\Resources\XrayImageResource;
use App\Models\Client;

/**
 * Answers a KVKK m.11 "verinin işlenip işlenmediğini/amacını öğrenme" and
 * data-portability request: everything the platform holds about one
 * patient, in one structured export. See ClientDataRequestController and
 * the KVKK compliance plan (docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md,
 * Görev 3.1).
 */
class ClientDataExportService
{
    public function export(Client $client): array
    {
        $client->load([
            'visits',
            'appointments',
            'payments',
            'treatmentCharges',
            'consents',
            'xrayImages',
            'labResults',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'client' => ClientResource::make($client)->resolve(),
            'visits' => VisitResource::collection($client->visits)->resolve(),
            'appointments' => AppointmentResource::collection($client->appointments)->resolve(),
            'payments' => PaymentResource::collection($client->payments)->resolve(),
            'treatment_charges' => $client->treatmentCharges->map(fn ($charge) => [
                'source_type' => $charge->source_type,
                'source_id' => $charge->source_id,
                'amount' => (float) $charge->amount,
                'description' => $charge->description,
                'created_at' => $charge->created_at?->toIso8601String(),
            ])->all(),
            'consents' => ClientConsentResource::collection($client->consents)->resolve(),
            'xray_images' => XrayImageResource::collection($client->xrayImages)->resolve(),
            'lab_results' => $client->labResults->map(fn ($result) => [
                'test_name' => $result->test_name,
                'result_value' => $result->result_value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'is_abnormal' => $result->is_abnormal,
                'test_date' => optional($result->test_date)->format('Y-m-d'),
                'notes' => $result->notes,
            ])->all(),
        ];
    }
}
