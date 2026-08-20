<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\TreatmentCharge;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanService
{
    public function __construct(
        protected OpenAiClient $openAi,
        protected DoctorAvailabilityService $availability,
        protected AppointmentConflictService $conflicts,
        protected AiTokenUsageService $aiTokenUsage,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function buildJsonSchema(): array
    {
        $enumOrNull = fn (array $values) => [
            'type' => ['string', 'null'],
            'enum' => [...$values, null],
        ];

        $toothSchema = [
            'type' => 'object',
            'properties' => [
                'tooth_number' => ['type' => 'integer', 'minimum' => 11, 'maximum' => 85],
                'tooth_selection' => $enumOrNull(OdontogramV2Vocabulary::toothSelection()),
                'tooth_substrate' => $enumOrNull(OdontogramV2Vocabulary::toothSubstrate()),
                'restoration_type' => $enumOrNull(OdontogramV2Vocabulary::restorationType()),
                'restoration_material' => $enumOrNull(OdontogramV2Vocabulary::restorationMaterial()),
                'prosthesis' => $enumOrNull(OdontogramV2Vocabulary::prosthesis()),
                'endo' => $enumOrNull(OdontogramV2Vocabulary::endo()),
                'filling_material' => $enumOrNull(OdontogramV2Vocabulary::fillingMaterial()),
                'filling_surfaces' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::fillingSurfaces()],
                ],
                'filling_defect' => $enumOrNull(OdontogramV2Vocabulary::fillingDefect()),
                'filling_defect_surfaces' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::fillingSurfaces()],
                ],
                'caries' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::caries()],
                ],
                'mods' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::mods()],
                ],
                'wear_edge' => $enumOrNull(OdontogramV2Vocabulary::wearEdge()),
                'wear_cervical' => $enumOrNull(OdontogramV2Vocabulary::wearCervical()),
                'discoloration' => $enumOrNull(OdontogramV2Vocabulary::discoloration()),
                'ortho_appliance' => $enumOrNull(OdontogramV2Vocabulary::orthoAppliance()),
                'mobility' => $enumOrNull(OdontogramV2Vocabulary::mobility()),
                'peri_implant' => $enumOrNull(OdontogramV2Vocabulary::periImplant()),
                'pulp_dx' => $enumOrNull(OdontogramV2Vocabulary::pulpDx()),
                'resorption_type' => $enumOrNull(OdontogramV2Vocabulary::resorptionType()),
                'root_caries' => $enumOrNull(OdontogramV2Vocabulary::rootCaries()),
                'indicator_flags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::indicatorFlags()],
                ],
            ],
            'required' => [
                'tooth_number', 'tooth_selection', 'tooth_substrate', 'restoration_type', 'restoration_material',
                'prosthesis', 'endo', 'filling_material', 'filling_surfaces', 'filling_defect', 'filling_defect_surfaces',
                'caries', 'mods', 'wear_edge', 'wear_cervical', 'discoloration', 'ortho_appliance', 'mobility',
                'peri_implant', 'pulp_dx', 'resorption_type', 'root_caries', 'indicator_flags',
            ],
            'additionalProperties' => false,
        ];

        $sessionSchema = [
            'type' => 'object',
            'properties' => [
                'day_offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 60],
                'duration_minutes' => ['type' => 'integer', 'enum' => [30, 60, 90]],
                'session_description' => ['type' => 'string'],
                'teeth' => [
                    'type' => 'array',
                    'items' => $toothSchema,
                    'minItems' => 0,
                    'maxItems' => 8,
                ],
            ],
            'required' => ['day_offset', 'duration_minutes', 'session_description', 'teeth'],
            'additionalProperties' => false,
        ];

        return [
            'name' => 'dental_treatment_plan',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'diagnosis_summary' => ['type' => 'string'],
                    'sessions' => [
                        'type' => 'array',
                        'items' => $sessionSchema,
                        'minItems' => 1,
                        'maxItems' => 8,
                    ],
                ],
                'required' => ['diagnosis_summary', 'sessions'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function resolveSessionSlot(mixed $doctor, Carbon $fromDate, int $durationMinutes, int $searchDays = 14): array
    {
        $cursor = $fromDate->copy();

        for ($attempt = 0; $attempt < $searchDays; $attempt++) {
            try {
                $times = $this->availability->availableStartTimes($doctor, $cursor->toDateString(), $durationMinutes);

                if (! empty($times['start_times'])) {
                    return [
                        'date' => $cursor->toDateString(),
                        'start_time' => $times['start_times'][0],
                    ];
                }
            } catch (ValidationException) {
                // Doctor has no schedule for this weekday — try the next day.
            }

            $cursor->addDay();
        }

        throw ValidationException::withMessages([
            'sessions' => ["No available slot found for the doctor within {$searchDays} days starting from {$fromDate->toDateString()}."],
        ]);
    }

    public function buildOdontogramStatus(array $teeth): array
    {
        $status = [
            'version' => '1.3',
            'globals' => [
                'wisdomVisible' => true,
                'showBase' => true,
                'occlusalVisible' => true,
                'showHealthyPulp' => true,
                'edentulous' => false,
            ],
            'teeth' => [],
        ];

        $fieldMap = [
            'tooth_selection' => 'toothSelection',
            'tooth_substrate' => 'toothSubstrate',
            'prosthesis' => 'prosthesis',
            'endo' => 'endo',
            'filling_material' => 'fillingMaterial',
            'wear_edge' => 'wearEdge',
            'wear_cervical' => 'wearCervical',
            'discoloration' => 'discoloration',
            'ortho_appliance' => 'orthoAppliance',
            'mobility' => 'mobility',
            'peri_implant' => 'periImplant',
            'pulp_dx' => 'pulpDx',
            'resorption_type' => 'resorptionType',
            'root_caries' => 'rootCaries',
        ];

        foreach ($teeth as $tooth) {
            $toothNo = (string) $tooth['tooth_number'];
            $state = [];

            foreach ($fieldMap as $aiField => $widgetField) {
                if (! empty($tooth[$aiField])) {
                    $state[$widgetField] = $tooth[$aiField];
                }
            }

            // restorationType/restorationMaterial are a pair (mirrors the
            // widget's combined restoration dropdown) -- only set together.
            if (! empty($tooth['restoration_type']) && ! empty($tooth['restoration_material'])) {
                $state['restorationType'] = $tooth['restoration_type'];
                $state['restorationMaterial'] = $tooth['restoration_material'];
            }

            if (! empty($tooth['filling_surfaces'])) {
                $state['fillingSurfaces'] = array_values($tooth['filling_surfaces']);
            }

            // fillingDefect is a per-surface map on the widget; the AI picks one
            // defect type plus the surfaces it applies to, folded into a map here.
            if (! empty($tooth['filling_defect']) && ! empty($tooth['filling_defect_surfaces'])) {
                $state['fillingDefect'] = array_fill_keys(array_values($tooth['filling_defect_surfaces']), $tooth['filling_defect']);
            }

            if (! empty($tooth['caries'])) {
                $state['caries'] = array_values($tooth['caries']);
            }

            if (! empty($tooth['mods'])) {
                $state['mods'] = array_values($tooth['mods']);
            }

            foreach ($tooth['indicator_flags'] ?? [] as $flag) {
                $state[$flag] = true;
            }

            $status['teeth'][$toothNo] = $state;
        }

        return $status;
    }

    public function buildPlannedSummary(array $odontogramStatus): string
    {
        return json_encode([
            '__visit_odontogram__' => true,
            'companyVersion' => 2,
            'activeTreatment' => 'consultation',
            'selectedTeeth' => [],
            'odontogramV2Status' => $odontogramStatus,
            'odontogramV2PricingOverrides' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Same as the old single-shot preview(), but takes an already-assembled
     * OpenAI messages array (system prompt + patient context + conversation
     * history, see AiConversationService::buildOpenAiMessages()) instead of a
     * lone description string -- so a plan can be grounded in a whole doctor/AI
     * conversation, patient info, and dental images, not just one message.
     */
    public function generatePlanFromMessages(mixed $doctor, mixed $actingUser, Client $client, array $messages): array
    {
        $response = $this->openAi->chatCompletionJson($messages, $this->buildJsonSchema());

        $this->aiTokenUsage->recordUsage(
            $actingUser->company,
            $actingUser,
            $client,
            'ai_treatment_plan_generate',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $response['usage']['prompt_tokens'],
            (int) $response['usage']['completion_tokens'],
        );

        $result = $response['content'];
        $sessions = [];
        $cursor = Carbon::now()->startOfDay();

        foreach (array_slice($result['sessions'], 0, 8) as $session) {
            $cursor = $cursor->copy()->addDays((int) $session['day_offset']);
            $slot = $this->resolveSessionSlot($doctor, $cursor, (int) $session['duration_minutes']);
            $cursor = Carbon::parse($slot['date']);

            $sessions[] = [
                'date' => $slot['date'],
                'start_time' => $slot['start_time'],
                'duration_minutes' => (int) $session['duration_minutes'],
                'session_description' => $session['session_description'],
                'odontogram_v2_status' => $this->buildOdontogramStatus($session['teeth']),
            ];
        }

        return [
            'diagnosis_summary' => $result['diagnosis_summary'],
            'sessions' => $sessions,
            'usage' => $response['usage'],
        ];
    }

    public function confirm(Client $client, mixed $doctor, array $sessions, int $userId): Collection
    {
        $this->assertNoIntraBatchOverlap($sessions);

        $odontogramStatuses = [];

        foreach ($sessions as $index => $session) {
            $this->conflicts->assertWithinSchedule($doctor, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
            $this->conflicts->assertNoConflict($doctor->id, $session['date'], $session['start_time'], (int) $session['duration_minutes']);

            $odontogramStatus = json_decode((string) $session['odontogram_v2_status'], true);

            if (! is_array($odontogramStatus)) {
                throw ValidationException::withMessages([
                    'sessions' => ['One of the sessions has an invalid odontogram payload.'],
                ]);
            }

            $odontogramStatuses[$index] = $odontogramStatus;
        }

        return DB::transaction(function () use ($client, $doctor, $sessions, $odontogramStatuses, $userId) {
            $this->enrollment->ensureEnrolled($client, $doctor);

            return collect($sessions)->map(function (array $session, int $index) use ($client, $doctor, $odontogramStatuses, $userId) {
                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'doctor_id' => $doctor->id,
                    'type' => AppointmentType::Booked->value,
                    'status' => AppointmentStatus::Scheduled->value,
                    'date' => $session['date'],
                    'start_time' => $session['start_time'],
                    'duration_minutes' => (int) $session['duration_minutes'],
                    'end_time' => $this->conflicts->calculateEndTime($session['start_time'], (int) $session['duration_minutes']),
                    'planned_notes' => $session['session_description'],
                    'planned_summary' => $this->buildPlannedSummary($odontogramStatuses[$index]),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->treatmentCharges->syncItems(
                    $client,
                    TreatmentCharge::SOURCE_AI_PLAN,
                    $appointment->id,
                    $session['charge_items'] ?? [],
                );

                return $appointment->fresh();
            });
        });
    }

    protected function assertNoIntraBatchOverlap(array $sessions): void
    {
        foreach ($sessions as $i => $sessionA) {
            $startA = Carbon::parse($sessionA['date'].' '.$sessionA['start_time']);
            $endA = $startA->copy()->addMinutes((int) $sessionA['duration_minutes']);

            foreach ($sessions as $j => $sessionB) {
                if ($j <= $i) {
                    continue;
                }

                $startB = Carbon::parse($sessionB['date'].' '.$sessionB['start_time']);
                $endB = $startB->copy()->addMinutes((int) $sessionB['duration_minutes']);

                if ($startA->lt($endB) && $endA->gt($startB)) {
                    throw ValidationException::withMessages([
                        'sessions' => ['Two sessions in this plan overlap with each other.'],
                    ]);
                }
            }
        }
    }

    public function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are a dental treatment planning assistant used inside a clinic's patient
            record system. You will receive the patient's basic info, possibly one or
            more dental/X-ray images, and possibly a prior conversation between you and
            the treating doctor about this patient's case -- ending in a message from
            the doctor (their diagnosis, or a request to build the plan, or both). Use
            all of this context together, not just the final message alone. The doctor's
            free text may name one or more tooth numbers (FDI notation, 11-85) and
            symptoms.

            Produce a treatment plan made of one or more future sessions (visits), each
            separated by a number of days from the previous one (day_offset; use 0 for
            the very first session, meaning "as soon as possible"). For each session,
            decide a realistic appointment duration (30, 60, or 90 minutes) and describe
            in session_description, in the same language the doctor used, what the
            doctor will do during that specific session.

            For each session, list the teeth involved and their condition/treatment
            using only the allowed vocabulary provided by the schema. If a tooth's
            condition does not map to any allowed value, leave that field null and
            mention the detail in session_description instead of guessing an
            unsupported value.

            Keep plans realistic: most common dental procedures need between 1 and 4
            sessions. Never propose more than 8 sessions.
            PROMPT;
    }
}
