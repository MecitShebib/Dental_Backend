<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\CarePlanResource;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Specialties\Nutrition\NutritionPlanStructure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manual add/edit of a client's diet plan or exercise plan. The AI
 * assistant already writes these onto a CarePlan row when a nutrition plan
 * is confirmed; this lets the dietitian write a new one by hand or correct
 * an existing one (AI-made or manual) -- same diet_plan/exercise_plan
 * columns, so the Diet Plan / Exercise Plan tabs show both kinds alike.
 */
class DietExercisePlanController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(protected ClientSpecialtyEnrollmentService $enrollment) {}

    public function store(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);
        $data = $this->validated($request);
        $specialty = Specialty::query()->where('key', 'nutrition')->firstOrFail();
        $actingUser = $request->user();

        $doctorId = $actingUser->isDoctorOnly()
            ? $actingUser->id
            : $client->specialtyRecords()->where('specialty_id', $specialty->id)->value('primary_doctor_id');

        if (! $doctorId) {
            throw ValidationException::withMessages([
                'doctor_id' => ['This client has no assigned dietitian yet. Assign a doctor to the client first.'],
            ]);
        }

        if ($actingUser->isDoctorOnly()) {
            $this->enrollment->ensureEnrolled($client, $actingUser);
        }

        $plan = CarePlan::create([
            'company_id' => $client->company_id,
            'specialty_id' => $specialty->id,
            'client_id' => $client->id,
            'doctor_id' => $doctorId,
            'created_by' => $actingUser->id,
            'title' => $data['title'] ?: now()->toDateString(),
            ...$this->planColumns($data),
            'status' => 'confirmed',
        ]);

        return $this->success(CarePlanResource::make($plan->load(['specialty', 'doctor'])), 'Plan saved.', 201);
    }

    public function update(Request $request, CarePlan $carePlan)
    {
        abort_unless($carePlan->specialty?->key === 'nutrition', 404);
        $this->assertActingDoctorOwnsClient($request, $carePlan->client);
        $data = $this->validated($request);

        $carePlan->update([
            'title' => $data['title'] ?: $carePlan->title,
            ...$this->planColumns($data),
        ]);

        return $this->success(CarePlanResource::make($carePlan->load(['specialty', 'doctor'])), 'Plan updated.');
    }

    /**
     * `data` is the table-shaped plan (NutritionPlanStructure); `body` is the
     * older free-text form, still accepted so existing clients keep working.
     */
    protected function validated(Request $request): array
    {
        $kind = $request->input('kind');
        $structureRules = $kind === 'exercise'
            ? NutritionPlanStructure::exerciseRules('data')
            : NutritionPlanStructure::dietRules('data');

        return $request->validate([
            'kind' => ['required', Rule::in(['diet', 'exercise'])],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required_without:data', 'nullable', 'string', 'max:20000'],
            ...$structureRules,
            'data' => ['required_without:body', 'nullable', 'array'],
        ]) + ['title' => null, 'body' => null, 'data' => null];
    }

    /** Structured table + its readable text rendering, or legacy text alone. */
    protected function planColumns(array $data): array
    {
        $isDiet = $data['kind'] === 'diet';

        if ($data['data'] === null) {
            return [$isDiet ? 'diet_plan' : 'exercise_plan' => $data['body']];
        }

        if ($isDiet) {
            $diet = NutritionPlanStructure::normalizeDiet($data['data']);

            return ['diet_plan_data' => $diet, 'diet_plan' => NutritionPlanStructure::dietToText($diet)];
        }

        $exercise = NutritionPlanStructure::normalizeExercise($data['data']);

        return ['exercise_plan_data' => $exercise, 'exercise_plan' => NutritionPlanStructure::exerciseToText($exercise)];
    }
}
