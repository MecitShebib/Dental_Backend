<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralSurgery\ConfirmPerioperativeCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\GeneralSurgery\PerioperativeCarePlanService;

/**
 * Surgivaria's one real endpoint so far -- see PerioperativeCarePlanService's
 * docblock for the caveat that its perioperative cadence is a v1 prototype, not a
 * validated clinical protocol.
 */
class PerioperativeCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected PerioperativeCarePlanService $perioperativeCarePlans) {}

    public function confirm(ConfirmPerioperativeCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this care plan is booked under.');

        $plan = $this->perioperativeCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('operation'),
            $request->validated('start_date'),
            $request->validated('preferred_start_time'),
            $actingUser->id,
        );

        return $this->success(
            CarePlanResource::make($plan->load(['specialty', 'doctor', 'sessions.appointment'])),
            'Care plan confirmed and appointments created.',
            201,
        );
    }
}
