<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralPractice\ConfirmGeneralFollowupCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\GeneralPractice\GeneralFollowupCarePlanService;

/**
 * Genervaria's one real endpoint so far -- see GeneralFollowupCarePlanService's
 * docblock for the caveat that its general follow-up cadence is a v1 prototype, not a
 * validated clinical protocol.
 */
class GeneralFollowupCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected GeneralFollowupCarePlanService $generalFollowupCarePlans) {}

    public function confirm(ConfirmGeneralFollowupCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this care plan is booked under.');

        $plan = $this->generalFollowupCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('complaint'),
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
