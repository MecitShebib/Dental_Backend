<?php

namespace App\Http\Controllers\Api\GeneralSurgery;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralSurgery\SaveGeneralSurgeryOperationRequest;
use App\Http\Resources\GeneralSurgeryOperationResource;
use App\Models\Client;
use App\Models\GeneralSurgeryOperation;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(GeneralSurgeryOperationResource::collection($client->generalSurgeryOperations()->get()));
    }

    public function store(SaveGeneralSurgeryOperationRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->generalSurgeryOperations()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralSurgeryOperationResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveGeneralSurgeryOperationRequest $request, GeneralSurgeryOperation $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralSurgeryOperationResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, GeneralSurgeryOperation $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
