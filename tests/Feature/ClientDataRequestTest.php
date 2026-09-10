<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Models\XrayImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDataRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-DSR-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Data Subject Patient',
            'email' => 'patient@example.com',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_user_can_export_a_clients_data(): void
    {
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Payment::create([
            'client_id' => $client->id,
            'payment_date' => '2026-08-01',
            'amount' => 500,
            'payment_method' => 'cash',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clients/{$client->id}/data-export")->assertOk();

        $response->assertJsonPath('client.id', $client->id);
        $response->assertJsonCount(1, 'payments');
    }

    public function test_a_user_cannot_export_another_companys_client_data(): void
    {
        $otherClient = $this->makeClient(Company::factory()->create()->id);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$otherClient->id}/data-export")->assertNotFound();
    }

    public function test_erasure_masks_identifying_fields_but_keeps_financial_records(): void
    {
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        $payment = Payment::create([
            'client_id' => $client->id,
            'payment_date' => '2026-08-01',
            'amount' => 750,
            'payment_method' => 'cash',
        ]);
        $charge = TreatmentCharge::create([
            'client_id' => $client->id,
            'source_type' => TreatmentCharge::SOURCE_MANUAL,
            'amount' => 750,
            'description' => 'Consultation',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/clients/{$client->id}/personal-data")
            ->assertOk();

        $anonymized = Client::withoutGlobalScopes()->withTrashed()->findOrFail($client->id);
        $this->assertNotSame('Data Subject Patient', $anonymized->name);
        $this->assertNull($anonymized->email);
        $this->assertNotNull($anonymized->anonymized_at);
        $this->assertNotNull($anonymized->deleted_at);

        // Financial trail is untouched -- VUK retention outlives the KVKK erasure request.
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'client_id' => $client->id, 'amount' => 750]);
        $this->assertDatabaseHas('treatment_charges', ['id' => $charge->id, 'client_id' => $client->id, 'amount' => 750]);
    }

    public function test_erasure_deletes_xray_images_and_their_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $imageId = $this->post('/api/xray-images', [
            'images' => [UploadedFile::fake()->image('x.jpg')],
            'client_id' => $client->id,
        ])->assertCreated()->json('data.0.id');
        $path = XrayImage::withoutGlobalScopes()->findOrFail($imageId)->image_path;

        $this->deleteJson("/api/clients/{$client->id}/personal-data")->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('xray_images', ['id' => $imageId]);
    }

    public function test_a_user_cannot_erase_another_companys_client(): void
    {
        $otherClient = $this->makeClient(Company::factory()->create()->id);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/clients/{$otherClient->id}/personal-data")->assertNotFound();

        $this->assertDatabaseHas('clients', ['id' => $otherClient->id, 'name' => 'Data Subject Patient']);
    }
}
