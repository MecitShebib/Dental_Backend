<?php

namespace App\Http\Controllers\Api\Pediatrics;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pediatrics\SavePediatricsVaccinationRequest;
use App\Http\Resources\PediatricsVaccinationResource;
use App\Models\Client;
use App\Models\PediatricsVaccination;
use Illuminate\Http\Request;

class VaccinationController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(PediatricsVaccinationResource::collection($client->pediatricsVaccinations()->get()));
    }

    public function store(SavePediatricsVaccinationRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->pediatricsVaccinations()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PediatricsVaccinationResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SavePediatricsVaccinationRequest $request, PediatricsVaccination $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PediatricsVaccinationResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, PediatricsVaccination $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
