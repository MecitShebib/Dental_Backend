<?php

namespace App\Http\Controllers\Api\InternalMedicine;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\InternalMedicine\SaveInternalMedicineVitalRequest;
use App\Http\Resources\InternalMedicineVitalResource;
use App\Models\Client;
use App\Models\InternalMedicineVital;
use Illuminate\Http\Request;

class VitalController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(InternalMedicineVitalResource::collection($client->internalMedicineVitals()->get()));
    }

    public function store(SaveInternalMedicineVitalRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->internalMedicineVitals()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(InternalMedicineVitalResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveInternalMedicineVitalRequest $request, InternalMedicineVital $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(InternalMedicineVitalResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, InternalMedicineVital $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
