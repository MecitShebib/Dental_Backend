<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orthopedics\ConfirmRehabCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\Orthopedics\RehabCarePlanService;

/**
 * Orthovaria's one real endpoint so far -- see RehabCarePlanService's
 * docblock for the caveat that its rehab cadence is a v1 prototype, not a
 * validated clinical protocol.
 */
class RehabCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected RehabCarePlanService $rehabCarePlans) {}

    public function confirm(ConfirmRehabCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this rehab plan is booked under.');

        $plan = $this->rehabCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('injury'),
            $request->validated('start_date'),
            $request->validated('preferred_start_time'),
            $actingUser->id,
        );

        return $this->success(
            CarePlanResource::make($plan->load(['specialty', 'doctor', 'sessions.appointment'])),
            'Rehab care plan confirmed and appointments created.',
            201,
        );
    }
}
