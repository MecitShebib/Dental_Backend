<?php

namespace App\Specialties\Cosmetic;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;
use Illuminate\Validation\ValidationException;

/**
 * Estevaria's one real clinical workflow so far: "session-package tracking"
 * -- a one-off consultation followed by a chosen number of same-type
 * treatment sessions, evenly spaced. Unlike Gynevaria/Medivaria/Orthovaria's
 * fixed milestone lists, the session count and spacing here are picked by
 * the doctor per package, so the milestone list is built dynamically before
 * handing off to the shared MilestoneCarePlanService. Not a substitute for a
 * real per-treatment protocol (e.g. laser sessions are typically spaced
 * differently than filler touch-ups); review by a clinical stakeholder
 * before this becomes more than a prototype.
 */
class CosmeticCarePlanService
{
    public const TREATMENT_LABELS = [
        'laser_session' => 'Laser Session',
        'botox_session' => 'Botox Session',
        'filler_session' => 'Filler Session',
    ];

    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    public function confirmPlan(
        Client $client,
        User $doctor,
        string $treatmentCode,
        int $sessionCount,
        int $intervalDays,
        string $startDate,
        string $preferredStartTime,
        int $userId,
    ): CarePlan {
        if (! array_key_exists($treatmentCode, self::TREATMENT_LABELS)) {
            throw ValidationException::withMessages([
                'treatment_code' => ['Unknown treatment type.'],
            ]);
        }

        $specialty = Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();
        $label = self::TREATMENT_LABELS[$treatmentCode];

        $milestones = [[
            'day_offset' => 0,
            'title' => 'Cosmetic Consultation',
            'catalog_code' => 'cosmetic_consultation',
            'clinical_data' => ['package_treatment' => $label, 'session_count' => $sessionCount],
        ]];

        for ($session = 1; $session <= $sessionCount; $session++) {
            $milestones[] = [
                'day_offset' => $intervalDays * $session,
                'title' => "{$label} ({$session}/{$sessionCount})",
                'catalog_code' => $treatmentCode,
                'clinical_data' => ['session_number' => $session, 'session_count' => $sessionCount],
            ];
        }

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            "{$label} Package",
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "{$sessionCount}-session {$label} package, every {$intervalDays} days",
        );
    }
}
