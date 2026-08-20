<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;

/**
 * Read-only timeline of every care plan a client has, across every
 * specialty -- CarePlan itself is already specialty-agnostic core data (see
 * CarePlanService), so this needed no per-specialty branching to build.
 */
class ClientCarePlanController extends Controller
{
    public function index(Client $client)
    {
        $plans = $client->carePlans()
            ->with(['specialty', 'doctor', 'sessions.appointment'])
            ->latest()
            ->get();

        return $this->success(CarePlanResource::collection($plans));
    }
}
