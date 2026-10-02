<?php

namespace App\Http\Controllers\Api\Pediatrics;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pediatrics\SavePediatricsGrowthMeasurementRequest;
use App\Http\Resources\PediatricsGrowthMeasurementResource;
use App\Models\Client;
use App\Models\PediatricsGrowthMeasurement;
use Illuminate\Http\Request;

class GrowthMeasurementController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(PediatricsGrowthMeasurementResource::collection($client->pediatricsGrowthMeasurements()->get()));
    }

    public function store(SavePediatricsGrowthMeasurementRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->pediatricsGrowthMeasurements()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PediatricsGrowthMeasurementResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SavePediatricsGrowthMeasurementRequest $request, PediatricsGrowthMeasurement $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PediatricsGrowthMeasurementResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, PediatricsGrowthMeasurement $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
