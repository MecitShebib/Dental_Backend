<?php

namespace App\Http\Controllers\Api\InternalMedicine;

use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiTreatmentPlan\ConfirmSpecialtyAiTreatmentPlanRequest;
use App\Http\Requests\AiTreatmentPlan\GenerateAiTreatmentPlanRequest;
use App\Http\Requests\AiTreatmentPlan\SendAiConversationMessageRequest;
use App\Http\Requests\AiTreatmentPlan\TranscribeAiTreatmentPlanAudioRequest;
use App\Http\Resources\AiConversationMessageResource;
use App\Http\Resources\AppointmentResource;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AiConversationService;
use App\Services\AiTokenUsageService;
use App\Services\OpenAiClient;
use App\Services\SpecialtyAiTreatmentPlanService;
use Illuminate\Validation\ValidationException;

/**
 * Medivaria's own AI treatment plan assistant. Same shape as dental's
 * Api\AiTreatmentPlanController, but generatePlan()/confirm() delegate to
 * SpecialtyAiTreatmentPlanService (procedure-code based, not odontogram
 * based) and every conversation is scoped to Specialty::INTERNAL_MEDICINE. See
 * app/Services/AiConversationService.php's docblock for the shared-engine
 * rationale, and app/Http/Controllers/Api/InternalMedicine/ClientController.php's
 * docblock for the general per-specialty-controller pattern this follows.
 */
class AiConversationController extends Controller
{
    use ResolvesTreatingDoctor;

    public function __construct(
        protected SpecialtyAiTreatmentPlanService $plans,
        protected AiConversationService $conversations,
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
    ) {}

    public function conversationHistory(Client $client)
    {
        $this->assertCanUseAiAssistant(request()->user());

        return $this->success(AiConversationMessageResource::collection($this->conversations->history($client, $this->specialty())));
    }

    public function sendMessage(SendAiConversationMessageRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);
        $this->aiTokenUsage->assertCanUseAiTokens($actingUser->company);

        [$userMessage, $assistantMessage] = $this->conversations->sendMessage($client, $actingUser, $request->validated('text'), $this->specialty());

        return $this->success([
            'user_message' => AiConversationMessageResource::make($userMessage),
            'assistant_message' => AiConversationMessageResource::make($assistantMessage),
        ], 'Message sent.');
    }

    public function generatePlan(GenerateAiTreatmentPlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);
        $this->aiTokenUsage->assertCanUseAiTokens($actingUser->company);

        $treatingDoctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Please select a doctor to schedule this treatment plan under.');

        $result = $this->conversations->generatePlan($client, $actingUser, $treatingDoctor, $request->validated('text'), $this->specialty());

        return $this->success([
            'diagnosis_summary' => $result['plan']['diagnosis_summary'],
            'sessions' => $result['plan']['sessions'],
            'user_message' => AiConversationMessageResource::make($result['user_message']),
            'assistant_message' => AiConversationMessageResource::make($result['assistant_message']),
        ], 'AI treatment plan generated successfully.');
    }

    public function transcribe(TranscribeAiTreatmentPlanAudioRequest $request, Client $client)
    {
        $this->assertCanUseAiAssistant($request->user());

        $description = $this->openAi->transcribe($request->file('audio'));

        return $this->success(['description' => $description], 'Recording transcribed successfully.');
    }

    public function confirm(ConfirmSpecialtyAiTreatmentPlanRequest $request, Client $client)
    {
        $actingUser = $request->user();
        $this->assertCanUseAiAssistant($actingUser);

        $treatingDoctor = $this->resolveTreatingDoctor($actingUser, $request->integer('doctor_id') ?: null, 'Please select a doctor to schedule this treatment plan under.');

        $appointments = $this->plans->confirm($client, $treatingDoctor, $request->validated('sessions'), $actingUser->id);

        return $this->success(AppointmentResource::collection($appointments), 'Treatment plan confirmed and appointments created.', 201);
    }

    protected function assertCanUseAiAssistant(User $user): void
    {
        if (! $user->is_doctor && ! $user->isSystemManager()) {
            throw ValidationException::withMessages([
                'doctor' => ['Only doctors or system managers can use the AI treatment assistant.'],
            ]);
        }
    }

    protected function specialty(): Specialty
    {
        return Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
    }
}
