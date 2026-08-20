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
            'services.infobip.enabled' => true,
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.base_url' => 'https://api.infobip.com',
        ]);

        Http::fake([
            'https://api.infobip.com/sms/2/text/advanced*' => Http::response([
                'messages' => [['messageId' => 'test', 'status' => ['groupId' => 1, 'groupName' => 'PENDING']]],
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

        $response = $this->postJson('/api/public/companies/dentavaria-clinic/book', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'Walk-in Patient',
            'client_phone' => '+905551112233',
            'client_email' => 'patient@example.com',
        ])->assertCreated();

        $appointment = Appointment::query()->findOrFail($response->json('data.appointment_id'));
        $this->assertTrue($appointment->booked_online);
        $this->assertSame($doctor->id, $appointment->doctor_id);
        $this->assertSame('scheduled', $appointment->status->value);

        $client = Client::query()->where('phone', '+905551112233')->first();
        $this->assertNotNull($client);
        $this->assertSame($client->id, $appointment->client_id);
        $this->assertSame('Walk-in Patient', $client->name);

        Http::assertSent(fn ($request) => str_contains((string) $request['messages'][0]['text'], 'confirmed'));
    }

    public function test_booking_the_same_slot_twice_is_rejected(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'busy-clinic']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();

        $payload = [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'First Patient',
            'client_phone' => '+905550000001',
        ];

        $this->postJson('/api/public/companies/busy-clinic/book', $payload)->assertCreated();

        $this->postJson('/api/public/companies/busy-clinic/book', [
            ...$payload,
            'client_name' => 'Second Patient',
            'client_phone' => '+905550000002',
        ])->assertStatus(422);

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_the_honeypot_field_silently_rejects_bots(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'bot-target']);
        $doctor = $this->makeBookableDoctor($company);
        $date = $this->nextMonday()->toDateString();

        $this->postJson('/api/public/companies/bot-target/book', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => '09:00',
            'client_name' => 'Bot',
            'client_phone' => '+905550000000',
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

        $this->postJson('/api/public/companies/repeat-clinic/book', [
            'doctor_id' => $doctor->id, 'date' => $date, 'start_time' => '09:00',
            'client_name' => 'Repeat Patient', 'client_phone' => '+905559998877',
        ])->assertCreated();

        $this->postJson('/api/public/companies/repeat-clinic/book', [
            'doctor_id' => $doctor->id, 'date' => $date, 'start_time' => '09:30',
            'client_name' => 'Repeat Patient', 'client_phone' => '+905559998877',
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
