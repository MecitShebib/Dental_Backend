<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Shared by every "plan a series of visits" controller (AI treatment plan,
 * and each specialty's care-plan confirm endpoint): a doctor always treats
 * under their own schedule; a non-doctor (system manager/accountant) must
 * pick which of the company's doctors the plan is booked under.
 */
trait ResolvesTreatingDoctor
{
    protected function resolveTreatingDoctor(User $actingUser, ?int $doctorId, string $message = 'Please select a doctor to schedule this plan under.'): User
    {
        if ($actingUser->is_doctor) {
            return $actingUser;
        }

        $doctor = $doctorId
            ? User::query()
                ->where('id', $doctorId)
                ->where('company_id', $actingUser->company_id)
                ->where('is_doctor', true)
                ->first()
            : null;

        if (! $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => [$message],
            ]);
        }

        return $doctor;
    }
}
