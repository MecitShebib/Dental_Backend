<?php

namespace Tests\Feature;

use App\Mail\PatientRecallMail;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\PatientRecall;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientRecallTest extends TestCase
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

    protected function makeAttendedVisit(Client $client, User $doctor, string $visitDate): Visit
    {
        return Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'visit_date' => $visitDate,
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
    }

    /**
     * Every attended visit also queues a satisfaction-survey invite
     * (VisitObserver, unrelated to recalls) -- an SMS through the same faked
     * api.infobip.com endpoint, and now also an email via SatisfactionSurveyInviteMail
     * since makeClient() gives every test client an email. These helpers/assertions
     * isolate checks to the recall-specific message instead of "nothing/one thing sent at all".
     */
    protected function recallSmsSentCount(): int
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) ($pair[0]['messages'][0]['text'] ?? ''), 'follow-up check-up'))
            ->count();
    }

    public function test_client_overdue_past_the_recall_interval_receives_sms_and_email(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['name' => 'Dentavaria Clinic', 'recall_interval_days' => 30]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company, ['preferred_language' => 'ar']);
        $visit = $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        Artisan::call('patients:send-recalls');

        Http::assertSent(fn ($request) => str_contains((string) $request['messages'][0]['text'], 'مرحبًا'));
        Mail::assertSent(PatientRecallMail::class, fn ($mail) => $mail->hasTo($client->email));

        $recall = PatientRecall::query()->where('visit_id', $visit->id)->first();
        $this->assertNotNull($recall);
        $this->assertNotNull($recall->sent_at);
    }

    public function test_client_not_yet_due_within_the_recall_interval_is_skipped(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(10)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
        Mail::assertNotSent(PatientRecallMail::class);
        $this->assertSame(0, PatientRecall::query()->count());
    }

    public function test_client_with_an_upcoming_scheduled_appointment_is_not_recalled(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
        Mail::assertNotSent(PatientRecallMail::class);
    }

    public function test_recall_is_not_sent_twice_for_the_same_visit(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        Artisan::call('patients:send-recalls');
        Artisan::call('patients:send-recalls');

        $this->assertSame(1, $this->recallSmsSentCount());
        Mail::assertSent(PatientRecallMail::class, 1);
    }

    public function test_recalls_are_disabled_when_company_interval_is_explicitly_zero(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['recall_interval_days' => 0]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(400)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
        Mail::assertNotSent(PatientRecallMail::class);
    }

    public function test_company_without_an_explicit_interval_falls_back_to_the_configured_default(): void
    {
        Mail::fake();

        config(['services.patient_recall.default_interval_days' => 60]);

        $company = Company::factory()->create(['recall_interval_days' => null]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(70)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(1, $this->recallSmsSentCount());
        Mail::assertSent(PatientRecallMail::class, 1);
    }

    public function test_staff_can_manually_send_a_recall_immediately(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['recall_interval_days' => 999]);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDay()->toDateString());

        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/send-recall");

        $response->assertOk();
        $this->assertSame(1, $this->recallSmsSentCount());
        Mail::assertSent(PatientRecallMail::class, 1);
        $this->assertSame(1, PatientRecall::query()->count());
    }
}
