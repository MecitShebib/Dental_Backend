<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gynecology\ConfirmPrenatalPlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\Gynecology\PrenatalCarePlanService;

/**
 * Gynevaria's one real endpoint so far -- see PrenatalCarePlanService's
 * docblock for the caveat that its milestone schedule is a v1 prototype, not
 * a validated clinical protocol.
 */
class PrenatalCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected PrenatalCarePlanService $prenatalCarePlans) {}

    public function confirm(ConfirmPrenatalPlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this prenatal plan is booked under.');

        $plan = $this->prenatalCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('last_menstrual_period'),
            $request->validated('preferred_start_time'),
            $actingUser->id,
        );

        return $this->success(
            CarePlanResource::make($plan->load(['specialty', 'doctor', 'sessions.appointment'])),
            'Prenatal care plan confirmed and appointments created.',
            201,
        );
    }
}
