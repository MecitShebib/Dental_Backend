<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Specialty;
use App\Models\User;

/**
 * Keeps client_specialty_records ("this client is a patient of this
 * specialty") in sync as a side effect of the ordinary ways a client and a
 * doctor end up connected -- booking an appointment, a walk-in visit,
 * confirming a care plan, or being added directly. Called from every one of
 * those creation points (AppointmentController, ClientVisitController,
 * CarePlanService, AiTreatmentPlanService, PublicBookingService,
 * ClientController) rather than relying on a single choke point, since none
 * of those flows share a common ancestor.
 */
class ClientSpecialtyEnrollmentService
{
    /**
     * A doctor interacted with this client -- enroll the client under the
     * doctor's specialty if not already, and claim primary_doctor_id if
     * nobody has claimed it yet. Never reassigns an already-claimed patient
     * to a different doctor, even if a second doctor of the same specialty
     * later sees them.
     */
    public function ensureEnrolled(Client $client, ?User $doctor): ?ClientSpecialtyRecord
    {
        if (! $doctor || ! $doctor->specialty_id) {
            return null;
        }

        $record = ClientSpecialtyRecord::query()->firstOrNew([
            'client_id' => $client->id,
            'specialty_id' => $doctor->specialty_id,
        ]);

        if (! $record->exists) {
            $record->company_id = $client->company_id;
            $record->primary_doctor_id = $doctor->id;
            $record->created_by = $doctor->id;
            $record->save();
        } elseif ($record->primary_doctor_id === null) {
            $record->primary_doctor_id = $doctor->id;
            $record->save();
        }

        return $record;
    }

    /**
     * A non-doctor (system manager/accountant) added this client while
     * working inside a specialty's app -- enroll the client under that
     * specialty with no doctor claimed yet (the first doctor to actually
     * treat them claims it via ensureEnrolled() above).
     */
    public function ensureEnrolledForSpecialty(Client $client, Specialty $specialty, ?User $creator = null): ClientSpecialtyRecord
    {
        $record = ClientSpecialtyRecord::query()->firstOrNew([
            'client_id' => $client->id,
            'specialty_id' => $specialty->id,
        ]);

        if (! $record->exists) {
            $record->company_id = $client->company_id;
            $record->created_by = $creator?->id;
            $record->save();
        }

        return $record;
    }
}
