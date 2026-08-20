<?php

namespace Tests\Feature\InternalMedicine;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function makeAppointmentFor(Company $company, User $doctor, string $clientName): Appointment
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $clientName,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);

        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    public function test_appointments_and_dashboard_stats_are_scoped_to_internal_medicine_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $internalMedicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $medDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $internalMedicine->id]);
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);

        $this->makeAppointmentFor($company, $medDoctor, 'Med Appt Patient');
        $this->makeAppointmentFor($company, $dentalDoctor, 'Dental Appt Patient');

        Sanctum::actingAs($manager);

        $apptResponse = $this->getJson('/api/internal_medicine/appointments');
        $apptResponse->assertOk();
        $names = collect($apptResponse->json('data'))->pluck('client_name');
        $this->assertTrue($names->contains('Med Appt Patient'));
        $this->assertFalse($names->contains('Dental Appt Patient'));

        $statsResponse = $this->getJson('/api/internal_medicine/dashboard/stats?date_from='.now()->toDateString().'&date_to='.now()->toDateString());
        $statsResponse->assertOk();
        $statsResponse->assertJsonPath('data.appointments.total', 1);
    }
}
