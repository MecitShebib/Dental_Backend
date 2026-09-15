<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorSignatureTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function makeDoctor(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
    }

    public function test_a_doctor_can_upload_and_view_their_own_signature(): void
    {
        Storage::fake('local');
        $doctor = $this->makeDoctor();
        Sanctum::actingAs($doctor);

        $response = $this->post('/api/me/signature', ['image' => UploadedFile::fake()->image('sig.png')]);
        $response->assertOk();
        $signatureUrl = $response->json('data.signature_url');
        $this->assertNotNull($signatureUrl);

        $doctor->refresh();
        Storage::disk('local')->assertExists($doctor->signature_path);

        $path = parse_url($signatureUrl, PHP_URL_PATH);
        $this->get($path.'?'.parse_url($signatureUrl, PHP_URL_QUERY))->assertOk();
    }

    public function test_uploading_a_new_signature_replaces_the_old_file(): void
    {
        Storage::fake('local');
        $doctor = $this->makeDoctor();
        Sanctum::actingAs($doctor);

        $this->post('/api/me/signature', ['image' => UploadedFile::fake()->image('first.png')])->assertOk();
        $doctor->refresh();
        $firstPath = $doctor->signature_path;

        $this->post('/api/me/signature', ['image' => UploadedFile::fake()->image('second.png')])->assertOk();
        $doctor->refresh();

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($doctor->signature_path);
    }

    public function test_a_doctor_can_delete_their_stamp(): void
    {
        Storage::fake('local');
        $doctor = $this->makeDoctor();
        Sanctum::actingAs($doctor);

        $this->post('/api/me/stamp', ['image' => UploadedFile::fake()->image('stamp.png')])->assertOk();
        $doctor->refresh();
        $path = $doctor->stamp_path;
        $this->assertNotNull($path);

        $this->delete('/api/me/stamp')->assertOk();
        $doctor->refresh();

        $this->assertNull($doctor->stamp_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_user_with_no_saved_signature_returns_a_null_url(): void
    {
        $doctor = $this->makeDoctor();
        Sanctum::actingAs($doctor);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.signature_url', null);
    }

    public function test_the_signature_file_route_404s_when_none_is_saved(): void
    {
        $doctor = $this->makeDoctor();

        $url = URL::temporarySignedRoute('users.signature-file', now()->addMinutes(5), ['user' => $doctor->id]);
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->get($path)->assertNotFound();
    }
}
