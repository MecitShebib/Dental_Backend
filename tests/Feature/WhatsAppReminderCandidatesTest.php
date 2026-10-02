<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\SystemMessageService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppReminderCandidatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeClient(Company $company, array $overrides = []): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
            ...$overrides,
        ]);
    }

    /**
     * A doctor with a real specialty_id, and the "System Messages" group
     * seeded for that company+specialty -- the SMS body
     * AppointmentReminderService::smsText() resolves only exists once this
     * has run (see Admin\SubscriptionController in production).
     */
    protected function makeDoctor(Company $company, Specialty $specialty, array $overrides = []): User
    {
        app(SystemMessageService::class)->seedForCompanySpecialty($company, $specialty);

        return User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id, ...$overrides]);
    }

    protected function makeAppointment(Company $company, User $doctor, Client $client, string $date, string $status = 'scheduled'): Appointment
    {
        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => $status,
            'date' => $date,
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    public function test_it_lists_tomorrows_appointments_scoped_to_one_specialty_with_a_wame_ready_phone(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $nutritionDoctor = $this->makeDoctor($company, $nutrition, ['name' => 'Dr. Nutrition']);
        $dentalDoctor = $this->makeDoctor($company, $dental);

        $tomorrow = now()->addDay()->toDateString();
        $nutritionClient = $this->makeClient($company, ['name' => 'Nutrition Patient', 'phone' => '+90 555 111 22 33']);
        $this->makeAppointment($company, $nutritionDoctor, $nutritionClient, $tomorrow);

        $dentalClient = $this->makeClient($company, ['name' => 'Dental Patient']);
        $this->makeAppointment($company, $dentalDoctor, $dentalClient, $tomorrow);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/appointments/whatsapp-reminders?specialty=nutrition')->assertOk();

        $names = collect($response->json('data'))->pluck('client_name');
        $this->assertTrue($names->contains('Nutrition Patient'));
        $this->assertFalse($names->contains('Dental Patient'));

        $item = collect($response->json('data'))->firstWhere('client_name', 'Nutrition Patient');
        // Digits only -- no "+" or spaces -- ready to drop straight into a
        // https://wa.me/<phone> link.
        $this->assertSame('905551112233', $item['phone']);
        $this->assertNotEmpty($item['message']);
    }

    public function test_it_excludes_appointments_that_are_not_tomorrow(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);

        $todayClient = $this->makeClient($company, ['name' => 'Today Patient']);
        $this->makeAppointment($company, $doctor, $todayClient, now()->toDateString());

        $dayAfterClient = $this->makeClient($company, ['name' => 'Day After Patient']);
        $this->makeAppointment($company, $doctor, $dayAfterClient, now()->addDays(2)->toDateString());

        $tomorrowClient = $this->makeClient($company, ['name' => 'Tomorrow Patient']);
        $this->makeAppointment($company, $doctor, $tomorrowClient, now()->addDay()->toDateString());

        Sanctum::actingAs($manager);

        $names = collect($this->getJson('/api/appointments/whatsapp-reminders')->assertOk()->json('data'))->pluck('client_name');

        $this->assertSame(['Tomorrow Patient'], $names->values()->all());
    }

    public function test_it_excludes_cancelled_and_no_show_appointments(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $tomorrow = now()->addDay()->toDateString();

        foreach (['cancelled', 'no_show', 'completed'] as $status) {
            $client = $this->makeClient($company, ['name' => "Client {$status}"]);
            $this->makeAppointment($company, $doctor, $client, $tomorrow, $status);
        }

        $scheduledClient = $this->makeClient($company, ['name' => 'Scheduled Client']);
        $this->makeAppointment($company, $doctor, $scheduledClient, $tomorrow, 'scheduled');

        Sanctum::actingAs($manager);

        $names = collect($this->getJson('/api/appointments/whatsapp-reminders')->assertOk()->json('data'))->pluck('client_name');

        $this->assertSame(['Scheduled Client'], $names->values()->all());
    }

    public function test_the_message_is_rendered_in_the_clients_own_preferred_language(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $doctor = $this->makeDoctor($company, $dental);
        $tomorrow = now()->addDay()->toDateString();

        $arabicClient = $this->makeClient($company, ['name' => 'Arabic Client', 'preferred_language' => 'ar']);
        $this->makeAppointment($company, $doctor, $arabicClient, $tomorrow);

        $englishClient = $this->makeClient($company, ['name' => 'English Client', 'preferred_language' => 'en']);
        $this->makeAppointment($company, $doctor, $englishClient, $tomorrow);

        Sanctum::actingAs($manager);

        $items = collect($this->getJson('/api/appointments/whatsapp-reminders')->assertOk()->json('data'));

        $this->assertStringContainsString('تذكير', $items->firstWhere('client_name', 'Arabic Client')['message']);
        $this->assertStringContainsString('Reminder:', $items->firstWhere('client_name', 'English Client')['message']);
    }

    public function test_results_are_paginated_at_ten_per_page(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $tomorrow = now()->addDay()->toDateString();

        foreach (range(1, 15) as $i) {
            $client = $this->makeClient($company, ['name' => "Patient {$i}"]);
            $this->makeAppointment($company, $doctor, $client, $tomorrow);
        }

        Sanctum::actingAs($manager);

        $firstPage = $this->getJson('/api/appointments/whatsapp-reminders')->assertOk();
        $this->assertCount(10, $firstPage->json('data'));
        $this->assertSame(1, $firstPage->json('meta.current_page'));
        $this->assertSame(2, $firstPage->json('meta.last_page'));
        $this->assertSame(15, $firstPage->json('meta.total'));

        $secondPage = $this->getJson('/api/appointments/whatsapp-reminders?page=2')->assertOk();
        $this->assertCount(5, $secondPage->json('data'));
    }
}
