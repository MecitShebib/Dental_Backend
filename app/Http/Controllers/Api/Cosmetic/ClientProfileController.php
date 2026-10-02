<?php

namespace App\Http\Controllers\Api\Cosmetic;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cosmetic\UpdateCosmeticClientProfileRequest;
use App\Http\Resources\CosmeticClientProfileResource;
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

        $profile = $client->cosmeticProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return $this->success(CosmeticClientProfileResource::make($profile));
    }

    public function update(UpdateCosmeticClientProfileRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->cosmeticProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $profile->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(CosmeticClientProfileResource::make($profile->fresh()), 'Clinical profile updated successfully.');
    }
}
