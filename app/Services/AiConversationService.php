<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\ClientLanguage;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\NutritionBodyMetric;
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
        $conversation = $this->conversationFor($client, $specialty, $text);
        $userMessage = $this->appendUserMessage($conversation, $client, $actingUser, $text);

        $languageName = $this->languageName($conversation->language);

        $systemPrompt = $specialty->key === Specialty::DENTAL
            ? $this->buildChatSystemPrompt($languageName)
            : SpecialtyAiProfiles::chatSystemPrompt($specialty->key, $languageName);
        $messages = $this->buildOpenAiMessages($client, $conversation->messages()->get(), $systemPrompt, $specialty);
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
        // Only the doctor's own trigger text can seed the language of a
        // brand-new conversation -- the synthesized fallback below is written
        // by us, in English, and says nothing about what language the doctor
        // works in.
        $conversation = $this->conversationFor($client, $specialty, $triggerText);
        $text = trim((string) $triggerText) !== ''
            ? $triggerText
            : 'Please build a treatment plan based on our conversation so far.';
        $userMessage = $this->appendUserMessage($conversation, $client, $actingUser, $text);

        $languageName = $this->languageName($conversation->language);

        $isDental = $specialty->key === Specialty::DENTAL;
        $systemPrompt = $isDental
            ? $this->plans->buildSystemPrompt($languageName)
            : SpecialtyAiProfiles::planSystemPrompt($specialty->key, $languageName);
        $messages = $this->buildOpenAiMessages($client, $conversation->messages()->get(), $systemPrompt, $specialty);
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

    /**
     * The conversation's language is resolved and stored exactly once, when
     * the thread is first created, and is never re-derived afterwards -- an
     * interpreter who starts an appointment in one language keeps speaking it
     * to the end rather than switching halfway through.
     *
     * $openingText is the doctor's very first message (it does not exist yet
     * as a row at this point, which is why it is passed in). Threads created
     * before the `language` column existed are back-filled lazily from their
     * own first message, so an old conversation keeps the language it was
     * actually started in instead of being re-judged by today's message.
     */
    protected function conversationFor(Client $client, Specialty $specialty, ?string $openingText = null): AiConversation
    {
        $conversation = AiConversation::firstOrCreate(
            ['client_id' => $client->id, 'specialty_id' => $specialty->id],
            ['language' => $this->resolveConversationLanguage($client, $openingText)],
        );

        if (! $conversation->language) {
            $firstMessage = $conversation->messages()
                ->where('role', AiConversationMessage::ROLE_USER)
                ->first();

            $conversation->update([
                'language' => $this->resolveConversationLanguage($client, $firstMessage?->content ?? $openingText),
            ]);
        }

        return $conversation;
    }

    /**
     * Which of the app's three languages (the same en/ar/tr vocabulary as
     * clients.preferred_language) this conversation is conducted in.
     *
     * Order of preference:
     *   1. The language the doctor actually opened the thread in, where that
     *      is detectable from the text itself. This chat is doctor-facing
     *      (they read the replies and tap the `options` chips), so their own
     *      writing is the strongest signal there is.
     *   2. The patient record's preferred_language -- the column this codebase
     *      already uses for "what language should communication about this
     *      patient be in" (reminders, surveys, recalls) -- except when the
     *      doctor is demonstrably writing in Latin script and that column says
     *      Arabic. Answering an English/Turkish thread in Arabic because of a
     *      patient-record setting is the exact bug this all exists to fix.
     *   3. The app locale, if it is one of the three (today always the config
     *      default; this is the hook if a request-locale middleware is ever
     *      added, since there is no per-user UI-language column to read).
     *   4. English.
     */
    protected function resolveConversationLanguage(Client $client, ?string $openingText): string
    {
        $text = trim((string) $openingText);

        if ($this->containsArabicScript($text)) {
            return ClientLanguage::Arabic->value;
        }

        if ($this->looksTurkish($text)) {
            return ClientLanguage::Turkish->value;
        }

        // Reaching here with a non-empty message means it held no Arabic
        // characters at all, i.e. the doctor is writing in Latin script.
        $writtenInLatinScript = $text !== '';

        $candidates = [
            $client->preferred_language instanceof ClientLanguage
                ? $client->preferred_language->value
                : $client->preferred_language,
            app()->getLocale(),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || ClientLanguage::tryFrom($candidate) === null) {
                continue;
            }

            if ($writtenInLatinScript && $candidate === ClientLanguage::Arabic->value) {
                continue;
            }

            return $candidate;
        }

        return ClientLanguage::English->value;
    }

    protected function containsArabicScript(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Turkish-specific letters settle it outright. The word list is the
     * fallback for diacritic-free typing (very common on a non-Turkish
     * keyboard layout) and is deliberately limited to words that simply do
     * not occur in English clinical notes -- a false positive here would pin
     * an English thread to Turkish, which is the same class of bug as the one
     * being fixed.
     */
    protected function looksTurkish(string $text): bool
    {
        if (preg_match('/[ğĞışŞİçÇöÖüÜ]/u', $text)) {
            return true;
        }

        $turkishWords = 've|bir|var|yok|icin|hasta|hastada|hastanin|agri|agrisi|curuk|tedavi|muayene|sikayet|degil|gun|sonra|lutfen|yapilacak';

        return (bool) preg_match('/(?:^|[\s.,!?;:])(?:'.$turkishWords.')(?=[\s.,!?;:]|$)/iu', $text);
    }

    protected function languageName(?string $language): string
    {
        return match ($language) {
            ClientLanguage::Arabic->value => 'Arabic',
            ClientLanguage::Turkish->value => 'Turkish',
            default => 'English',
        };
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
     * basic info -- cheap enough to resend every turn, unlike images. Every
     * specialty now gets the same baseline of shared context (scheduling,
     * prescriptions, visit/record history) plus its own specialty-specific
     * piece (nutrition's profile/body-metrics/care-plan/labs, dental's
     * lab cases, the other 4's lab results) -- previously only nutrition got
     * anything beyond the four basic fields below.
     */
    protected function buildOpenAiMessages(Client $client, Collection $history, string $systemPrompt, Specialty $specialty): array
    {
        $contextText = $this->buildPatientContextText($client);
        $contextText .= "\n\n".$this->buildAppointmentsContextText($client, $specialty);

        $prescriptionsText = $this->buildPrescriptionsContextText($client, $specialty);
        if ($prescriptionsText) {
            $contextText .= "\n\n".$prescriptionsText;
        }

        $visitHistoryText = $this->buildVisitHistoryText($client, $specialty);
        if ($visitHistoryText) {
            $contextText .= "\n\n".$visitHistoryText;
        }

        if ($specialty->key === Specialty::NUTRITION) {
            $contextText .= "\n\n".$this->buildNutritionContextText($client);
        } elseif ($specialty->key === Specialty::DENTAL) {
            $labCasesText = $this->buildLabCasesContextText($client);
            if ($labCasesText) {
                $contextText .= "\n\n".$labCasesText;
            }
        } else {
            $labResultsText = $this->buildLabResultsText($client);
            if ($labResultsText) {
                $contextText .= "\n\n".$labResultsText;
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt."\n\n".$contextText],
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

    /**
     * Every specialty's AI now sees the client's own upcoming schedule
     * within that specialty (never surfaced anywhere before this) plus an
     * explicit same-day instruction -- a doctor confirming a plan while the
     * patient is physically in the room routinely wants a session TODAY,
     * but the AI had no way to know whether one already existed, risking a
     * duplicate booking for the same day.
     */
    protected function buildAppointmentsContextText(Client $client, Specialty $specialty): string
    {
        $today = now()->toDateString();

        $appointments = $client->appointments()
            ->whereHas('doctor', fn ($query) => $query->where('specialty_id', $specialty->id))
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        $hasAppointmentToday = $appointments->contains(fn ($appointment) => $appointment->date->toDateString() === $today);

        $lines = ["Today's date: {$today}."];

        if ($appointments->isEmpty()) {
            $lines[] = 'This patient has no upcoming scheduled appointments in this specialty.';
        } else {
            $lines[] = 'Upcoming scheduled appointments in this specialty (soonest first):';
            foreach ($appointments as $appointment) {
                $parts = ["{$appointment->date->toDateString()} {$appointment->start_time}"];
                if ($appointment->planned_notes) {
                    $parts[] = $appointment->planned_notes;
                }
                $lines[] = '- '.implode(': ', $parts);
            }
        }

        $lines[] = $hasAppointmentToday
            ? 'The patient already has an appointment scheduled for today -- when proposing new session dates, do NOT add another one for today, start scheduling from the next available day instead.'
            : 'The patient does not currently have an appointment scheduled for today -- if the doctor is confirming this plan with the patient physically present, it is appropriate to schedule the first session for today.';

        return "Scheduling context:\n".implode("\n", $lines);
    }

    /**
     * Recent prescriptions on file, scoped to this specialty (Prescription
     * has its own specialty_id column, unlike Appointment/Visit which are
     * scoped via the treating doctor's specialty).
     */
    protected function buildPrescriptionsContextText(Client $client, Specialty $specialty): ?string
    {
        $prescriptions = $client->prescriptions()
            ->where('specialty_id', $specialty->id)
            ->with('items')
            ->latest('prescribed_date')
            ->limit(5)
            ->get();

        if ($prescriptions->isEmpty()) {
            return null;
        }

        $lines = ['Recent prescriptions on file (most recent first):'];

        foreach ($prescriptions as $prescription) {
            $medications = $prescription->items
                ->map(fn ($item) => $item->dosage_instruction ? "{$item->medication_name} ({$item->dosage_instruction})" : $item->medication_name)
                ->implode(', ');
            $lines[] = "- {$prescription->prescribed_date->toDateString()}: ".($medications ?: 'no medications listed');
        }

        return implode("\n", $lines);
    }

    /**
     * "Record"/timeline history: recent past visits, most recent first. For
     * the 4 non-dental, non-nutrition specialties, Visit.summary is the
     * same "__visit_specialty_procedures__" JSON snapshot
     * ClientTimelinePanel.jsx writes (see visitSpecialtyProcedures.js on
     * the frontend) -- decoded here into plain procedure codes. Dental's
     * own summary is a much larger odontogram JSON blob (tooth-by-tooth
     * state) this deliberately does not attempt to decode into prose;
     * dental visit history here is notes-only.
     */
    protected function buildVisitHistoryText(Client $client, Specialty $specialty): ?string
    {
        $visits = $client->visits()
            ->whereHas('doctor', fn ($query) => $query->where('specialty_id', $specialty->id))
            ->latest('visit_date')
            ->limit(5)
            ->get();

        if ($visits->isEmpty()) {
            return null;
        }

        $lines = ['Recent visit history (most recent first):'];

        foreach ($visits as $visit) {
            $parts = [$visit->visit_date->toDateString()];

            $procedureCodes = $this->extractSpecialtyProcedureCodes($visit->summary);
            if ($procedureCodes) {
                $parts[] = 'procedures: '.implode(', ', $procedureCodes);
            }

            if ($visit->notes) {
                $parts[] = "notes: {$visit->notes}";
            }

            $lines[] = '- '.implode(', ', $parts);
        }

        return implode("\n", $lines);
    }

    protected function extractSpecialtyProcedureCodes(?string $summary): array
    {
        if (! $summary) {
            return [];
        }

        $decoded = json_decode($summary, true);
        if (! is_array($decoded) || empty($decoded['__visit_specialty_procedures__'])) {
            return [];
        }

        return collect($decoded['procedures'] ?? [])->pluck('procedure_code')->filter()->values()->all();
    }

    /**
     * Dental's own "lab" concept -- outsourced prosthetics work (LabCase),
     * not a test-results concept (that's PatientLabResult, used by the
     * other 5 specialties -- see buildLabResultsText()).
     */
    protected function buildLabCasesContextText(Client $client): ?string
    {
        $labCases = $client->labCases()->latest('sent_date')->limit(5)->get();

        if ($labCases->isEmpty()) {
            return null;
        }

        $lines = ['Dental lab cases on file (most recent first):'];

        foreach ($labCases as $labCase) {
            $parts = [$labCase->work_type?->value ?? $labCase->work_type];
            if (! empty($labCase->teeth)) {
                $parts[] = 'teeth '.implode(', ', $labCase->teeth);
            }
            $parts[] = 'status: '.($labCase->status?->value ?? $labCase->status);
            if ($labCase->expected_return_date) {
                $parts[] = "expected back {$labCase->expected_return_date->toDateString()}";
            }
            $lines[] = '- '.implode(', ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * Nutrition-only: folds the client's static profile (sub-project 2) and
     * recent body-composition measurement history (sub-project 3) into the
     * system prompt automatically, so the doctor never has to type this in
     * by hand -- see docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md
     * section 5. Capped at the 5 most recent measurements for the same
     * cost/context-size reason recentImageUrls() caps attachments.
     */
    protected function buildNutritionContextText(Client $client): string
    {
        $lines = [];

        $profile = $client->nutritionProfile;
        if ($profile) {
            if ($profile->height_cm) {
                $lines[] = "Height: {$profile->height_cm} cm";
            }
            if ($profile->dietary_type) {
                $lines[] = 'Dietary type: '.($profile->dietary_type->value ?? $profile->dietary_type);
            }
            if (! empty($profile->allergies)) {
                $lines[] = 'Allergies: '.implode(', ', $profile->allergies);
            }
            if (! empty($profile->chronic_conditions)) {
                $lines[] = 'Chronic conditions: '.implode(', ', $profile->chronic_conditions);
            }
            if ($profile->medications_affecting_diet) {
                $lines[] = "Medications affecting diet: {$profile->medications_affecting_diet}";
            }
            if ($profile->smoking_status) {
                $lines[] = 'Smoking: '.($profile->smoking_status->value ?? $profile->smoking_status);
            }
            if ($profile->alcohol_status) {
                $lines[] = 'Alcohol: '.($profile->alcohol_status->value ?? $profile->alcohol_status);
            }
            if ($profile->activity_level) {
                $lines[] = 'Activity level: '.($profile->activity_level->value ?? $profile->activity_level);
            }
            if ($profile->goal) {
                $lines[] = 'Goal: '.($profile->goal->value ?? $profile->goal);
            }
            if ($profile->target_weight_kg) {
                $lines[] = "Target weight: {$profile->target_weight_kg} kg";
            }
            if ($profile->notes) {
                $lines[] = "Profile notes: {$profile->notes}";
            }
        }

        $measurements = $client->nutritionBodyMetrics()->limit(5)->get();
        if ($measurements->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent body-composition measurements (most recent first):';
            foreach ($measurements as $measurement) {
                $parts = ["{$measurement->recorded_at->toDateString()}:"];
                if ($measurement->weight_kg) {
                    $parts[] = "weight {$measurement->weight_kg}kg";
                }
                if ($measurement->bmi) {
                    $parts[] = "BMI {$measurement->bmi}";
                }
                if ($measurement->body_fat_percent) {
                    $parts[] = "body fat {$measurement->body_fat_percent}%";
                }
                if ($measurement->muscle_mass_kg) {
                    $parts[] = "muscle mass {$measurement->muscle_mass_kg}kg";
                }
                if ($measurement->waist_cm) {
                    $parts[] = "waist {$measurement->waist_cm}cm";
                }
                $lines[] = '- '.implode(', ', $parts);
            }

            if ($measurements->count() > 1) {
                $latest = $measurements->first();
                $oldest = $measurements->last();
                $daysBetween = $oldest->recorded_at->diffInDays($latest->recorded_at);
                $lines[] = '';
                $lines[] = "{$daysBetween} days between the oldest and newest measurement shown above.";
                if ($latest->weight_kg && $oldest->weight_kg) {
                    $lines[] = $this->formatWeightDeltaLine('Weight change over that period', (float) $oldest->weight_kg, (float) $latest->weight_kg);
                }
            }

            $cycleLines = $this->buildActiveFollowUpCycleLines($client, $measurements->first());
            if ($cycleLines) {
                $lines[] = '';
                $lines = [...$lines, ...$cycleLines];
            }
        }

        $text = $lines
            ? "Nutrition profile and measurement history:\n".implode("\n", $lines)
            : 'Nutrition profile and measurement history: none recorded yet.';

        $priorPlanText = $this->buildPriorCarePlanText($client);
        if ($priorPlanText) {
            $text .= "\n\n".$priorPlanText;
        }

        $labResultsText = $this->buildLabResultsText($client);
        if ($labResultsText) {
            $text .= "\n\n".$labResultsText;
        }

        return $text;
    }

    /**
     * The most recently confirmed diet/exercise plan this client already has
     * on file (see CarePlan.diet_plan/exercise_plan, authored by
     * SpecialtyAiTreatmentPlanService::confirm()) -- so a follow-up plan
     * builds on what the patient was already given instead of starting from
     * nothing, and the AI can judge whether it's still working.
     */
    protected function buildPriorCarePlanText(Client $client): ?string
    {
        $plan = $client->carePlans()
            ->where('status', CarePlan::STATUS_CONFIRMED)
            ->whereHas('specialty', fn ($query) => $query->where('key', Specialty::NUTRITION))
            ->where(fn ($query) => $query->whereNotNull('diet_plan')->orWhereNotNull('exercise_plan'))
            ->latest('id')
            ->first();

        if (! $plan) {
            return null;
        }

        $lines = ["Existing plan on file (\"{$plan->title}\"):"];

        if ($plan->diet_plan) {
            $lines[] = "Diet plan:\n{$plan->diet_plan}";
        }

        if ($plan->exercise_plan) {
            $lines[] = "Exercise plan:\n{$plan->exercise_plan}";
        }

        return implode("\n\n", $lines);
    }

    /**
     * Every lab/analysis result on file for this client (not specialty-
     * filtered -- a nutrition patient's bloodwork ordered by any doctor is
     * still clinically relevant here), most recent first, capped for the
     * same cost/context-size reason the measurement history above is.
     */
    protected function buildLabResultsText(Client $client): ?string
    {
        $results = $client->labResults()->latest('test_date')->limit(10)->get();

        if ($results->isEmpty()) {
            return null;
        }

        $lines = ['Lab/analysis results on file (most recent first):'];

        foreach ($results as $result) {
            $parts = ["{$result->test_date->toDateString()}: {$result->test_name}"];
            if ($result->result_value) {
                $parts[] = $result->result_value.($result->unit ? " {$result->unit}" : '');
            }
            if ($result->reference_range) {
                $parts[] = "reference {$result->reference_range}";
            }
            if ($result->is_abnormal !== null) {
                $parts[] = $result->is_abnormal ? 'flagged abnormal' : 'normal';
            }
            $lines[] = '- '.implode(', ', $parts);
        }

        return implode("\n", $lines);
    }

    protected function formatWeightDeltaLine(string $label, float $from, float $to): string
    {
        $delta = round($to - $from, 1);
        $direction = $delta < 0 ? 'lost' : ($delta > 0 ? 'gained' : 'unchanged');

        return "{$label}: {$direction} ".abs($delta).' kg.';
    }

    /**
     * Periodic follow-up loop (spec section 6): if the client has a
     * confirmed nutrition follow-up program whose baseline measurement
     * (stamped by NutritionCarePlanService::confirmPlan() into the initial
     * consultation session's clinical_data) is older than their latest
     * measurement, surface the comparison so the AI can judge whether
     * enough time has passed to refresh the plan for the next period --
     * rather than the backend forcing a new plan automatically.
     */
    protected function buildActiveFollowUpCycleLines(Client $client, ?NutritionBodyMetric $latestMeasurement): array
    {
        if (! $latestMeasurement) {
            return [];
        }

        $cycle = $client->carePlans()
            ->where('status', CarePlan::STATUS_CONFIRMED)
            ->whereHas('specialty', fn ($query) => $query->where('key', Specialty::NUTRITION))
            ->latest('id')
            ->first();

        if (! $cycle) {
            return [];
        }

        $baselineMetricId = $cycle->sessions()->first()?->clinical_data['baseline_metric_id'] ?? null;
        if (! $baselineMetricId) {
            return [];
        }

        $baseline = NutritionBodyMetric::find($baselineMetricId);
        if (! $baseline || $baseline->id === $latestMeasurement->id) {
            return [];
        }

        $daysSinceBaseline = $baseline->recorded_at->diffInDays($latestMeasurement->recorded_at);

        $lines = [
            "Active follow-up program \"{$cycle->title}\" started with a baseline measurement on {$baseline->recorded_at->toDateString()}.",
            "{$daysSinceBaseline} days have passed since that baseline (compared to the latest measurement above).",
        ];

        if ($baseline->weight_kg && $latestMeasurement->weight_kg) {
            $lines[] = $this->formatWeightDeltaLine('Weight since baseline', (float) $baseline->weight_kg, (float) $latestMeasurement->weight_kg);
        }

        if ($baseline->body_fat_percent && $latestMeasurement->body_fat_percent) {
            $deltaFat = round((float) $latestMeasurement->body_fat_percent - (float) $baseline->body_fat_percent, 1);
            $lines[] = 'Body fat % change since baseline: '.($deltaFat >= 0 ? '+' : '').$deltaFat.'%.';
        }

        $lines[] = 'Consider whether enough time has passed and progress warrants building a new plan for the next period.';

        return $lines;
    }

    protected function recentImageUrls(Client $client): ?array
    {
        // Inline base64 data URIs, not a Storage::url()/signed route -- same
        // reasoning and same pattern as AnalyzeXrayImageJob: X-ray images
        // live on the private disk (KVKK Faz 0), and a plain
        // Storage::disk('public')->url() here built a URL for a file that no
        // longer exists there (OpenAI's vision API 404'd fetching it). A
        // *signed* private-disk URL is itself fetchable (verified directly
        // -- 200, real image bytes) but OpenAI's own fetcher still couldn't
        // reach it in practice; a data URI sidesteps needing OpenAI to fetch
        // anything from this host at all, and as a bonus never expires, so
        // it stays valid even when this same value is resent verbatim on
        // later turns of a long-running conversation (unlike a temporary
        // signed URL, which would start failing again after 60 minutes).
        $urls = $client->xrayImages()
            ->latest()
            ->limit(self::MAX_IMAGES)
            ->get()
            ->map(function ($xrayImage) {
                $binary = Storage::disk('local')->get($xrayImage->image_path);

                if ($binary === null) {
                    return null;
                }

                $mimeType = Storage::disk('local')->mimeType($xrayImage->image_path) ?: 'image/jpeg';

                return 'data:'.$mimeType.';base64,'.base64_encode($binary);
            })
            ->filter()
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

    /**
     * $languageName is the conversation's pinned language ("English"/"Arabic"/
     * "Turkish", see resolveConversationLanguage()), stated explicitly rather
     * than left for the model to re-infer per turn -- soft inference is what
     * made fully English threads flip into Arabic mid-conversation.
     */
    protected function buildChatSystemPrompt(string $languageName): string
    {
        return <<<PROMPT
            You are a knowledgeable dental assistant AI embedded in a clinic's patient
            record system, chatting with the treating doctor about one specific patient.
            You may be shown the patient's basic info, dental/X-ray images on file, and
            the conversation so far. Discuss the case naturally: answer questions, help
            the doctor reason through diagnosis and treatment options, and reference the
            images when relevant.

            This conversation is conducted in {$languageName}. Write every reply, and
            every answer choice in `options`, in {$languageName} for the whole
            conversation -- never switch to another language, even if an individual
            message you receive happens to be written in a different one.

            If you are missing a specific piece of clinical information you would need to
            build a good treatment plan (e.g. which tooth, a symptom's duration or
            severity, an examination finding), ask the doctor ONE focused question at a
            time in `reply`. When that question has a small set of likely answers, put
            2-4 short answer choices in `options` so the doctor can tap one after
            examining the patient instead of typing it out. Leave `options` empty for
            open-ended questions or whenever you are not asking a question that has
            discrete answers.

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
