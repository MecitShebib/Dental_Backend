<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeXrayImageJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Models\XrayImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class XrayImageAiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
            // This whole file is exercising the AI analysis pipeline itself,
            // which is now gated behind the 3D Odontogram feature flag (off
            // by default) -- see config/features.php.
            'features.three_d_odontogram' => true,
        ]);
    }

    protected function activeDoctor(?int $maxAiTokens = null): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => $maxAiTokens,
        ]);

        return $doctor;
    }

    protected function makeClient(Company $company): Client
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'X-ray Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        return $client;
    }

    protected function emptyTooth(int $toothNumber, array $overrides = []): array
    {
        return array_merge([
            'tooth_number' => $toothNumber,
            'tooth_selection' => null,
            'tooth_substrate' => null,
            'restoration_type' => null,
            'restoration_material' => null,
            'prosthesis' => null,
            'endo' => null,
            'filling_material' => null,
            'filling_surfaces' => [],
            'filling_defect' => null,
            'filling_defect_surfaces' => [],
            'caries' => [],
            'mods' => [],
            'wear_edge' => null,
            'wear_cervical' => null,
            'discoloration' => null,
            'ortho_appliance' => null,
            'mobility' => null,
            'peri_implant' => null,
            'pulp_dx' => null,
            'resorption_type' => null,
            'root_caries' => null,
            'indicator_flags' => [],
        ], $overrides);
    }

    protected function fakeXrayReply(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'teeth' => [
                        $this->emptyTooth(16, ['caries' => ['caries-occlusal']]),
                        $this->emptyTooth(48, ['indicator_flags' => ['extractionPlan']]),
                    ],
                ])]]],
                'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 80, 'total_tokens' => 580],
            ], 200),
        ]);
    }

    public function test_linking_an_image_to_a_client_analyzes_it_and_saves_the_odontogram_status(): void
    {
        Storage::fake('local');
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeXrayReply();

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('pano.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $client->id])->assertOk();

        $xrayImage = XrayImage::withoutGlobalScopes()->findOrFail($id);
        $this->assertNotNull($xrayImage->ai_analyzed_at);
        $this->assertSame(['caries-occlusal'], $xrayImage->ai_odontogram_status['teeth']['16']['caries']);
        $this->assertTrue($xrayImage->ai_odontogram_status['teeth']['48']['extractionPlan']);

        $this->assertDatabaseHas('ai_usage_logs', [
            'client_id' => $client->id,
            'action' => 'xray_odontogram_analysis',
        ]);
    }

    public function test_uploading_an_image_already_linked_to_a_client_analyzes_it_immediately(): void
    {
        Storage::fake('local');
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeXrayReply();

        $id = $this->post('/api/xray-images', [
            'images' => [UploadedFile::fake()->image('pano.jpg')],
            'client_id' => $client->id,
        ])->assertCreated()->json('data.0.id');

        $xrayImage = XrayImage::withoutGlobalScopes()->findOrFail($id);
        $this->assertNotNull($xrayImage->ai_analyzed_at);
    }

    public function test_editing_an_already_analyzed_image_does_not_trigger_a_second_analysis(): void
    {
        Queue::fake();
        Storage::fake('local');
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $xrayImage = XrayImage::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'image_path' => 'xray-images/already-done.jpg',
            'original_filename' => 'already-done.jpg',
            'uploaded_by' => $doctor->id,
            'ai_odontogram_status' => ['version' => '1.3', 'globals' => [], 'teeth' => []],
            'ai_analyzed_at' => now(),
        ]);

        $this->putJson("/api/xray-images/{$xrayImage->id}", ['notes' => 'follow-up note'])->assertOk();

        Queue::assertNotPushed(AnalyzeXrayImageJob::class);
    }

    public function test_latest_xray_odontogram_endpoint_returns_null_when_nothing_analyzed_yet(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $this->getJson("/api/clients/{$client->id}/xray-odontogram")
            ->assertOk()
            ->assertJsonPath('data.odontogram_v2_status', null)
            ->assertJsonPath('data.analyzed_at', null);
    }

    public function test_latest_xray_odontogram_endpoint_returns_the_most_recently_analyzed_image(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        XrayImage::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'image_path' => 'xray-images/older.jpg',
            'original_filename' => 'older.jpg',
            'uploaded_by' => $doctor->id,
            'ai_odontogram_status' => ['version' => '1.3', 'globals' => [], 'teeth' => ['11' => ['caries' => ['caries-mesial']]]],
            'ai_analyzed_at' => now()->subDay(),
        ]);
        $newer = XrayImage::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'image_path' => 'xray-images/newer.jpg',
            'original_filename' => 'newer.jpg',
            'uploaded_by' => $doctor->id,
            'ai_odontogram_status' => ['version' => '1.3', 'globals' => [], 'teeth' => ['26' => ['caries' => ['caries-occlusal']]]],
            'ai_analyzed_at' => now(),
        ]);

        $this->getJson("/api/clients/{$client->id}/xray-odontogram")
            ->assertOk()
            ->assertJsonPath('data.xray_image_uuid', $newer->uuid)
            ->assertJsonPath('data.odontogram_v2_status.teeth.26.caries.0', 'caries-occlusal');
    }

    public function test_analysis_is_skipped_when_the_three_d_odontogram_feature_is_disabled(): void
    {
        config(['features.three_d_odontogram' => false]);
        Storage::fake('local');
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeXrayReply();

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('pano.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $client->id])->assertOk();

        Http::assertNothingSent();
        $xrayImage = XrayImage::withoutGlobalScopes()->findOrFail($id);
        $this->assertNull($xrayImage->ai_analyzed_at);
    }

    public function test_analysis_is_skipped_silently_without_a_signed_kvkk_consent(): void
    {
        Storage::fake('local');
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-NOCONSENT',
            'name' => 'No Consent Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
        $this->fakeXrayReply();

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('pano.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $client->id])->assertOk();

        Http::assertNothingSent();
        $xrayImage = XrayImage::withoutGlobalScopes()->findOrFail($id);
        $this->assertNull($xrayImage->ai_analyzed_at);
    }

    public function test_analysis_is_skipped_silently_when_the_ai_token_cap_is_reached(): void
    {
        Storage::fake('local');
        $doctor = $this->activeDoctor(maxAiTokens: 0);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeXrayReply();

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('pano.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $client->id])->assertOk();

        Http::assertNothingSent();
        $xrayImage = XrayImage::withoutGlobalScopes()->findOrFail($id);
        $this->assertNull($xrayImage->ai_analyzed_at);
    }
}
