<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\InternalMedicine\ConfirmChronicCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\InternalMedicine\ChronicCarePlanService;

/**
 * Medivaria's one real endpoint so far -- see ChronicCarePlanService's
 * docblock for the caveat that its follow-up cadence is a v1 prototype, not
 * a validated clinical protocol.
 */
class ChronicCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected ChronicCarePlanService $chronicCarePlans) {}

    public function confirm(ConfirmChronicCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this follow-up plan is booked under.');

        $plan = $this->chronicCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('condition'),
            $request->validated('start_date'),
            $request->validated('preferred_start_time'),
            $actingUser->id,
        );

        return $this->success(
            CarePlanResource::make($plan->load(['specialty', 'doctor', 'sessions.appointment'])),
            'Chronic care follow-up plan confirmed and appointments created.',
            201,
        );
    }
}
