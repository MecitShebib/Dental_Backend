<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A single ongoing chat thread per (client, specialty) pair between the
 * treating doctor and the AI, used both for free-form discussion of the case
 * and, via generatePlan(), to trigger a structured treatment plan grounded
 * in that whole conversation. Dental keeps its own odontogram-based plan
 * generator (AiTreatmentPlanService); every other specialty shares the
 * simpler procedure-code-based SpecialtyAiTreatmentPlanService -- this
 * service is the one shared "engine" (history, message persistence, OpenAI
 * call plumbing) both plan generators sit behind, matching this codebase's
 * established "thin per-specialty layer over one shared engine" pattern.
 */
class AiConversationService
{
    /**
     * Capped for cost/context-size reasons -- images are resent on every
     * subsequent turn (the API is stateless, full history goes out each call),
     * so an unbounded attachment count would make the conversation's cost grow
     * unboundedly too. Six recent images is enough context for a case
     * discussion without that runaway cost.
     */
    protected const MAX_IMAGES = 6;

    public function __construct(
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
        protected AiTreatmentPlanService $plans,
        protected SpecialtyAiTreatmentPlanService $specialtyPlans,
    ) {}

    public function history(Client $client, Specialty $specialty): Collection
    {
        $conversation = AiConversation::query()
            ->where('client_id', $client->id)
            ->where('specialty_id', $specialty->id)
            ->first();

        return $conversation ? $conversation->messages : collect();
    }

    /**
     * @return array{0: AiConversationMessage, 1: AiConversationMessage} [userMessage, assistantMessage]
     */
    public function sendMessage(Client $client, User $actingUser, string $text, Specialty $specialty): array
    {
        $conversation = $this->conversationFor($client, $specialty);
        $userMessage = $this->appendUserMessage($conversation, $client, $actingUser, $text);

        $systemPrompt = $specialty->key === Specialty::DENTAL
            ? $this->buildChatSystemPrompt()
            : SpecialtyAiProfiles::chatSystemPrompt($specialty->key);
        $messages = $this->buildOpenAiMessages($client, $conversation->messages()->get(), $systemPrompt);
        $response = $this->openAi->chatCompletionJson($messages, $this->buildChatResponseSchema());

        $this->aiTokenUsage->recordUsage(
            $actingUser->company,
            $actingUser,
            $client,
            'ai_conversation_message',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $response['usage']['prompt_tokens'],
            (int) $response['usage']['completion_tokens'],
        );

        $assistantMessage = $conversation->messages()->create([
            'role' => AiConversationMessage::ROLE_ASSISTANT,
            'content' => $response['content']['reply'],
            'options' => $response['content']['options'] ?: null,
            'ready_for_plan' => (bool) $response['content']['ready_for_plan'],
            'prompt_tokens' => $response['usage']['prompt_tokens'],
            'completion_tokens' => $response['usage']['completion_tokens'],
        ]);

        return [$userMessage, $assistantMessage];
    }

