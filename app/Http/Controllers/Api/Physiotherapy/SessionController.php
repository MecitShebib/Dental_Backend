<?php

namespace App\Http\Controllers\Api\Physiotherapy;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Physiotherapy\SavePhysiotherapySessionRequest;
use App\Http\Resources\PhysiotherapySessionResource;
use App\Models\Client;
use App\Models\PhysiotherapySession;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(PhysiotherapySessionResource::collection($client->physiotherapySessions()->get()));
    }

    public function store(SavePhysiotherapySessionRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->physiotherapySessions()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PhysiotherapySessionResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SavePhysiotherapySessionRequest $request, PhysiotherapySession $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PhysiotherapySessionResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, PhysiotherapySession $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
