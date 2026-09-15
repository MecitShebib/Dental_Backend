<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\UpdateNutritionClientProfileRequest;
use App\Http\Resources\NutritionClientProfileResource;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientProfileController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function show(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->nutritionProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return $this->success(NutritionClientProfileResource::make($profile));
    }

    public function update(UpdateNutritionClientProfileRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->nutritionProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $profile->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(NutritionClientProfileResource::make($profile), 'Nutrition profile updated successfully.');
    }
}
