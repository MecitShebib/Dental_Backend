<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared gate for doctor-owned clinical records (appointments, visits, lab
 * cases, doctor schedules, and the clients themselves): a doctor-acting
 * user may only create, view, or modify records tied to their own
 * doctor_id -- never another doctor's, even by id-guessing or crafting a
 * request the UI would never send. Mirrors the read-list scoping already
 * enforced by ClientQueryService/AppointmentQueryService/DashboardStatsService
 * -- this closes the same gap on the single-record and write-side endpoints.
 */
trait AuthorizesOwnDoctorRecords
{
    protected function assertActingDoctorOwnsDoctorId(Request $request, ?int $doctorId): void
    {
        $actingUser = $request->user();
        if ($actingUser->is_doctor && (int) $doctorId !== $actingUser->id) {
            throw ValidationException::withMessages([
                'doctor_id' => ["You are not authorized to access another doctor's records."],
            ]);
        }
    }

    /**
     * $client is nullable because several callers reach it through a record
     * (a payment, an invoice, a body metric) whose client may have been
     * soft-deleted -- a doctor can't own a patient that isn't there, so that
     * resolves to "denied" rather than a TypeError.
     */
    protected function assertActingDoctorOwnsClient(Request $request, ?Client $client): void
    {
        $actingUser = $request->user();

        // specialty_id is required_if:is_doctor,true on user creation and is
        // backfilled for every pre-Doctovaria doctor (see
        // 2026_08_17_000200_add_specialty_id_to_users_table.php), so a real
        // doctor account always has one. A doctor with no specialty_id is a
        // malformed/legacy fixture the specialty-scoping system was never
        // wired up for -- nothing to check against, so let it through rather
        // than lock every client in the company.
        if (! $actingUser->is_doctor || ! $actingUser->specialty_id) {
            return;
        }

        $owns = $client && $client->specialtyRecords()
            ->where('specialty_id', $actingUser->specialty_id)
            ->where('primary_doctor_id', $actingUser->id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'client' => ["You are not authorized to access this patient's record."],
            ]);
        }
    }

    /**
     * The write-side counterpart of assertActingDoctorOwnsClient() for the two
     * endpoints that are themselves how a patient gets claimed in the first
     * place -- creating an appointment or a walk-in visit (both end in
     * ClientSpecialtyEnrollmentService::ensureEnrolled(), which sets
     * primary_doctor_id when nobody holds it yet).
     *
     * Requiring full ownership there would make claiming impossible: a doctor
     * could never book the company's brand-new, still-unclaimed walk-in. So
     * this only rejects the case the audit actually found -- a doctor writing
     * onto a patient another doctor of the same specialty has ALREADY claimed
     * -- and stays a no-op for an unclaimed patient (and, like every method
     * here, for any non-doctor acting user).
     */
    protected function assertClientNotClaimedByAnotherDoctor(Request $request, ?Client $client): void
    {
        $actingUser = $request->user();

        if (! $client || ! $actingUser->is_doctor || ! $actingUser->specialty_id) {
            return;
        }

        $claimedByAnother = $client->specialtyRecords()
            ->where('specialty_id', $actingUser->specialty_id)
            ->whereNotNull('primary_doctor_id')
            ->where('primary_doctor_id', '!=', $actingUser->id)
            ->exists();

        if ($claimedByAnother) {
            throw ValidationException::withMessages([
                'client' => ["You are not authorized to access this patient's record."],
            ]);
        }
    }
}
