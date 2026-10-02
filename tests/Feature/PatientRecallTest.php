<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\PatientRecall;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Visit;
use App\Services\SystemMessageService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientRecallTest extends TestCase
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
     * resolves at send time only exists once this has run (see
     * Admin\SubscriptionController in production).
     */
    protected function makeDoctor(Company $company, array $overrides = []): User
    {
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        app(SystemMessageService::class)->seedForCompanySpecialty($company, $dental);

        return User::factory()->create(['company_id' => $company->id, 'specialty_id' => $dental->id, ...$overrides]);
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
     * Every attended visit also queues a satisfaction-survey SMS invite
     * (VisitObserver, unrelated to recalls) through the same faked
     * api.iletimerkezi.com endpoint -- this isolates checks to the
     * recall-specific message instead of "nothing/one thing sent at all".
     */
    protected function recallSmsSentCount(): int
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) ($pair[0]['request']['order']['message']['text'] ?? ''), 'follow-up check-up'))
            ->count();
    }

    public function test_client_overdue_past_the_recall_interval_receives_sms(): void
    {
        $company = Company::factory()->create(['name' => 'Dentavaria Clinic', 'recall_interval_days' => 30]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company, ['preferred_language' => 'ar']);
        $visit = $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        Artisan::call('patients:send-recalls');

        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], 'مرحبًا'));

        $recall = PatientRecall::query()->where('visit_id', $visit->id)->first();
        $this->assertNotNull($recall);
        $this->assertNotNull($recall->sent_at);
    }

    public function test_client_not_yet_due_within_the_recall_interval_is_skipped(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(10)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
        $this->assertSame(0, PatientRecall::query()->count());
    }

    public function test_client_with_an_upcoming_scheduled_appointment_is_not_recalled(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = $this->makeDoctor($company);
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
    }

    public function test_recall_is_not_sent_twice_for_the_same_visit(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        Artisan::call('patients:send-recalls');
        Artisan::call('patients:send-recalls');

        $this->assertSame(1, $this->recallSmsSentCount());
    }

    public function test_recalls_are_disabled_when_company_interval_is_explicitly_zero(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 0]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(400)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
    }

    public function test_company_without_an_explicit_interval_falls_back_to_the_configured_default(): void
    {
        config(['services.patient_recall.default_interval_days' => 60]);

        $company = Company::factory()->create(['recall_interval_days' => null]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(70)->toDateString());

        Artisan::call('patients:send-recalls');

        $this->assertSame(1, $this->recallSmsSentCount());
    }

    public function test_no_recall_is_sent_when_the_system_message_was_deleted(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 30]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDays(40)->toDateString());

        CustomMessage::query()
            ->where('company_id', $company->id)
            ->where('system_key', 'patient_recall')
            ->delete();

        Artisan::call('patients:send-recalls');

        $this->assertSame(0, $this->recallSmsSentCount());
    }

    public function test_staff_can_manually_send_a_recall_immediately(): void
    {
        $company = Company::factory()->create(['recall_interval_days' => 999]);
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->makeAttendedVisit($client, $doctor, now()->subDay()->toDateString());

        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/send-recall");

        $response->assertOk();
        $this->assertSame(1, $this->recallSmsSentCount());
        $this->assertSame(1, PatientRecall::query()->count());
    }
}
