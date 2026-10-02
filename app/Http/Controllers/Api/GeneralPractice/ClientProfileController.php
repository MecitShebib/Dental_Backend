<?php

namespace App\Http\Controllers\Api\GeneralPractice;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralPractice\UpdateGeneralPracticeClientProfileRequest;
use App\Http\Resources\GeneralPracticeClientProfileResource;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * One clinical profile row per patient for this specialty (lazily created on
 * first read, same as Nutrition\ClientProfileController).
 */
class ClientProfileController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function show(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->generalPracticeProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return $this->success(GeneralPracticeClientProfileResource::make($profile));
    }

    public function update(UpdateGeneralPracticeClientProfileRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->generalPracticeProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $profile->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GeneralPracticeClientProfileResource::make($profile->fresh()), 'Clinical profile updated successfully.');
    }
}
