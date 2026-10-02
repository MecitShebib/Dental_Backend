<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\Specialty;
use App\Models\User;
use App\Services\SystemMessageService;
use Carbon\Carbon;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
            'services.iletimerkezi.sender' => 'Dentavaria',
        ]);

        Http::fake([
            'https://api.iletimerkezi.com/v1/send-sms/json*' => Http::response([
                'response' => [
                    'status' => ['code' => 200, 'message' => 'OK'],
                    'order' => ['id' => '1000007721'],
                ],
            ], 200),
        ]);

        $this->seed(SpecialtySeeder::class);
    }

    protected function makeClient(Company $company, array $overrides = []): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'gender' => 'male',
            'status' => 'new',
            ...$overrides,
        ]);
    }

    /**
     * A doctor with a real specialty_id, and the "System Messages" group
     * seeded for that company+specialty -- the SMS body SystemMessageService
     * resolves at send time only exists once this has run, same as it
     * would in production once a company actually subscribes to a
     * specialty (see Admin\SubscriptionController).
     */
    protected function makeDoctor(Company $company, array $overrides = []): User
    {
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        app(SystemMessageService::class)->seedForCompanySpecialty($company, $dental);

        return User::factory()->create(['company_id' => $company->id, 'specialty_id' => $dental->id, ...$overrides]);
    }

    protected function makeAppointment(Company $company, User $doctor, Client $client, string $startDateTime, string $status = 'scheduled'): Appointment
    {
        $start = Carbon::parse($startDateTime);

        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => $status,
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'duration_minutes' => 30,
        ]);
    }

    public function test_reminder_is_sent_via_sms_for_an_appointment_within_the_next_24_hours(): void
    {
        $company = Company::factory()->create(['name' => 'Dentavaria Clinic']);
        $doctor = $this->makeDoctor($company, ['name' => 'Ali Doctor']);
        $client = $this->makeClient($company, ['preferred_language' => 'ar']);
        $appointment = $this->makeAppointment($company, $doctor, $client, now()->addHours(20)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSent(function ($request) {
            return str_contains((string) $request['request']['order']['message']['text'], 'تذكير');
        });

        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_for_an_appointment_more_than_24_hours_away(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $doctor, $client, now()->addHours(30)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
        $this->assertNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_twice_for_the_same_appointment(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAppointment($company, $doctor, $client, now()->addHours(10)->toDateTimeString());

        Artisan::call('appointments:send-reminders');
        Artisan::call('appointments:send-reminders');

        Http::assertSentCount(1);
    }

    public function test_reminder_is_skipped_for_cancelled_and_completed_and_no_show_appointments(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);

        foreach (['cancelled', 'completed', 'no_show'] as $status) {
            $client = $this->makeClient($company);
            $this->makeAppointment($company, $doctor, $client, now()->addHours(10)->toDateTimeString(), $status);
        }

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
    }

    public function test_sms_is_only_sent_when_the_client_has_a_phone(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);

        $phoneClient = $this->makeClient($company);
        $this->makeAppointment($company, $doctor, $phoneClient, now()->addHours(5)->toDateTimeString());

        $noPhoneClient = $this->makeClient($company, ['phone' => '']);
        $appointmentWithoutPhone = $this->makeAppointment($company, $doctor, $noPhoneClient, now()->addHours(6)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSentCount(1);
        // Nothing to deliver still counts as "handled" -- never retried.
        $this->assertNotNull($appointmentWithoutPhone->fresh()->reminder_sent_at);
    }

    public function test_no_reminder_is_sent_when_the_system_message_was_deleted(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company, ['preferred_language' => 'ar']);
        $appointment = $this->makeAppointment($company, $doctor, $client, now()->addHours(5)->toDateTimeString());

        CustomMessage::query()
            ->where('company_id', $company->id)
            ->where('system_key', 'appointment_reminder')
            ->where('language', 'ar')
            ->delete();

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
        // Nothing meaningful to retry -- still marked handled, not left stuck.
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_appointments_without_a_client_are_skipped(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $start = now()->addHours(10);

        Appointment::create([
            'company_id' => $company->id,
            'client_id' => null,
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'status' => 'scheduled',
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'duration_minutes' => 30,
        ]);

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
    }

    public function test_reminder_message_language_matches_the_clients_preferred_language(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);

        $englishClient = $this->makeClient($company, ['preferred_language' => 'en']);
        $this->makeAppointment($company, $doctor, $englishClient, now()->addHours(2)->toDateTimeString());

        $turkishClient = $this->makeClient($company, ['preferred_language' => 'tr']);
        $this->makeAppointment($company, $doctor, $turkishClient, now()->addHours(3)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], 'Reminder:'));
        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], 'Hatırlatma:'));
    }
}
