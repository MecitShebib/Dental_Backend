<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiTreatmentPlan\AddAiTreatmentPlanChargeRequest;
use App\Http\Requests\AiTreatmentPlan\ConfirmAiTreatmentPlanRequest;
use App\Http\Requests\AiTreatmentPlan\PreviewAiTreatmentPlanRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Client;
use App\Models\User;
use App\Services\AiTokenUsageService;
use App\Services\AiTreatmentPlanService;
use App\Services\ClientFinancialSummaryService;
use App\Services\OpenAiClient;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanController extends Controller
{
    public function __construct(
        protected AiTreatmentPlanService $plans,
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
    ) {}

    public function preview(PreviewAiTreatmentPlanRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);
        $this->aiTokenUsage->assertCanUseAiTokens($doctor->company);

        $description = (string) ($request->validated('description') ?? '');

        if ($request->hasFile('audio')) {
            $description = $this->openAi->transcribe($request->file('audio'));
        }

        $plan = $this->plans->preview($doctor, $client, $description);

        return $this->success($plan, 'AI treatment plan generated successfully.');
    }

    public function confirm(ConfirmAiTreatmentPlanRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);

        $appointments = $this->plans->confirm($client, $doctor, $request->validated('sessions'), $doctor->id);

        return $this->success(AppointmentResource::collection($appointments), 'Treatment plan confirmed and appointments created.', 201);
    }

    public function addCharge(AddAiTreatmentPlanChargeRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);

        $client->aiTreatmentPlanCharges()->create([
            'amount' => $request->validated('amount'),
            'description' => $request->validated('description'),
            'created_by' => $doctor->id,
        ]);

        return $this->success(
            app(ClientFinancialSummaryService::class)->summary($client),
            'Charge recorded successfully.',
            201,
        );
    }

    protected function assertIsDoctor(User $user): void
    {
        if (! $user->is_doctor) {
            throw ValidationException::withMessages([
                'doctor' => ['Only doctors can use the AI treatment assistant.'],
            ]);
        }
    }
}
