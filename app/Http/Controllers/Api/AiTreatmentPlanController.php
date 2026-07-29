<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiTreatmentPlan\AddAiTreatmentPlanChargeRequest;
use App\Http\Requests\AiTreatmentPlan\ConfirmAiTreatmentPlanRequest;
use App\Http\Requests\AiTreatmentPlan\PreviewAiTreatmentPlanRequest;
use App\Http\Requests\AiTreatmentPlan\TranscribeAiTreatmentPlanAudioRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Client;
use App\Models\TreatmentCharge;
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
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);
        $this->aiTokenUsage->assertCanUseAiTokens($actingUser->company);

        $treatingDoctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null);

        $description = (string) ($request->validated('description') ?? '');

        if ($request->hasFile('audio')) {
            $description = $this->openAi->transcribe($request->file('audio'));
        }

        $plan = $this->plans->preview($treatingDoctor, $actingUser, $client, $description);

        return $this->success($plan, 'AI treatment plan generated successfully.');
    }

    /**
     * Transcribes a recording as soon as it's captured, so the doctor/system
     * manager sees (and can edit) the text before spending an AI-plan
     * generation call on it -- unlike preview(), this isn't gated by the
     * company's AI token cap, since Whisper usage isn't counted against it
     * (only the chat-completion call in AiTreatmentPlanService::preview() is).
     */
    public function transcribe(TranscribeAiTreatmentPlanAudioRequest $request, Client $client)
    {
        $this->assertCanUseAiAssistant($request->user());

        $description = $this->openAi->transcribe($request->file('audio'));

        return $this->success(['description' => $description], 'Recording transcribed successfully.');
    }

    public function confirm(ConfirmAiTreatmentPlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);

        $treatingDoctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null);

        $appointments = $this->plans->confirm($client, $treatingDoctor, $request->validated('sessions'), $actingUser->id);

        return $this->success(AppointmentResource::collection($appointments), 'Treatment plan confirmed and appointments created.', 201);
    }

    public function addCharge(AddAiTreatmentPlanChargeRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);

        $client->treatmentCharges()->create([
            'source_type' => TreatmentCharge::SOURCE_MANUAL,
            'amount' => $request->validated('amount'),
            'description' => $request->validated('description'),
            'created_by' => $actingUser->id,
        ]);

        return $this->success(
            app(ClientFinancialSummaryService::class)->summary($client),
            'Charge recorded successfully.',
            201,
        );
    }

    protected function assertCanUseAiAssistant(User $user): void
    {
        if (! $user->is_doctor && ! $user->isSystemManager()) {
            throw ValidationException::withMessages([
                'doctor' => ['Only doctors or system managers can use the AI treatment assistant.'],
            ]);
        }
    }

    /**
     * A doctor always treats under their own schedule. A system manager acting on
     * behalf of the clinic must pick which doctor's schedule the plan is booked into.
     */
    protected function resolveTreatingDoctor(User $actingUser, ?int $doctorId): User
    {
        if ($actingUser->is_doctor) {
            return $actingUser;
        }

        $doctor = $doctorId
            ? User::query()
                ->where('id', $doctorId)
                ->where('company_id', $actingUser->company_id)
                ->where('is_doctor', true)
                ->first()
            : null;

        if (! $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Please select a doctor to schedule this treatment plan under.'],
            ]);
        }

        return $doctor;
    }
}