    /**
     * @return array{plan: array, user_message: AiConversationMessage, assistant_message: AiConversationMessage}
     */
    public function generatePlan(Client $client, User $actingUser, mixed $treatingDoctor, ?string $triggerText, Specialty $specialty): array
    {
        $conversation = $this->conversationFor($client, $specialty);
        $text = trim((string) $triggerText) !== ''
            ? $triggerText
            : 'Please build a treatment plan based on our conversation so far.';
        $userMessage = $this->appendUserMessage($conversation, $client, $actingUser, $text);

        $isDental = $specialty->key === Specialty::DENTAL;
        $systemPrompt = $isDental ? $this->plans->buildSystemPrompt() : SpecialtyAiProfiles::planSystemPrompt($specialty->key);
        $messages = $this->buildOpenAiMessages($client, $conversation->messages()->get(), $systemPrompt);
        $plan = $isDental
            ? $this->plans->generatePlanFromMessages($treatingDoctor, $actingUser, $client, $messages)
            : $this->specialtyPlans->generatePlanFromMessages($specialty, $treatingDoctor, $actingUser, $client, $messages);

        $sessionCount = count($plan['sessions']);
        $summary = $sessionCount === 1
            ? "Generated a treatment plan with 1 session: {$plan['diagnosis_summary']}"
            : "Generated a treatment plan with {$sessionCount} sessions: {$plan['diagnosis_summary']}";

        $assistantMessage = $conversation->messages()->create([
            'role' => AiConversationMessage::ROLE_ASSISTANT,
            'content' => $summary,
            'prompt_tokens' => $plan['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $plan['usage']['completion_tokens'] ?? null,
        ]);

        return [
            'plan' => $plan,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    protected function conversationFor(Client $client, Specialty $specialty): AiConversation
    {
        return AiConversation::firstOrCreate(['client_id' => $client->id, 'specialty_id' => $specialty->id]);
    }

    protected function appendUserMessage(AiConversation $conversation, Client $client, User $actingUser, string $text): AiConversationMessage
    {
        $isFirstMessage = $conversation->messages()->count() === 0;

        return $conversation->messages()->create([
            'role' => AiConversationMessage::ROLE_USER,
            'content' => $text,
            'image_urls' => $isFirstMessage ? $this->recentImageUrls($client) : null,
            'created_by' => $actingUser->id,
        ]);
    }

    /**
     * One combined system message: the mode-specific system prompt (chat vs
     * plan-building) followed by a small, always-fresh block of the patient's
     * basic info -- cheap enough to resend every turn, unlike images.
     */
    protected function buildOpenAiMessages(Client $client, Collection $history, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt."\n\n".$this->buildPatientContextText($client)],
        ];

        foreach ($history as $message) {
            $messages[] = ['role' => $message->role, 'content' => $this->toOpenAiContent($message)];
        }

        return $messages;
    }

    protected function toOpenAiContent(AiConversationMessage $message): string|array
    {
        if (empty($message->image_urls)) {
            return $message->content;
        }

        $blocks = [['type' => 'text', 'text' => $message->content]];

        foreach ($message->image_urls as $url) {
            $blocks[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }

        return $blocks;
    }

    protected function buildPatientContextText(Client $client): string
    {
        $lines = ["Patient: {$client->name}"];

        if ($client->age) {
            $lines[] = "Age: {$client->age}";
        }

        if ($client->gender) {
            $lines[] = 'Gender: '.($client->gender->value ?? $client->gender);
        }

        if ($client->city) {
            $lines[] = "City: {$client->city}";
        }

        if ($client->medical_notes) {
            $lines[] = "Medical notes: {$client->medical_notes}";
        }

        return "Patient context:\n".implode("\n", $lines);
    }

    protected function recentImageUrls(Client $client): ?array
    {
        $urls = $client->xrayImages()
            ->latest()
            ->limit(self::MAX_IMAGES)
            ->pluck('image_path')
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values()
            ->all();

        return $urls ?: null;
    }

    /**
     * Every conversational turn is structured (not freeform) so the frontend can
     * render options as tappable answer buttons and detect readiness without
     * parsing prose. Kept intentionally small next to
     * AiTreatmentPlanService::buildJsonSchema() (the much larger plan schema).
     */
    protected function buildChatResponseSchema(): array
    {
        return [
            'name' => 'ai_chat_reply',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'reply' => ['type' => 'string'],
                    'options' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'minItems' => 0,
                        'maxItems' => 4,
                    ],
                    'ready_for_plan' => ['type' => 'boolean'],
                ],
                'required' => ['reply', 'options', 'ready_for_plan'],
                'additionalProperties' => false,
            ],
        ];
    }

    protected function buildChatSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are a knowledgeable dental assistant AI embedded in a clinic's patient
            record system, chatting with the treating doctor about one specific patient.
            You may be shown the patient's basic info, dental/X-ray images on file, and
            the conversation so far. Discuss the case naturally: answer questions, help
            the doctor reason through diagnosis and treatment options, and reference the
            images when relevant. Reply in the same language the doctor is writing in.

            If you are missing a specific piece of clinical information you would need to
            build a good treatment plan (e.g. which tooth, a symptom's duration or
            severity, an examination finding), ask the doctor ONE focused question at a
            time in `reply`. When that question has a small set of likely answers, put
            2-4 short answer choices in `options` (in the doctor's own language) so the
            doctor can tap one after examining the patient instead of typing it out.
            Leave `options` empty for open-ended questions or whenever you are not asking
            a question that has discrete answers.

            Once you have enough information to build a solid plan -- from the doctor's
            diagnosis, this conversation, and/or the images -- set `ready_for_plan` to
            true and end `reply` with a short sentence telling the doctor they can now
            press the "Create Plan" button. Otherwise set `ready_for_plan` to false. Do
            not set it to true just because the doctor said something -- only once the
            case is actually clear enough to plan from.

            Do not produce a structured treatment plan yourself in this mode. If the
            doctor asks you to build/generate a treatment plan, respond as above -- the
            actual structured plan is produced separately once they trigger plan
            generation, grounded in this same conversation.
            PROMPT;
    }
}
