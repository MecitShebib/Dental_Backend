<?php

namespace Tests\Feature;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.infobip.enabled' => true,
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.base_url' => 'https://api.infobip.com',
            'services.infobip.sender' => 'Dentavaria',
        ]);

        Http::fake([
            'https://api.infobip.com/sms/2/text/advanced*' => Http::response([
                'messages' => [[
                    'messageId' => '1000007721',
                    'status' => ['groupId' => 1, 'groupName' => 'PENDING'],
                ]],
            ], 200),
        ]);
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

    public function test_reminder_is_sent_via_sms_and_email_for_an_appointment_within_the_next_24_hours(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['name' => 'Dentavaria Clinic']);
        $doctor = User::factory()->create(['company_id' => $company->id, 'name' => 'Ali Doctor']);
        $client = $this->makeClient($company, ['preferred_language' => 'ar']);
        $appointment = $this->makeAppointment($company, $doctor, $client, now()->addHours(20)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSent(function ($request) {
            return str_contains((string) $request['messages'][0]['text'], 'تذكير');
        });

        Mail::assertSent(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo($client->email));

        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_for_an_appointment_more_than_24_hours_away(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $doctor, $client, now()->addHours(30)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_twice_for_the_same_appointment(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAppointment($company, $doctor, $client, now()->addHours(10)->toDateTimeString());

        Artisan::call('appointments:send-reminders');
        Artisan::call('appointments:send-reminders');

        Http::assertSentCount(1);
        Mail::assertSent(AppointmentReminderMail::class, 1);
    }

    public function test_reminder_is_skipped_for_cancelled_and_completed_and_no_show_appointments(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);

        foreach (['cancelled', 'completed', 'no_show'] as $status) {
            $client = $this->makeClient($company);
            $this->makeAppointment($company, $doctor, $client, now()->addHours(10)->toDateTimeString(), $status);
        }

        Artisan::call('appointments:send-reminders');

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_sms_is_only_sent_when_phone_present_and_email_only_when_email_present(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);

        $phoneOnlyClient = $this->makeClient($company, ['email' => null]);
        $this->makeAppointment($company, $doctor, $phoneOnlyClient, now()->addHours(5)->toDateTimeString());

        $emailOnlyClient = $this->makeClient($company, ['phone' => '']);
        $this->makeAppointment($company, $doctor, $emailOnlyClient, now()->addHours(6)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSentCount(1);
        Mail::assertSent(AppointmentReminderMail::class, 1);
        Mail::assertSent(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo($emailOnlyClient->email));
    }

    public function test_appointments_without_a_client_are_skipped(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
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
        Mail::assertNothingSent();
    }

    public function test_reminder_message_language_matches_the_clients_preferred_language(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);

        $englishClient = $this->makeClient($company, ['preferred_language' => 'en']);
        $this->makeAppointment($company, $doctor, $englishClient, now()->addHours(2)->toDateTimeString());

        $turkishClient = $this->makeClient($company, ['preferred_language' => 'tr']);
        $this->makeAppointment($company, $doctor, $turkishClient, now()->addHours(3)->toDateTimeString());

        Artisan::call('appointments:send-reminders');

        Http::assertSent(fn ($request) => str_contains((string) $request['messages'][0]['text'], 'Reminder:'));
        Http::assertSent(fn ($request) => str_contains((string) $request['messages'][0]['text'], 'Hatırlatma:'));
    }
}
