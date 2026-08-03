<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPartner\StoreLabPartnerRequest;
use App\Http\Requests\LabPartner\UpdateLabPartnerRequest;
use App\Http\Resources\LabPartnerResource;
use App\Models\LabPartner;
use Illuminate\Http\Request;

class LabPartnerController extends Controller
{
    public function index(Request $request)
    {
        $labPartners = $request->user()->company->labPartners()->orderBy('name')->get();

        return $this->success(LabPartnerResource::collection($labPartners));
    }

    public function store(StoreLabPartnerRequest $request)
    {
        $labPartner = $request->user()->company->labPartners()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return $this->success(LabPartnerResource::make($labPartner), 'Lab partner created successfully.', 201);
    }

    public function update(UpdateLabPartnerRequest $request, LabPartner $labPartner)
    {
        $labPartner->update($request->validated());

        return $this->success(LabPartnerResource::make($labPartner), 'Lab partner updated successfully.');
    }

    public function destroy(LabPartner $labPartner)
    {
        $labPartner->delete();

        return $this->success(null, 'Lab partner deleted successfully.');
    }
}
