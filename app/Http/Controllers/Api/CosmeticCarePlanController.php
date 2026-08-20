<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cosmetic\ConfirmCosmeticCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\Cosmetic\CosmeticCarePlanService;

/**
 * Estevaria's one real endpoint so far -- see CosmeticCarePlanService's
 * docblock for the caveat that its session spacing is a v1 prototype, not a
 * validated per-treatment protocol.
 */
class CosmeticCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected CosmeticCarePlanService $cosmeticCarePlans) {}

    public function confirm(ConfirmCosmeticCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this treatment package is booked under.');

        $plan = $this->cosmeticCarePlans->confirmPlan(
            $client,
            $doctor,
            $request->validated('treatment_code'),
            $request->validated('session_count'),
            $request->validated('interval_days'),
            $request->validated('start_date'),
            $request->validated('preferred_start_time'),
            $actingUser->id,
        );

        return $this->success(
            CarePlanResource::make($plan->load(['specialty', 'doctor', 'sessions.appointment'])),
            'Treatment package confirmed and appointments created.',
            201,
        );
    }
}
