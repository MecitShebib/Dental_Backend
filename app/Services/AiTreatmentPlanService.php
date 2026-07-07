<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Client;
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
                'crown_material' => $enumOrNull(OdontogramV2Vocabulary::crownMaterial()),
                'bridge_unit' => $enumOrNull(OdontogramV2Vocabulary::bridgeUnit()),
                'endo' => $enumOrNull(OdontogramV2Vocabulary::endo()),
                'filling_material' => $enumOrNull(OdontogramV2Vocabulary::fillingMaterial()),
                'filling_surfaces' => [
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
                'indicator_flags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::indicatorFlags()],
                ],
            ],
            'required' => [
                'tooth_number', 'tooth_selection', 'crown_material', 'bridge_unit', 'endo',
                'filling_material', 'filling_surfaces', 'caries', 'mods', 'indicator_flags',
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
            'crown_material' => 'crownMaterial',
            'bridge_unit' => 'bridgeUnit',
            'endo' => 'endo',
            'filling_material' => 'fillingMaterial',
        ];

        foreach ($teeth as $tooth) {
            $toothNo = (string) $tooth['tooth_number'];
            $state = [];

            foreach ($fieldMap as $aiField => $widgetField) {
                if (! empty($tooth[$aiField])) {
                    $state[$widgetField] = $tooth[$aiField];
                }
            }

            if (! empty($tooth['filling_surfaces'])) {
                $state['fillingSurfaces'] = array_values($tooth['filling_surfaces']);
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

    public function preview(mixed $doctor, Client $client, string $description): array
    {
        $response = $this->openAi->chatCompletionJson(
            $this->buildSystemPrompt(),
            $description,
            $this->buildJsonSchema()
        );

        $this->aiTokenUsage->recordUsage(
            $doctor->company,
            $doctor,
            $client,
            'ai_treatment_plan_preview',
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

                if (! empty($session['image'])) {
                    $path = $session['image']->storeAs('odontogram-plans', $appointment->uuid.'.png', 'public');
                    $appointment->update(['planned_image_path' => $path]);
                }

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

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are a dental treatment planning assistant used inside a clinic's patient
            record system. A doctor will describe a patient's dental condition in free
            text, possibly naming one or more tooth numbers (FDI notation, 11-85) and
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
