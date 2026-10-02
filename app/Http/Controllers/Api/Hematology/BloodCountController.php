<?php

namespace App\Http\Controllers\Api\Hematology;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hematology\SaveHematologyBloodCountRequest;
use App\Http\Resources\HematologyBloodCountResource;
use App\Models\Client;
use App\Models\HematologyBloodCount;
use Illuminate\Http\Request;

class BloodCountController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(HematologyBloodCountResource::collection($client->hematologyBloodCounts()->get()));
    }

    public function store(SaveHematologyBloodCountRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->hematologyBloodCounts()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(HematologyBloodCountResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveHematologyBloodCountRequest $request, HematologyBloodCount $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(HematologyBloodCountResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, HematologyBloodCount $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
