<?php

namespace Tests\Feature\Gynecology;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_index_only_returns_appointments_with_a_gynecology_doctor_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);

        foreach ([$gynDoctor, $dentalDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'female',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->addDay()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/gynecology/appointments');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('client_name');
        $this->assertTrue($names->contains('Patient of '.$gynDoctor->id));
        $this->assertFalse($names->contains('Patient of '.$dentalDoctor->id));
    }
}
