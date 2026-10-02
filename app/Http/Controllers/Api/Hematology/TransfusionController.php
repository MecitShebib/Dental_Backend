<?php

namespace App\Http\Controllers\Api\Hematology;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hematology\SaveHematologyTransfusionRequest;
use App\Http\Resources\HematologyTransfusionResource;
use App\Models\Client;
use App\Models\HematologyTransfusion;
use Illuminate\Http\Request;

class TransfusionController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(HematologyTransfusionResource::collection($client->hematologyTransfusions()->get()));
    }

    public function store(SaveHematologyTransfusionRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->hematologyTransfusions()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(HematologyTransfusionResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveHematologyTransfusionRequest $request, HematologyTransfusion $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(HematologyTransfusionResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, HematologyTransfusion $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
