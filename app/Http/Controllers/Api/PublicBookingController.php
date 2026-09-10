<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicBooking\BookPublicAppointmentRequest;
use App\Http\Requests\PublicBooking\RequestBookingOtpRequest;
use App\Models\Company;
use App\Services\PublicBookingOtpService;
use App\Services\PublicBookingService;
use Illuminate\Http\Request;

/**
 * Powers the unauthenticated /book/{company} page (see routes/web.php and
 * resources/views/public-booking.blade.php). Every action is scoped to the
 * :company route-model-bound-by-booking_slug param, and never touches
 * anything outside that one company.
 */
class PublicBookingController extends Controller
{
    public function doctors(Company $company, PublicBookingService $booking)
    {
        $this->assertBookable($company);

        return $this->success($booking->doctorsFor($company));
    }

    public function availability(Request $request, Company $company, PublicBookingService $booking)
    {
        $this->assertBookable($company);

        $data = $request->validate([
            'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        return $this->success([
            'doctor_id' => (int) $data['doctor_id'],
            'date' => $data['date'],
            'free_times' => $booking->freeTimes($company, (int) $data['doctor_id'], $data['date']),
        ]);
    }

    public function requestOtp(RequestBookingOtpRequest $request, Company $company, PublicBookingOtpService $otp)
    {
        $this->assertBookable($company);

        $phone = $request->validated()['client_phone'];
        $challenge = $otp->issue($company, $phone);

        return $this->success([
            'otp_reference' => $challenge->reference,
            'masked_mobile' => $otp->maskMobile($phone),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
        ], 'Verification code sent.');
    }

    public function book(BookPublicAppointmentRequest $request, Company $company, PublicBookingService $booking)
    {
        $this->assertBookable($company);

        $appointment = $booking->book($company, $request->validated());

        return $this->success([
            'appointment_id' => $appointment->id,
            'date' => $appointment->date->toDateString(),
            'start_time' => substr($appointment->start_time, 0, 5),
        ], 'Appointment booked successfully.', 201);
    }

    /**
     * A suspended clinic or one whose subscription lapsed shouldn't keep
     * accepting public bookings just because its slug is still live.
     */
    protected function assertBookable(Company $company): void
    {
        abort_unless($company->status === 'active' && $company->currentSubscription()->exists(), 404);
    }
}
