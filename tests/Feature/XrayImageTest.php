<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class XrayImageTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(int $companyId): Client
    {
        static $sequence = 0;
        $sequence++;

        return Client::create([
            'company_id' => $companyId,
            'client_code' => "CLI-XRAY-{$sequence}",
            'name' => "Xray Test Client {$sequence}",
            'phone' => "90555000{$sequence}",
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_user_can_upload_one_or_more_images_unlinked_by_default(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/xray-images', [
            'images' => [
                UploadedFile::fake()->image('pano-1.jpg'),
                UploadedFile::fake()->image('pano-2.jpg'),
            ],
        ])->assertCreated();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.client_id', null);

        $path = str_replace(Storage::disk('public')->url(''), '', $response->json('data.0.image_url'));
        Storage::disk('public')->assertExists($path);

        $this->getJson('/api/xray-images')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_uploaded_images_can_be_filtered_to_unlinked_only(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('linked.jpg')], 'client_id' => $client->id])
            ->assertCreated();
        $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('unlinked.jpg')]])
            ->assertCreated();

        $this->getJson('/api/xray-images?unlinked=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/xray-images?client_id={$client->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_image_can_be_linked_to_a_client_and_later_unlinked(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $client->id])
            ->assertOk()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.client_name', $client->name);

        $this->putJson("/api/xray-images/{$id}", ['client_id' => null])
            ->assertOk()
            ->assertJsonPath('data.client_id', null);
    }

    public function test_an_image_cannot_be_linked_to_another_companys_client(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $otherClient = $this->makeClient(Company::factory()->create()->id);
        Sanctum::actingAs($user);

        $id = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertCreated()->json('data.0.id');

        $this->putJson("/api/xray-images/{$id}", ['client_id' => $otherClient->id])
            ->assertUnprocessable();
    }

    public function test_deleting_an_image_removes_the_stored_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('x.jpg')]])->assertCreated();
        $id = $response->json('data.0.id');
        $path = str_replace(Storage::disk('public')->url(''), '', $response->json('data.0.image_url'));

        $this->deleteJson("/api/xray-images/{$id}")->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->getJson('/api/xray-images')->assertJsonCount(0, 'data');
    }

    public function test_images_are_scoped_to_the_companys_own_data(): void
    {
        Storage::fake('public');
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherUser);
        $otherImageId = $this->post('/api/xray-images', ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertCreated()->json('data.0.id');

        $ownUser = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($ownUser);

        $this->getJson('/api/xray-images')->assertJsonCount(0, 'data');
        $this->putJson("/api/xray-images/{$otherImageId}", ['notes' => 'hijacked'])->assertNotFound();
        $this->deleteJson("/api/xray-images/{$otherImageId}")->assertNotFound();
    }

    public function test_images_field_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/xray-images', [])->assertUnprocessable();
    }
}
