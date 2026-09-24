<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
            // A fixed OTP means booking tests can drive the real
            // request-otp -> book flow without mocking OTP generation.
            'services.otp.fixed_code' => '123456',
        ]);

        Http::fake([
            'https://api.iletimerkezi.com/v1/send-sms/json*' => Http::response([
                'response' => ['status' => ['code' => 200, 'message' => 'OK'], 'order' => ['id' => 'test']],
            ], 200),
        ]);
    }

    /**
     * Drives the two-step OTP flow this booking form now requires: request a
     * code for the phone number, then return the reference to attach to the
     * final /book payload alongside the fixed '123456' code from setUp().
     */
    protected function requestBookingOtp(string $slug, string $phone): string
    {
        return $this->postJson("/api/public/companies/{$slug}/book/request-otp", [
            'client_phone' => $phone,
        ])->assertOk()->json('data.otp_reference');
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_public_can_list_doctors_check_availability_and_book_an_appointment(): void
    {
        $company = Company::factory()->create(['name' => 'Dentavaria Clinic', 'booking_slug' => 'dentavaria-clinic']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();

        $this->getJson('/api/public/companies/dentavaria-clinic/doctors')
            ->assertOk()
            ->assertJsonFragment(['id' => $doctor->id]);

        $availability = $this->getJson("/api/public/companies/dentavaria-clinic/availability?doctor_id={$doctor->id}&date={$date}")
            ->assertOk();
        $this->assertContains('09:00', $availability->json('data.free_times'));

        $otpReference = $this->requestBookingOtp('dentavaria-clinic', '+905551112233');

        $response = $this->postJson('/api/public/companies/dentavaria-clinic/book', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'Walk-in Patient',
            'client_phone' => '+905551112233',
            'client_email' => 'patient@example.com',
            'otp' => '123456',
            'otp_reference' => $otpReference,
        ])->assertCreated();

        $appointment = Appointment::query()->findOrFail($response->json('data.appointment_id'));
        $this->assertTrue($appointment->booked_online);
        $this->assertSame($doctor->id, $appointment->doctor_id);
        $this->assertSame('scheduled', $appointment->status->value);

        $client = Client::query()->where('phone', '+905551112233')->first();
        $this->assertNotNull($client);
        $this->assertSame($client->id, $appointment->client_id);
        $this->assertSame('Walk-in Patient', $client->name);

        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], 'confirmed'));
    }

    public function test_a_same_day_slot_that_has_already_passed_is_not_listed_or_bookable(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 15));

        $company = Company::factory()->create(['booking_slug' => 'today-clinic']);
        // makeBookableDoctor's schedule is 09:00-12:00 in 30-minute slots --
        // with "now" frozen at 11:15, only 11:30 is still in the future.
        $doctor = $this->makeBookableDoctor($company, strtolower(Carbon::today()->englishDayOfWeek));
        $date = Carbon::today()->toDateString();

        $freeTimes = $this->getJson("/api/public/companies/today-clinic/availability?doctor_id={$doctor->id}&date={$date}")
            ->assertOk()
            ->json('data.free_times');

        $this->assertNotContains('09:00', $freeTimes);
        $this->assertNotContains('11:00', $freeTimes);
        $this->assertContains('11:30', $freeTimes);

        $otpReference = $this->requestBookingOtp('today-clinic', '+905550001111');

        $this->postJson('/api/public/companies/today-clinic/book', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'Late Riser',
            'client_phone' => '+905550001111',
            'otp' => '123456',
            'otp_reference' => $otpReference,
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_booking_the_same_slot_twice_is_rejected(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'busy-clinic']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();

        $firstReference = $this->requestBookingOtp('busy-clinic', '+905550000001');
        $payload = [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'First Patient',
            'client_phone' => '+905550000001',
            'otp' => '123456',
            'otp_reference' => $firstReference,
        ];

        $this->postJson('/api/public/companies/busy-clinic/book', $payload)->assertCreated();

        $secondReference = $this->requestBookingOtp('busy-clinic', '+905550000002');

        $this->postJson('/api/public/companies/busy-clinic/book', [
            ...$payload,
            'client_name' => 'Second Patient',
            'client_phone' => '+905550000002',
            'otp_reference' => $secondReference,
        ])->assertStatus(422);

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_the_honeypot_field_silently_rejects_bots(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'bot-target']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();
        $otpReference = $this->requestBookingOtp('bot-target', '+905550000000');

        $this->postJson('/api/public/companies/bot-target/book', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'Bot',
            'client_phone' => '+905550000000',
            'otp' => '123456',
            'otp_reference' => $otpReference,
            'website' => 'http://spam.example',
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_booking_is_unavailable_for_an_inactive_company(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'inactive-clinic', 'status' => 'inactive']);

        $this->getJson('/api/public/companies/inactive-clinic/doctors')->assertNotFound();
    }

    public function test_unknown_booking_slug_returns_not_found(): void
    {
        $this->getJson('/api/public/companies/does-not-exist/doctors')->assertNotFound();
    }

    public function test_an_existing_client_found_by_phone_is_reused_not_duplicated(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'repeat-clinic']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();

        $firstReference = $this->requestBookingOtp('repeat-clinic', '+905559998877');
        $this->postJson('/api/public/companies/repeat-clinic/book', [
            'doctor_id' => $doctor->id, 'date' => $date, 'start_time' => '09:00',
            'client_name' => 'Repeat Patient', 'client_phone' => '+905559998877',
            'otp' => '123456', 'otp_reference' => $firstReference,
        ])->assertCreated();

        $secondReference = $this->requestBookingOtp('repeat-clinic', '+905559998877');
        $this->postJson('/api/public/companies/repeat-clinic/book', [
            'doctor_id' => $doctor->id, 'date' => $date, 'start_time' => '09:30',
            'client_name' => 'Repeat Patient', 'client_phone' => '+905559998877',
            'otp' => '123456', 'otp_reference' => $secondReference,
        ])->assertCreated();

        $this->assertSame(1, Client::query()->where('phone', '+905559998877')->count());
        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_the_booking_page_renders(): void
    {
        $company = Company::factory()->create(['name' => 'Dentavaria Clinic', 'booking_slug' => 'dentavaria-clinic']);

        $this->get('/book/dentavaria-clinic')
            ->assertOk()
            ->assertSee('Dentavaria Clinic');

        $this->get('/ar/book/dentavaria-clinic')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }
}
