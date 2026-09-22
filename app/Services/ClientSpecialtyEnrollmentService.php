<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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

        $this->assertNotAnotherDoctorsPatient($record, $doctor);

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
     * Defense-in-depth behind AuthorizesOwnDoctorRecords: every write path
     * that lands here (appointment, visit, care plan, prescription, lab
     * result, AI-confirmed plan) is now gated at the controller, so reaching
     * this with someone else's patient means a gate was missed. Silently
     * no-op'ing there was how a doctor could still leave records attached to
     * a colleague's patient.
     *
     * Only a DOCTOR acting on their own behalf is blocked. A non-doctor (a
     * receptionist booking any patient with any doctor), a public online
     * booking, a seeder, or an artisan command legitimately connects a
     * patient to a doctor who isn't the record's owner -- auth('sanctum') is
     * null or non-doctor for all of those, matching the trait's own rule that
     * these checks are a no-op for non-doctor actors.
     */
    protected function assertNotAnotherDoctorsPatient(ClientSpecialtyRecord $record, User $doctor): void
    {
        if (! $record->exists || $record->primary_doctor_id === null) {
            return;
        }

        if ((int) $record->primary_doctor_id === (int) $doctor->id) {
            return;
        }

        $actingUser = auth('sanctum')->user();

        if (! $actingUser || ! $actingUser->is_doctor || (int) $actingUser->id !== (int) $doctor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'client' => ["You are not authorized to access this patient's record."],
        ]);
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
