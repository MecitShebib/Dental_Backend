<?php

namespace Tests\Feature;

use App\Http\Resources\ApiTokenResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\MessageGroup;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionFeaturesAndCustomMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function company(?array $features): Company
    {
        $company = Company::factory()->create();
        $company->subscriptions()->update(['features' => $features === null ? null : json_encode($features)]);

        return $company;
    }

    protected function manager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $manager->roles()->attach(Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']));

        return $manager;
    }

    // --- Subscription features ------------------------------------------

    public function test_legacy_subscriptions_without_a_features_list_keep_every_feature(): void
    {
        $company = $this->company(null);

        $this->assertSame(Subscription::FEATURES, $company->enabledFeatures());
    }

    public function test_disabled_features_are_blocked_and_enabled_ones_work(): void
    {
        $company = $this->company(['whatsapp']);
        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/consent-templates')->assertForbidden()->assertJsonPath('feature', 'consent_templates');
        $this->getJson('/api/settings/api-tokens')->assertForbidden();
        $this->getJson('/api/settings/crm')->assertForbidden();
        $this->getJson('/api/settings/call-webhook')->assertForbidden();
        $this->getJson('/api/settings/whatsapp')->assertOk();
    }

    public function test_features_are_pooled_across_active_subscriptions(): void
    {
        $company = $this->company(['crm']);
        Subscription::factory()->create(['company_id' => $company->id, 'features' => ['api_tokens']]);

        $this->assertEqualsCanonicalizing(['crm', 'api_tokens'], $company->fresh()->enabledFeatures());
    }

    public function test_auth_me_reports_the_enabled_features(): void
    {
        $company = $this->company(['whatsapp', 'crm']);
        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.subscription_features', ['whatsapp', 'crm']);
    }

    public function test_integration_tokens_stop_working_without_the_api_tokens_feature(): void
    {
        $company = $this->company(['whatsapp']);
        $manager = $this->manager($company);
        $token = $manager->createToken(ApiTokenResource::PREFIX.'xray')->plainTextToken;

        $this->withToken($token)->getJson('/api/specialties')->assertForbidden();

        $company->subscriptions()->update(['features' => json_encode(['api_tokens'])]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/specialties')->assertOk();
    }

    public function test_admin_subscription_form_saves_the_ticked_features(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $subscription = $company->subscriptions()->firstOrFail();
        $subscription->update(['specialty_id' => Specialty::query()->where('key', 'dental')->value('id')]);
        $admin = User::factory()->create(['company_id' => null, 'is_project_admin' => true, 'status' => 'active']);

        $this->actingAs($admin)->put(route('admin.subscriptions.update', $subscription), [
            'company_id' => $company->id,
            'specialty_ids' => [$subscription->specialty_id],
            'plan_name' => 'Essentials',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_doctors' => 3,
            'max_assistants' => 2,
            'max_branches' => 1,
            'features_submitted' => '1',
            'features' => ['whatsapp', 'crm'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['whatsapp', 'crm'], $subscription->fresh()->features);

        // Nothing ticked = no optional features at all (not "all").
        $this->actingAs($admin)->put(route('admin.subscriptions.update', $subscription), [
            'company_id' => $company->id,
            'specialty_ids' => [$subscription->specialty_id],
            'plan_name' => 'Essentials',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_doctors' => 3,
            'max_assistants' => 2,
            'max_branches' => 1,
            'features_submitted' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $subscription->fresh()->features);
        $this->assertSame([], $company->fresh()->enabledFeatures());
    }

    // --- Custom message groups ------------------------------------------

    public function test_manager_can_manage_groups_and_messages(): void
    {
        $company = $this->company(null);
        Sanctum::actingAs($this->manager($company));

        $group = $this->postJson('/api/message-groups', ['name' => 'Diabetes patients'])->assertCreated()->json('data');
        $message = $this->postJson("/api/message-groups/{$group['uuid']}/messages", [
            'title' => 'Fasting reminder', 'body' => 'Hello {patient_name}, please come fasting tomorrow.',
        ])->assertCreated()->json('data');

        $this->putJson("/api/custom-messages/{$message['uuid']}", ['title' => 'Fasting reminder', 'body' => 'Updated'])->assertOk();
        $this->putJson("/api/message-groups/{$group['uuid']}", ['name' => 'Diabetes'])->assertOk();

        $this->getJson('/api/message-groups')->assertOk()
            ->assertJsonPath('data.0.name', 'Diabetes')
            ->assertJsonPath('data.0.messages.0.body', 'Updated');

        $this->deleteJson("/api/message-groups/{$group['uuid']}")->assertOk();
        $this->assertSame(0, CustomMessage::query()->count());
    }

    public function test_staff_can_read_but_not_manage_messages(): void
    {
        $company = $this->company(null);
        $group = MessageGroup::create(['company_id' => $company->id, 'name' => 'General']);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $this->getJson('/api/message-groups')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/message-groups', ['name' => 'x'])->assertForbidden();
        $this->postJson("/api/message-groups/{$group->uuid}/messages", ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_groups_are_isolated_per_company_and_work_without_the_whatsapp_feature(): void
    {
        $other = $this->company(null);
        MessageGroup::create(['company_id' => $other->id, 'name' => 'Other clinic']);

        // The subscription's WhatsApp feature only covers the Business API
        // integration -- the wa.me message button works for every clinic.
        $company = $this->company(['crm']);
        Sanctum::actingAs($this->manager($company));
        $this->getJson('/api/message-groups')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson('/api/message-groups/'.MessageGroup::query()->withoutGlobalScopes()->first()->uuid, ['name' => 'hijack'])->assertNotFound();
    }

    public function test_groups_are_kept_per_specialty(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = $this->company(null);
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/message-groups?specialty=nutrition', ['name' => 'Diabetes patients'])->assertCreated();
        $this->postJson('/api/message-groups?specialty=dental', ['name' => 'Implant aftercare'])->assertCreated();
        MessageGroup::create(['company_id' => $company->id, 'name' => 'Legacy (all specialties)']);

        $names = fn (string $key) => collect($this->getJson("/api/message-groups?specialty={$key}")->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Diabetes patients', 'Legacy (all specialties)'], $names('nutrition'));
        $this->assertSame(['Implant aftercare', 'Legacy (all specialties)'], $names('dental'));

        // A doctor always sees their own specialty's groups, whatever is asked.
        $dietitian = User::factory()->create([
            'company_id' => $company->id, 'is_doctor' => true,
            'specialty_id' => Specialty::query()->where('key', 'nutrition')->value('id'),
        ]);
        Sanctum::actingAs($dietitian);
        $this->assertSame(['Diabetes patients', 'Legacy (all specialties)'], $names('dental'));
    }

    // --- Message attachments --------------------------------------------

    public function test_message_can_carry_an_image_or_pdf_and_needs_text_or_a_file(): void
    {
        Storage::fake('public');
        $company = $this->company(null);
        Sanctum::actingAs($this->manager($company));
        $group = $this->postJson('/api/message-groups', ['name' => 'Diabetes'])->json('data');

        // Title alone is not enough.
        $this->post("/api/message-groups/{$group['uuid']}/messages", ['title' => 'Empty'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('body');

        // Title + image, no text.
        $message = $this->post("/api/message-groups/{$group['uuid']}/messages", [
            'title' => 'Diet sheet',
            'attachment' => UploadedFile::fake()->image('diet.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->assertSame('image', $message['attachment_type']);
        $this->assertSame('diet.png', $message['attachment_name']);
        $path = CustomMessage::query()->firstOrFail()->attachment_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, $message['attachment_url']);

        // Replacing it with a PDF deletes the old file.
        $updated = $this->post("/api/custom-messages/{$message['uuid']}", [
            '_method' => 'PUT', 'title' => 'Diet sheet',
            'attachment' => UploadedFile::fake()->create('diet.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('pdf', $updated['attachment_type']);
        Storage::disk('public')->assertMissing($path);

        // Removing the attachment without adding text is refused...
        $this->post("/api/custom-messages/{$message['uuid']}", ['_method' => 'PUT', 'title' => 'Diet sheet', 'remove_attachment' => '1'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('body');

        // ...but editing only the title keeps the existing file.
        $this->post("/api/custom-messages/{$message['uuid']}", ['_method' => 'PUT', 'title' => 'Renamed'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.attachment_type', 'pdf');

        // Deleting the group deletes the message and its file.
        $pdfPath = CustomMessage::query()->firstOrFail()->attachment_path;
        $this->deleteJson("/api/message-groups/{$group['uuid']}")->assertOk();
        Storage::disk('public')->assertMissing($pdfPath);
        $this->assertSame(0, CustomMessage::query()->count());
    }

    public function test_deleting_a_message_deletes_its_attachment(): void
    {
        Storage::fake('public');
        $company = $this->company(null);
        Sanctum::actingAs($this->manager($company));
        $group = $this->postJson('/api/message-groups', ['name' => 'General'])->json('data');
        $message = $this->post("/api/message-groups/{$group['uuid']}/messages", [
            'title' => 'Brochure', 'body' => 'See attached', 'attachment' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $path = CustomMessage::query()->firstOrFail()->attachment_path;

        $this->deleteJson("/api/custom-messages/{$message['uuid']}")->assertOk();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_attachments_must_be_images_or_pdfs(): void
    {
        Storage::fake('public');
        $company = $this->company(null);
        Sanctum::actingAs($this->manager($company));
        $group = $this->postJson('/api/message-groups', ['name' => 'General'])->json('data');

        $this->post("/api/message-groups/{$group['uuid']}/messages", [
            'title' => 'Bad', 'attachment' => UploadedFile::fake()->create('x.exe', 1, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('attachment');
    }

    // --- WhatsApp Business API vs wa.me ---------------------------------

    protected function connectWhatsApp(Company $company): void
    {
        config(['services.whatsapp.graph_base_url' => 'https://graph.test/v20.0']);
        WhatsAppIntegration::create(['company_id' => $company->id, 'access_token' => 'token', 'phone_number_id' => 'PHONE-ID', 'status' => 'active']);
    }

    protected function patient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id, 'client_code' => 'CL-7001', 'name' => 'Ayşe Yılmaz',
            'phone' => '0555 123 45 67', 'gender' => 'female', 'status' => 'new',
        ]);
    }

    public function test_message_goes_through_the_business_api_when_subscribed_and_connected(): void
    {
        Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $company = $this->company(['whatsapp']);
        $this->connectWhatsApp($company);
        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/auth/me')->assertJsonPath('data.whatsapp_api_active', true);
        $this->postJson("/api/clients/{$this->patient($company)->id}/whatsapp-message", ['text' => 'Merhaba'])
            ->assertOk()->assertJsonPath('data.sent', true);

        Http::assertSent(fn ($request) => $request['to'] === '905551234567' && $request['text']['body'] === 'Merhaba');
    }

    public function test_connected_integration_without_the_subscription_feature_is_not_used(): void
    {
        Http::fake();
        $company = $this->company(['crm']);
        $this->connectWhatsApp($company);
        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/auth/me')->assertJsonPath('data.whatsapp_api_active', false);
        $this->postJson("/api/clients/{$this->patient($company)->id}/whatsapp-message", ['text' => 'Merhaba'])
            ->assertOk()->assertJsonPath('data.sent', false)->assertJsonPath('data.phone', '905551234567');
        $this->postJson('/api/appointments/whatsapp-reminders/send')->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_reminders_can_be_sent_through_the_business_api(): void
    {
        Http::fake();
        $company = $this->company(['whatsapp']);
        $this->connectWhatsApp($company);
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/appointments/whatsapp-reminders/send')
            ->assertOk()->assertJsonPath('data.sent', 0)->assertJsonPath('data.failed', 0);
    }
}
