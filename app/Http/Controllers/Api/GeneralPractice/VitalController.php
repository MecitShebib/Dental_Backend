<?php

namespace App\Http\Controllers\Api\GeneralPractice;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralPractice\SaveGeneralPracticeVitalRequest;
use App\Http\Resources\GeneralPracticeVitalResource;
use App\Models\Client;
use App\Models\GeneralPracticeVital;
use Illuminate\Http\Request;

class VitalController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(GeneralPracticeVitalResource::collection($client->generalPracticeVitals()->get()));
    }

    public function store(SaveGeneralPracticeVitalRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->generalPracticeVitals()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralPracticeVitalResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveGeneralPracticeVitalRequest $request, GeneralPracticeVital $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralPracticeVitalResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, GeneralPracticeVital $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
