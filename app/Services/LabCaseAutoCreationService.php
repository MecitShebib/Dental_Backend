<?php

namespace App\Services;

use App\Models\Client;
use App\Models\LabCase;
use App\Models\User;

/**
 * Scans an odontogram-v2 snapshot (the same __visit_odontogram__ JSON shape
 * Visit.summary / Appointment.planned_summary / TreatmentRecord.notes all
 * use) for teeth whose restorationType needs an outside lab -- crown,
 * bridge, or veneer, the only three that always leave the practice for
 * fabrication (unlike e.g. inlay/onlay, which are often chairside) -- and
 * auto-creates a LabCase per newly-appearing work type, grouping every
 * affected tooth from this save into one case per type.
 *
 * "Newly-appearing" matters: this runs on every visit/treatment-record save,
 * not just the first, so it only creates a case for a tooth+work-type
 * combination that doesn't already have one for this client -- otherwise
 * re-saving an unrelated edit would spam duplicate lab orders.
 */
class LabCaseAutoCreationService
{
    private const LAB_REQUIRED_WORK_TYPES = ['crown', 'bridge', 'veneer'];

    public function createFromOdontogramSnapshot(
        Client $client,
        User $doctor,
        ?string $summaryJson,
        ?int $appointmentId,
        int $actingUserId,
    ): void {
        $restorationsByTooth = $this->extractLabRequiredRestorations($summaryJson);

        if (empty($restorationsByTooth)) {
            return;
        }

        // $restorationsByTooth is keyed by tooth number, and PHP silently
        // normalizes numeric-string array keys ('11') back to ints (11) --
        // recast to string here, at the point of use as a *value*, since a
        // cast on the key itself wouldn't survive being read back out.
        $toothNumbersByWorkType = [];
        foreach ($restorationsByTooth as $toothNumber => $workType) {
            $toothNumbersByWorkType[$workType][] = (string) $toothNumber;
        }

        foreach ($toothNumbersByWorkType as $workType => $toothNumbers) {
            $newToothNumbers = $this->excludeAlreadyCoveredTeeth($client, $workType, $toothNumbers);

            if (empty($newToothNumbers)) {
                continue;
            }

            $client->labCases()->create([
                'doctor_id' => $doctor->id,
                'appointment_id' => $appointmentId,
                'work_type' => $workType,
                'teeth' => $newToothNumbers,
                'status' => 'sent',
                'sent_date' => now()->toDateString(),
                'notes' => 'Automatically created from the odontogram (tooth '
                    .(count($newToothNumbers) > 1 ? 'numbers' : 'number').': '
                    .implode(', ', $newToothNumbers).').',
                'created_by' => $actingUserId,
                'updated_by' => $actingUserId,
            ]);
        }
    }

    protected function extractLabRequiredRestorations(?string $summaryJson): array
    {
        if (! $summaryJson) {
            return [];
        }

        $decoded = json_decode($summaryJson, true);
        $teeth = $decoded['odontogramV2Status']['teeth'] ?? null;

        if (! is_array($teeth)) {
            return [];
        }

        $restorations = [];

        foreach ($teeth as $toothNumber => $state) {
            $restorationType = is_array($state) ? ($state['restorationType'] ?? null) : null;

            if (in_array($restorationType, self::LAB_REQUIRED_WORK_TYPES, true)) {
                $restorations[(string) $toothNumber] = $restorationType;
            }
        }

        return $restorations;
    }

    protected function excludeAlreadyCoveredTeeth(Client $client, string $workType, array $toothNumbers): array
    {
        $alreadyCoveredTeeth = LabCase::query()
            ->where('client_id', $client->id)
            ->where('work_type', $workType)
            ->pluck('teeth')
            ->flatMap(fn (?array $teeth) => $teeth ?? [])
            ->unique()
            ->all();

        return array_values(array_diff($toothNumbers, $alreadyCoveredTeeth));
    }
}
