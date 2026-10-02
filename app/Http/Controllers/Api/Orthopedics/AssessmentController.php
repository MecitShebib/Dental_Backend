<?php

namespace App\Http\Controllers\Api\Orthopedics;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orthopedics\SaveOrthopedicsAssessmentRequest;
use App\Http\Resources\OrthopedicsAssessmentResource;
use App\Models\Client;
use App\Models\OrthopedicsAssessment;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(OrthopedicsAssessmentResource::collection($client->orthopedicsAssessments()->get()));
    }

    public function store(SaveOrthopedicsAssessmentRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->orthopedicsAssessments()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(OrthopedicsAssessmentResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveOrthopedicsAssessmentRequest $request, OrthopedicsAssessment $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(OrthopedicsAssessmentResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, OrthopedicsAssessment $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
