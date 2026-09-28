<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\ClientLanguage;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Backs the unauthenticated /book/{company} page: lists a company's doctors,
 * their free slots, and creates the appointment + (find-or-create) client
 * from just a name and phone number. No Sanctum user is ever involved, so
 * every method takes an explicit Company instead of relying on the usual
 * BelongsToCompany auth-guard scoping.
 */
class PublicBookingService
{
    public function __construct(
        protected DoctorAvailabilityService $availability,
        protected AppointmentConflictService $conflicts,
        protected MessagingService $messaging,
        protected MessageTemplateService $templates,
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected PublicBookingOtpService $otp,
    ) {}

    public function doctorsFor(Company $company): Collection
    {
        return $this->doctorQuery($company)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<int, string> "H:i" times with a free slot today or later
     */
    public function freeTimes(Company $company, int $doctorId, string $date): array
    {
        $doctor = $this->doctorQuery($company)->findOrFail($doctorId);
        $result = $this->availability->availability($doctor, $date);

        // availability() builds every slot in the doctor's working hours
        // regardless of the current time -- fine for the internal scheduling
        // grid (staff still need to see the whole day), but this public page
        // must not let a same-day slot that's already passed show up as
        // bookable.
        return collect($result['slots'])
            ->where('status', 'free')
            ->filter(fn (array $slot) => Carbon::parse($date.' '.$slot['time'])->isFuture())
            ->pluck('time')
            ->values()
            ->all();
    }

    /**
     * @param  array{doctor_id: int, date: string, start_time: string, client_name: string, client_phone: string, client_email: ?string, otp: string, otp_reference: string}  $data
     */
    public function book(Company $company, array $data): Appointment
    {
        $challenge = $this->otp->findChallenge($company, $data['client_phone'], $data['otp_reference']);

        if (! $challenge) {
            throw ValidationException::withMessages([
                'otp_reference' => ['This verification session was not found. Please request a new code.'],
            ]);
        }

        $this->otp->verify($challenge, $data['otp']);
        $this->otp->markUsed($challenge);

        $doctor = $this->doctorQuery($company)->find($data['doctor_id']);

        if (! $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor is not available for online booking.'],
            ]);
        }

        // Guards the endpoint itself, not just what freeTimes() lists: the
        // frontend already hides a same-day slot once it's passed, but this
        // still needs to reject a direct request for one too (stale page,
        // or a slot that tips into the past during the OTP round-trip).
        if (Carbon::parse($data['date'].' '.$data['start_time'])->isPast()) {
            throw ValidationException::withMessages([
                'start_time' => ['This time has already passed. Please choose another time.'],
            ]);
        }

        $durationMinutes = $doctor->doctorSchedule?->slot_minutes ?? 30;

        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], $durationMinutes);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], $durationMinutes);

        return DB::transaction(function () use ($company, $doctor, $data, $durationMinutes) {
            // Re-check for a conflict inside the transaction: the two checks
            // above already ran, but another booking could have landed
            // between then and now under concurrent requests.
            $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], $durationMinutes);

            $client = Client::query()
                ->where('company_id', $company->id)
                ->where('phone', $data['client_phone'])
                ->first();

            if (! $client) {
                $client = Client::create([
                    'company_id' => $company->id,
                    'client_code' => 'CL-'.strtoupper(Str::random(8)),
                    'name' => $data['client_name'],
                    'phone' => $data['client_phone'],
                    'email' => $data['client_email'] ?? null,
                    'status' => 'new',
                ]);
            }

            $appointment = Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => AppointmentType::Booked->value,
                'booked_online' => true,
                'status' => AppointmentStatus::Scheduled->value,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'duration_minutes' => $durationMinutes,
            ]);

            $this->enrollment->ensureEnrolled($client, $doctor);
            $this->sendConfirmation($company, $client, $doctor, $appointment);

            return $appointment;
        });
    }

    protected function doctorQuery(Company $company)
    {
        return User::query()
            ->where('company_id', $company->id)
            ->where('is_doctor', true)
            ->where('status', 'active');
    }

    protected function sendConfirmation(Company $company, Client $client, User $doctor, Appointment $appointment): void
    {
        $language = $client->preferred_language ?? ClientLanguage::English;

        if ($client->phone) {
            $rendered = $this->templates->render($company, 'booking_confirmation', 'sms', $language, [
                'client_name' => $client->name,
                'doctor_name' => $doctor->name,
                'company_name' => $company->name,
                'date' => $appointment->date->format('d/m/Y'),
                'time' => substr($appointment->start_time, 0, 5),
            ], $doctor->specialty_id);

            $this->messaging->send($company, $client->phone, $rendered['body']);
        }

        Log::info('Online booking confirmed.', [
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
        ]);
    }
}
