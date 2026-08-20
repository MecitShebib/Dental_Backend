<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_doctor_can_view_and_update_their_own_schedule(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/doctors/{$doctor->id}/schedule")->assertOk();

        $this->putJson("/api/doctors/{$doctor->id}/schedule", [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_minutes' => 30,
            'working_days' => ['monday', 'tuesday'],
        ])->assertOk();
    }

    public function test_a_doctor_cannot_view_or_update_another_doctors_schedule(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/doctors/{$otherDoctor->id}/schedule")->assertStatus(422);

        $this->putJson("/api/doctors/{$otherDoctor->id}/schedule", [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_minutes' => 30,
            'working_days' => ['monday'],
        ])->assertStatus(422);
    }

    public function test_a_non_doctor_can_view_and_update_any_doctors_schedule_in_their_company(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/doctors/{$doctor->id}/schedule")->assertOk();

        $this->putJson("/api/doctors/{$doctor->id}/schedule", [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_minutes' => 30,
            'working_days' => ['monday'],
        ])->assertOk();
    }
}
