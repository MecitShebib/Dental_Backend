<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\GynecologyClientProfile;
use App\Models\User;
use App\Services\MessageTemplateVariableBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTemplateVariableBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_legacy_flat_keys_and_the_new_dot_path_keys(): void
    {
        $company = Company::factory()->create(['name' => 'Doctovaria Clinic', 'phone' => '+90111', 'email' => 'clinic@example.com', 'address' => 'Istanbul']);
        $doctor = User::factory()->create(['company_id' => $company->id, 'name' => 'Dr. Ada', 'phone' => '+90222', 'email' => 'ada@example.com']);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-1',
            'name' => 'Jane Patient',
            'phone' => '+90333',
            'email' => 'jane@example.com',
            'gender' => 'female',
            'age' => 29,
            'city' => 'Ankara',
            'address' => 'Some street',
            'status' => 'new',
        ]);

        $variables = app(MessageTemplateVariableBuilder::class)->build($client, $doctor, $company, null, ['date' => '2026-10-01']);

        $this->assertSame('Jane Patient', $variables['client_name']);
        $this->assertSame('Dr. Ada', $variables['doctor_name']);
        $this->assertSame('Doctovaria Clinic', $variables['company_name']);
        $this->assertSame('2026-10-01', $variables['date']);

        $this->assertSame('Jane Patient', $variables['patient.name']);
        $this->assertSame('+90333', $variables['patient.phone']);
        $this->assertSame('29', $variables['patient.age']);
        $this->assertSame('female', $variables['patient.gender']);
        $this->assertSame('Ankara', $variables['patient.city']);

        $this->assertSame('Dr. Ada', $variables['doctor.name']);
        $this->assertSame('+90222', $variables['doctor.phone']);

        $this->assertSame('Doctovaria Clinic', $variables['clinic.name']);
        $this->assertSame('Istanbul', $variables['clinic.address']);
    }

    public function test_it_flattens_the_clients_own_specialty_profile_into_patient_dot_fields(): void
    {
        $company = Company::factory()->create();
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-2',
            'name' => 'Gyn Patient',
            'phone' => '+90444',
            'gender' => 'female',
            'status' => 'new',
        ]);
        GynecologyClientProfile::create([
            'client_id' => $client->id,
            'blood_type' => 'A',
            'rh' => 'positive',
            'gravida' => 2,
        ]);

        $variables = app(MessageTemplateVariableBuilder::class)->build($client, null, $company, 'gynecology');

        $this->assertSame('A', $variables['patient.blood_type']);
        $this->assertSame('positive', $variables['patient.rh']);
        $this->assertSame('2', $variables['patient.gravida']);
        $this->assertArrayNotHasKey('patient.client_id', $variables);
        $this->assertArrayNotHasKey('patient.id', $variables);
    }

    public function test_an_unknown_or_missing_specialty_profile_does_not_add_patient_dot_fields_beyond_the_base_set(): void
    {
        $company = Company::factory()->create();
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-3',
            'name' => 'Dental Patient',
            'phone' => '+90555',
            'gender' => 'male',
            'status' => 'new',
        ]);

        $variables = app(MessageTemplateVariableBuilder::class)->build($client, null, $company, 'dental');

        $this->assertArrayNotHasKey('patient.blood_type', $variables);
    }
}
