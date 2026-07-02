<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TreatmentRecord;
use Illuminate\Support\Facades\DB;

class TreatmentRecordService
{
    public function update(Client $client, array $payload, ?int $userId = null): TreatmentRecord
    {
        return DB::transaction(function () use ($client, $payload, $userId) {
            $record = $client->treatmentRecord()->firstOrCreate(
                [],
                [
                    'treatment_plan' => $payload['treatment_plan'] ?? null,
                    'currency_code' => $payload['currency_code'] ?? 'SYP',
                    'notes' => $payload['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            $record->fill([
                'treatment_plan' => $payload['treatment_plan'] ?? null,
                'currency_code' => $payload['currency_code'] ?? $record->currency_code,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $userId,
            ])->save();

            $record->teeth()->delete();

            $teeth = collect($payload['teeth'] ?? [])->map(function (array $tooth) {
                return [
                    'tooth_number' => $tooth['tooth_number'],
                    'treatment_catalog_id' => $tooth['treatment_catalog_id'],
                    'unit_price' => $tooth['unit_price'],
                    'notes' => $tooth['notes'] ?? null,
                ];
            });

            if ($teeth->isNotEmpty()) {
                $record->teeth()->createMany($teeth->all());
            }

            $record->total_services_amount = $teeth->sum('unit_price');
            $record->save();

            return $record->load('teeth.treatmentCatalog');
        });
    }
}
