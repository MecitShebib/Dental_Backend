<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanService
{
    public function __construct(
        protected OpenAiClient $openAi,
        protected DoctorAvailabilityService $availability,
        protected AppointmentConflictService $conflicts,
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
}
