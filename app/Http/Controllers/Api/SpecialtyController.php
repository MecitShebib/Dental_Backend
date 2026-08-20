<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialtyResource;
use App\Models\Specialty;
use App\Specialties\SpecialtyModuleRegistry;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function __construct(protected SpecialtyModuleRegistry $modules) {}

    /**
     * Every specialty on the platform, ordered for display, each flagged
     * with whether the requester's own company currently subscribes to it
     * and whether it has a real clinical module behind it yet -- the data
     * the Zoho-style launcher screen needs to decide what to show and what
     * to grey out.
     */
    public function index(Request $request)
    {
        $company = $request->user()->company;
        $subscribedIds = $company->activeSpecialties()->pluck('id')->all();

        $specialties = Specialty::query()->orderBy('sort_order')->get()->map(function (Specialty $specialty) use ($subscribedIds) {
            $specialty->setAttribute('is_built', $this->modules->get($specialty->key)?->isBuilt() ?? false);
            $specialty->setAttribute('is_subscribed', in_array($specialty->id, $subscribedIds, true));

            return $specialty;
        });

        return $this->success(SpecialtyResource::collection($specialties));
    }
}
