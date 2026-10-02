<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hematology\ConfirmBloodCountCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\Hematology\BloodCountCarePlanService;

/**
 * Hemavaria's one real endpoint so far -- see BloodCountCarePlanService's
 * docblock for the caveat that its blood count cadence is a v1 prototype, not a
 * validated clinical protocol.
 */
class BloodCountCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected BloodCountCarePlanService $bloodCountCarePlans) {}

    public function confirm(ConfirmBloodCountCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this care plan is booked under.');

        $plan = $this->bloodCountCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('diagnosis'),
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
