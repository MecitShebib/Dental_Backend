<?php

namespace App\Http\Controllers\Api\GeneralPractice;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralPractice\SaveGeneralPracticeReferralRequest;
use App\Http\Resources\GeneralPracticeReferralResource;
use App\Models\Client;
use App\Models\GeneralPracticeReferral;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(GeneralPracticeReferralResource::collection($client->generalPracticeReferrals()->get()));
    }

    public function store(SaveGeneralPracticeReferralRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $record = $client->generalPracticeReferrals()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralPracticeReferralResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveGeneralPracticeReferralRequest $request, GeneralPracticeReferral $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $request->validated();

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralPracticeReferralResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, GeneralPracticeReferral $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }
}
