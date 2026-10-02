<?php

namespace App\Specialties\Nutrition;

/**
 * The fixed table shape of Dietavaria's diet and exercise plans (2026-09-28):
 *
 * - diet: plan_date + 3 options each for breakfast / lunch / dinner / snacks,
 *   daily_calories, water_liters, notes;
 * - exercise: plan_date + one {activity, duration_minutes} row per day,
 *   Saturday through Friday (the clinic's week), notes.
 *
 * Stored as JSON on CarePlan (diet_plan_data / exercise_plan_data). The
 * legacy diet_plan / exercise_plan text columns keep a readable rendering
 * (toText) so the AI assistant's plan history and older consumers still read
 * plain text. One place for the shape, validation rules and the AI's JSON
 * schema, so the three can't drift apart.
 */
class NutritionPlanStructure
{
    public const MEALS = ['breakfast', 'lunch', 'dinner', 'snacks'];

    public const OPTIONS_PER_MEAL = 3;

    public const DAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    /** @return array<string, mixed> Laravel rules for a diet table under $prefix (e.g. "data"). */
    public static function dietRules(string $prefix): array
    {
        $rules = [
            "{$prefix}" => ['array'],
            "{$prefix}.plan_date" => ['nullable', 'date'],
            "{$prefix}.daily_calories" => ['nullable', 'integer', 'min:0', 'max:10000'],
            "{$prefix}.water_liters" => ['nullable', 'numeric', 'min:0', 'max:20'],
            "{$prefix}.notes" => ['nullable', 'string', 'max:5000'],
        ];
        foreach (self::MEALS as $meal) {
            $rules["{$prefix}.{$meal}"] = ['nullable', 'array', 'max:'.self::OPTIONS_PER_MEAL];
            $rules["{$prefix}.{$meal}.*"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /** @return array<string, mixed> Laravel rules for an exercise table under $prefix. */
    public static function exerciseRules(string $prefix): array
    {
        $rules = [
            "{$prefix}" => ['array'],
            "{$prefix}.plan_date" => ['nullable', 'date'],
            "{$prefix}.days" => ['nullable', 'array:'.implode(',', self::DAYS)],
            "{$prefix}.notes" => ['nullable', 'string', 'max:5000'],
        ];
        foreach (self::DAYS as $day) {
            $rules["{$prefix}.days.{$day}"] = ['nullable', 'array'];
            $rules["{$prefix}.days.{$day}.activity"] = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.days.{$day}.duration_minutes"] = ['nullable', 'integer', 'min:0', 'max:600'];
        }

        return $rules;
    }

    /**
     * Canonical diet table: every meal padded to exactly 3 options (''), plan
     * date defaulted. A plain string (older AI output / legacy text) is kept
     * as the notes rather than dropped.
     */
    public static function normalizeDiet(array|string|null $input, ?string $defaultDate = null): ?array
    {
        if ($input === null) {
            return null;
        }
        if (is_string($input)) {
            $input = ['notes' => $input];
        }

        $diet = ['plan_date' => self::dateOrDefault($input['plan_date'] ?? null, $defaultDate)];
        foreach (self::MEALS as $meal) {
            $options = array_values(array_map(fn ($value) => trim((string) $value), (array) ($input[$meal] ?? [])));
            $diet[$meal] = array_pad(array_slice($options, 0, self::OPTIONS_PER_MEAL), self::OPTIONS_PER_MEAL, '');
        }
        $diet['daily_calories'] = isset($input['daily_calories']) && $input['daily_calories'] !== '' ? (int) $input['daily_calories'] : null;
        $diet['water_liters'] = isset($input['water_liters']) && $input['water_liters'] !== '' ? (float) $input['water_liters'] : null;
        $diet['notes'] = trim((string) ($input['notes'] ?? ''));

        return $diet;
    }

    /** Canonical exercise table: all 7 days, Saturday first. */
    public static function normalizeExercise(array|string|null $input, ?string $defaultDate = null): ?array
    {
        if ($input === null) {
            return null;
        }
        if (is_string($input)) {
            $input = ['notes' => $input];
        }

        $days = [];
        foreach (self::DAYS as $day) {
            $row = (array) ($input['days'][$day] ?? []);
            $duration = $row['duration_minutes'] ?? null;
            $days[$day] = [
                'activity' => trim((string) ($row['activity'] ?? '')),
                'duration_minutes' => $duration === null || $duration === '' ? null : (int) $duration,
            ];
        }

        return [
            'plan_date' => self::dateOrDefault($input['plan_date'] ?? null, $defaultDate),
            'days' => $days,
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    public static function dietIsEmpty(?array $diet): bool
    {
        if (! $diet) {
            return true;
        }
        foreach (self::MEALS as $meal) {
            if (array_filter($diet[$meal] ?? [], fn ($value) => $value !== '')) {
                return false;
            }
        }

        return $diet['daily_calories'] === null && $diet['water_liters'] === null && ($diet['notes'] ?? '') === '';
    }

    public static function exerciseIsEmpty(?array $exercise): bool
    {
        if (! $exercise) {
            return true;
        }
        foreach ($exercise['days'] ?? [] as $row) {
            if (($row['activity'] ?? '') !== '' || ($row['duration_minutes'] ?? null) !== null) {
                return false;
            }
        }

        return ($exercise['notes'] ?? '') === '';
    }

    /** Readable plain-text rendering kept in the legacy diet_plan column. */
    public static function dietToText(array $diet): string
    {
        $lines = ['Plan date: '.$diet['plan_date']];
        foreach (self::MEALS as $meal) {
            $options = array_values(array_filter($diet[$meal], fn ($value) => $value !== ''));
            if ($options) {
                $numbered = array_map(fn ($value, $index) => ($index + 1).') '.$value, $options, array_keys($options));
                $lines[] = ucfirst($meal).': '.implode('  ', $numbered);
            }
        }
        if ($diet['daily_calories'] !== null) {
            $lines[] = 'Daily calories: '.$diet['daily_calories'].' kcal';
        }
        if ($diet['water_liters'] !== null) {
            $lines[] = 'Water: '.rtrim(rtrim(number_format($diet['water_liters'], 1, '.', ''), '0'), '.').' L/day';
        }
        if ($diet['notes'] !== '') {
            $lines[] = 'Notes: '.$diet['notes'];
        }

        return implode("\n", $lines);
    }

    /** Readable plain-text rendering kept in the legacy exercise_plan column. */
    public static function exerciseToText(array $exercise): string
    {
        $lines = ['Plan date: '.$exercise['plan_date']];
        foreach (self::DAYS as $day) {
            $row = $exercise['days'][$day];
            if ($row['activity'] === '' && $row['duration_minutes'] === null) {
                continue;
            }
            $lines[] = ucfirst($day).': '.($row['activity'] ?: '-').($row['duration_minutes'] !== null ? " ({$row['duration_minutes']} min)" : '');
        }
        if ($exercise['notes'] !== '') {
            $lines[] = 'Notes: '.$exercise['notes'];
        }

        return implode("\n", $lines);
    }

    /** OpenAI strict structured-output schema for the AI's diet table (no date -- the server stamps today). */
    public static function aiDietSchema(): array
    {
        $options = ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => self::OPTIONS_PER_MEAL, 'maxItems' => self::OPTIONS_PER_MEAL];
        $properties = array_fill_keys(self::MEALS, $options) + [
            'daily_calories' => ['type' => ['integer', 'null']],
            'water_liters' => ['type' => ['number', 'null']],
            'notes' => ['type' => 'string'],
        ];

        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    /** OpenAI strict structured-output schema for the AI's exercise table. */
    public static function aiExerciseSchema(): array
    {
        $row = [
            'type' => 'object',
            'properties' => ['activity' => ['type' => 'string'], 'duration_minutes' => ['type' => ['integer', 'null']]],
            'required' => ['activity', 'duration_minutes'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'object', 'properties' => array_fill_keys(self::DAYS, $row), 'required' => self::DAYS, 'additionalProperties' => false],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['days', 'notes'],
            'additionalProperties' => false,
        ];
    }

    protected static function dateOrDefault(mixed $value, ?string $default): string
    {
        return is_string($value) && $value !== '' ? substr($value, 0, 10) : ($default ?? now()->toDateString());
    }
}
