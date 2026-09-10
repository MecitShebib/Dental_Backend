<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\ConfirmNutritionCarePlanRequest;
use App\Http\Resources\CarePlanResource;
use App\Models\Client;
use App\Specialties\Nutrition\NutritionCarePlanService;

/**
 * Dietavaria's one real endpoint so far -- see NutritionCarePlanService's
 * docblock for the caveat that its session spacing is a v1 prototype, not a
 * validated per-program protocol.
 */
class NutritionCarePlanController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(protected NutritionCarePlanService $nutritionCarePlans) {}

    public function confirm(ConfirmNutritionCarePlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $doctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Select which doctor this follow-up program is booked under.');

        $plan = $this->nutritionCarePlans->confirmPlan(
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
            'Follow-up program confirmed and appointments created.',
            201,
        );
    }
}
