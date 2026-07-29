<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateTreatmentRecordRequest;
use App\Http\Resources\TreatmentRecordResource;
use App\Models\Client;
use App\Services\TreatmentRecordService;

class ClientTreatmentRecordController extends Controller
{
    public function __construct(protected TreatmentRecordService $service) {}

    public function show(Client $client)
    {
        $record = $client->treatmentRecord()->with('teeth.treatmentCatalog')->firstOrCreate(
            ['client_id' => $client->id],
            ['currency_code' => 'TRY']
        );

        return $this->success(TreatmentRecordResource::make($record->load('teeth.treatmentCatalog')));
    }

    public function update(UpdateTreatmentRecordRequest $request, Client $client)
    {
        $record = $this->service->update($client, $request->validated(), $request->user()->id);

        return $this->success(TreatmentRecordResource::make($record), 'Treatment record updated successfully.');
    }
}
