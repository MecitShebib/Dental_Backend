<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\PublicBookingOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicBookingOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
            'services.otp.fixed_code' => '123456',
            // SMS-channel behaviour; the email channel is covered by
            // PublicBookingEmailOtpTest (local .env may set email).
            'services.otp.channel' => 'sms',
        ]);

        Http::fake([
            'https://api.iletimerkezi.com/v1/send-sms/json*' => Http::response([
                'response' => ['status' => ['code' => 200, 'message' => 'OK'], 'order' => ['id' => 'test']],
            ], 200),
        ]);
    }

    protected function makeBookableDoctor(Company $company, string $weekday = 'monday'): User
    {
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'status' => 'active']);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => $weekday]);

        return $doctor;
    }

    protected function nextMonday(): Carbon
    {
        return Carbon::now()->next(Carbon::MONDAY);
    }

    protected function bookingPayload(Company $company, User $doctor, string $phone, string $otp, string $otpReference): array
    {
        return [
            'doctor_id' => $doctor->id,
            'date' => $this->nextMonday()->toDateString(),
            'start_time' => '09:00',
            'client_name' => 'OTP Patient',
            'client_phone' => $phone,
            'otp' => $otp,
            'otp_reference' => $otpReference,
        ];
    }

    public function test_request_otp_creates_a_challenge_and_sends_it_via_sms(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'otp-clinic']);

        $response = $this->postJson('/api/public/companies/otp-clinic/book/request-otp', [
            'client_phone' => '+905551234567',
        ])->assertOk();

        $response->assertJsonStructure(['data' => ['otp_reference', 'masked_mobile', 'expires_at']])
            ->assertJsonPath('data.masked_mobile', '********4567');

        $this->assertDatabaseHas('public_booking_otps', [
            'company_id' => $company->id,
            'mobile' => '905551234567',
            'reference' => $response->json('data.otp_reference'),
        ]);

        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], '123456'));
    }

    public function test_request_otp_does_not_send_sms_when_the_provider_is_disabled(): void
    {
        config(['services.iletimerkezi.enabled' => false]);
        Company::factory()->create(['booking_slug' => 'log-only-clinic']);

        $this->postJson('/api/public/companies/log-only-clinic/book/request-otp', [
            'client_phone' => '+905551234567',
        ])->assertOk();

        Http::assertNothingSent();
    }

    public function test_booking_succeeds_with_the_correct_otp(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'confirm-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $reference = $this->postJson('/api/public/companies/confirm-clinic/book/request-otp', [
            'client_phone' => '+905550001111',
        ])->assertOk()->json('data.otp_reference');

        $response = $this->postJson(
            '/api/public/companies/confirm-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550001111', '123456', $reference)
        )->assertCreated();

        $this->assertTrue(Appointment::query()->findOrFail($response->json('data.appointment_id'))->booked_online);
        $this->assertNotNull(PublicBookingOtp::query()->where('reference', $reference)->firstOrFail()->used_at);
    }

    public function test_booking_is_rejected_with_the_wrong_otp(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'wrong-otp-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $reference = $this->postJson('/api/public/companies/wrong-otp-clinic/book/request-otp', [
            'client_phone' => '+905550002222',
        ])->assertOk()->json('data.otp_reference');

        $this->postJson(
            '/api/public/companies/wrong-otp-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550002222', '000000', $reference)
        )->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_booking_is_rejected_when_the_otp_reference_is_unknown(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'no-reference-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $this->postJson(
            '/api/public/companies/no-reference-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550003333', '123456', 'booking_otp_ref_does-not-exist')
        )->assertStatus(422)->assertJsonValidationErrors('otp_reference');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_booking_is_rejected_with_an_expired_otp(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'expired-otp-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $reference = $this->postJson('/api/public/companies/expired-otp-clinic/book/request-otp', [
            'client_phone' => '+905550004444',
        ])->assertOk()->json('data.otp_reference');

        PublicBookingOtp::query()->where('reference', $reference)->update(['expires_at' => now()->subMinute()]);

        $this->postJson(
            '/api/public/companies/expired-otp-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550004444', '123456', $reference)
        )->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_booking_is_rejected_when_the_otp_was_already_used(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'reused-otp-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $reference = $this->postJson('/api/public/companies/reused-otp-clinic/book/request-otp', [
            'client_phone' => '+905550005555',
        ])->assertOk()->json('data.otp_reference');

        $this->postJson(
            '/api/public/companies/reused-otp-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550005555', '123456', $reference)
        )->assertCreated();

        $this->postJson(
            '/api/public/companies/reused-otp-clinic/book',
            [...$this->bookingPayload($company, $doctor, '+905550005555', '123456', $reference), 'start_time' => '09:30']
        )->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_otp_locks_after_five_incorrect_attempts(): void
    {
        // This drives 6 requests to /book for the same phone+IP, which would
        // otherwise trip the public-booking-write rate limiter (5/hour)
        // before the OTP lock itself has a chance to kick in.
        $this->withoutMiddleware(ThrottleRequests::class);

        $company = Company::factory()->create(['booking_slug' => 'locked-otp-clinic']);
        $doctor = $this->makeBookableDoctor($company);

        $reference = $this->postJson('/api/public/companies/locked-otp-clinic/book/request-otp', [
            'client_phone' => '+905550006666',
        ])->assertOk()->json('data.otp_reference');

        for ($i = 0; $i < PublicBookingOtp::MAX_ATTEMPTS; $i++) {
            $this->postJson(
                '/api/public/companies/locked-otp-clinic/book',
                $this->bookingPayload($company, $doctor, '+905550006666', '000000', $reference)
            )->assertStatus(422)->assertJsonValidationErrors('otp');
        }

        $response = $this->postJson(
            '/api/public/companies/locked-otp-clinic/book',
            $this->bookingPayload($company, $doctor, '+905550006666', '123456', $reference)
        )->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->assertStringContainsString('Too many incorrect attempts', $response->json('errors.otp.0'));
        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_new_otp_request_invalidates_the_previous_unused_challenge_for_the_same_phone(): void
    {
        Company::factory()->create(['booking_slug' => 'superseded-otp-clinic']);

        $firstReference = $this->postJson('/api/public/companies/superseded-otp-clinic/book/request-otp', [
            'client_phone' => '+905550007777',
        ])->assertOk()->json('data.otp_reference');

        $this->postJson('/api/public/companies/superseded-otp-clinic/book/request-otp', [
            'client_phone' => '+905550007777',
        ])->assertOk();

        $this->assertNotNull(PublicBookingOtp::query()->where('reference', $firstReference)->firstOrFail()->used_at);
    }
}
