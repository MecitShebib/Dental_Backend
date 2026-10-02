<?php

namespace App\Http\Controllers\Api\GeneralSurgery;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralSurgery\SaveGeneralSurgeryFollowupRequest;
use App\Http\Resources\GeneralSurgeryFollowupResource;
use App\Models\Client;
use App\Models\GeneralSurgeryFollowup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FollowupController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(GeneralSurgeryFollowupResource::collection($client->generalSurgeryFollowups()->get()));
    }

    public function store(SaveGeneralSurgeryFollowupRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();
        $this->assertOperationBelongsToClient($data, $client);

        $record = $client->generalSurgeryFollowups()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralSurgeryFollowupResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveGeneralSurgeryFollowupRequest $request, GeneralSurgeryFollowup $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();
        $this->assertOperationBelongsToClient($data, $record->client);

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralSurgeryFollowupResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, GeneralSurgeryFollowup $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }

    protected function assertOperationBelongsToClient(array $data, Client $client): void
    {
        if (! empty($data['operation_id']) && ! $client->generalSurgeryOperations()->whereKey($data['operation_id'])->exists()) {
            throw ValidationException::withMessages([
                'operation_id' => ['The selected record does not belong to this patient.'],
            ]);
        }
    }
}
