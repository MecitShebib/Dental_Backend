<?php

namespace Tests\Feature\Gynecology;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
        Storage::fake('local');
    }

    private function specialty(): Specialty
    {
        return Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
    }

    private function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Clinical Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    /** @return array{0: Company, 1: User, 2: Client} */
    private function ownedClient(): array
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        $client = $this->makeClient($company);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $doctor);

        return [$company, $doctor, $client];
    }

    public function test_the_profile_starts_empty_and_can_be_updated(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->getJson("/api/gynecology/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/gynecology/clients/{$client->id}/profile", ['last_menstrual_period' => '2026-09-10', 'gravida' => 1, 'para' => 1, 'abortus' => 1, 'blood_type' => 'A', 'rh' => 'positive', 'cycle_length_days' => 11, 'cycle_regularity' => 'regular', 'contraception_method' => 'none', 'last_pap_smear_date' => '2026-09-10', 'menopause_status' => 'pre', 'previous_delivery_types' => ['normal' => 1], 'notes' => 'Notes for notes'])->assertOk();

        $this->assertDatabaseHas('gynecology_client_profiles', ['client_id' => $client->id, 'gravida' => 1]);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/gynecology/clients/{$client->id}/profile", ['blood_type' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('blood_type');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/gynecology/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/gynecology/clients/{$client->id}/ultrasound-exams")->assertUnprocessable();
    }

    public function test_ultrasound_exams_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/gynecology/clients/{$client->id}/ultrasound-exams", ['exam_date' => '2026-09-10', 'gestational_week' => 1, 'gestational_day' => 1, 'bpd_mm' => 1.5, 'hc_mm' => 1.5, 'ac_mm' => 1.5, 'fl_mm' => 1.5, 'efw_grams' => 1, 'fetal_heart_rate' => 1, 'placenta_location' => 'anterior', 'amniotic_fluid' => 'normal', 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/gynecology/clients/{$client->id}/ultrasound-exams")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/gynecology/ultrasound-exams/{$uuid}", ['exam_date' => '2026-09-11', 'gestational_week' => 2, 'gestational_day' => 2, 'bpd_mm' => 2.5, 'hc_mm' => 2.5, 'ac_mm' => 2.5, 'fl_mm' => 2.5, 'efw_grams' => 2, 'fetal_heart_rate' => 2, 'placenta_location' => 'posterior', 'amniotic_fluid' => 'oligohydramnios', 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.exam_date', '2026-09-11');

        $this->deleteJson("/api/gynecology/ultrasound-exams/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('gynecology_ultrasound_exams', ['uuid' => $uuid]);
    }

    public function test_ultrasound_exams_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/gynecology/clients/{$client->id}/ultrasound-exams", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam_date');
    }

    public function test_ultrasound_exams_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/gynecology/clients/{$client->id}/ultrasound-exams", ['exam_date' => '2026-09-10', 'gestational_week' => 1, 'gestational_day' => 1, 'bpd_mm' => 1.5, 'hc_mm' => 1.5, 'ac_mm' => 1.5, 'fl_mm' => 1.5, 'efw_grams' => 1, 'fetal_heart_rate' => 1, 'placenta_location' => 'anterior', 'amniotic_fluid' => 'normal', 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/gynecology/ultrasound-exams/{$uuid}", ['exam_date' => '2026-09-11', 'gestational_week' => 2, 'gestational_day' => 2, 'bpd_mm' => 2.5, 'hc_mm' => 2.5, 'ac_mm' => 2.5, 'fl_mm' => 2.5, 'efw_grams' => 2, 'fetal_heart_rate' => 2, 'placenta_location' => 'posterior', 'amniotic_fluid' => 'oligohydramnios', 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_ultrasound_exams_image_upload_is_served_through_a_signed_url(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $response = $this->post("/api/gynecology/clients/{$client->id}/ultrasound-exams", [
            ...['exam_date' => '2026-09-10', 'gestational_week' => 1, 'gestational_day' => 1, 'bpd_mm' => 1.5, 'hc_mm' => 1.5, 'ac_mm' => 1.5, 'fl_mm' => 1.5, 'efw_grams' => 1, 'fetal_heart_rate' => 1, 'placenta_location' => 'anterior', 'amniotic_fluid' => 'normal', 'notes' => 'Notes for notes'],
            'image' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $url = $response->json('data.image_url');
        $this->assertNotNull($url);
        $this->get($url)->assertOk();
    }
}
