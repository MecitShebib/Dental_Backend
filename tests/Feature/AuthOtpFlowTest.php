<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use App\Specialties\Cosmetic\CosmeticModule;
use App\Specialties\Dental\DentalModule;
use App\Specialties\Gynecology\GynecologyModule;
use App\Specialties\InternalMedicine\InternalMedicineModule;
use App\Specialties\Orthopedics\OrthopedicsModule;
use App\Specialties\SpecialtyModuleRegistry;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.infobip.enabled' => true,
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.base_url' => 'https://api.infobip.com',
            'services.infobip.sender' => 'Dentavaria',
            'services.otp.digits' => 6,
        ]);
    }

    protected function fakeGeneratedOtp(string $otp): void
    {
        $this->partialMock(MobileOtpService::class, function ($mock) use ($otp) {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('generateOtp')->andReturn($otp);
        });
    }

    public function test_login_returns_otp_challenge_without_token(): void
    {
        $user = $this->activeUser();
        $this->fakeGeneratedOtp('123456');
        Http::fake([
            'https://api.infobip.com/sms/2/text/advanced*' => Http::response([
                'messages' => [[
                    'messageId' => '1000007721',
                    'status' => ['groupId' => 1, 'groupName' => 'PENDING'],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'mobile' => '963955123456',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'otp_reference', 'masked_mobile', 'expires_at'])
            ->assertJsonMissing(['token']);

        $reference = $response->json('otp_reference');

        $this->assertDatabaseHas('user_otps', [
            'user_id' => $user->id,
            'purpose' => UserOtp::PURPOSE_LOGIN,
            'reference' => $reference,
        ]);
    }

    public function test_verify_login_otp_returns_token_and_user(): void
    {
        $user = $this->activeUser();
        $this->fakeGeneratedOtp('123456');
        Http::fake([
            'https://api.infobip.com/sms/2/text/advanced*' => Http::response([
                'messages' => [[
                    'messageId' => '1000007721',
                    'status' => ['groupId' => 1, 'groupName' => 'PENDING'],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'mobile' => '963955123456',
            'password' => 'secret',
        ])->assertOk();

        $challenge = UserOtp::query()->where('reference', $response->json('otp_reference'))->firstOrFail();

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => '963955123456',
            'password' => 'secret',
            'otp' => '123456',
            'otp_reference' => $challenge->reference,
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'mobile']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.mobile', '+963955123456')
            // This test's company only holds a dental subscription, so it
            // only has one USABLE specialty regardless of how many others
            // are globally built -- requires_specialty_selection is scoped
            // to the company's own activeSpecialties(), not every built
            // module. See requires_specialty_selection_is_true... below for
            // the true case (a company subscribed to 2+ built specialties).
            ->assertJsonPath('user.requires_specialty_selection', false);

        $this->assertDatabaseMissing('user_otps', [
            'id' => $challenge->id,
            'used_at' => null,
        ]);
    }

    public function test_requires_specialty_selection_is_true_for_staff_at_a_company_with_two_built_specialties(): void
    {
        $this->seed(SpecialtySeeder::class);
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        $company = Company::factory()->create(['code' => 'DAM-02', 'status' => 'active']);
        $company->subscriptions()->delete();
        Subscription::create(['company_id' => $company->id, 'specialty_id' => $dental->id, 'plan_name' => 'Dental', 'status' => 'active', 'starts_at' => now()->subDay()->toDateString()]);
        Subscription::create(['company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Gynecology', 'status' => 'active', 'starts_at' => now()->subDay()->toDateString()]);

        $user = User::factory()->create(['company_id' => $company->id, 'phone' => '+963955223456', 'password' => 'secret', 'status' => 'active']);

        // Gynecology has no real module yet -- fake the registry so this
        // test can exercise the "2+ USABLE specialties" branch without
        // waiting for a real second specialty to actually be built.
        // Extends the real GynecologyModule (SpecialtyModuleRegistry's
        // constructor type-hints the concrete classes, not the interface,
        // so it can auto-resolve without any binding in production).
        $fakeGynecology = new class extends GynecologyModule
        {
            public function isBuilt(): bool
            {
                return true;
            }
        };

        $this->app->bind(SpecialtyModuleRegistry::class, fn ($app) => new SpecialtyModuleRegistry(
            $app->make(DentalModule::class),
            $fakeGynecology,
            $app->make(InternalMedicineModule::class),
            $app->make(OrthopedicsModule::class),
            $app->make(CosmeticModule::class),
        ));

        $this->fakeGeneratedOtp('999111');
        Http::fake(['https://api.infobip.com/sms/2/text/advanced*' => Http::response(['messages' => [['messageId' => '1', 'status' => ['groupId' => 1, 'groupName' => 'PENDING']]]], 200)]);

        $loginResponse = $this->postJson('/api/auth/login', ['mobile' => '963955223456', 'password' => 'secret'])->assertOk();
        $challenge = UserOtp::query()->where('reference', $loginResponse->json('otp_reference'))->firstOrFail();

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => '963955223456',
            'password' => 'secret',
            'otp' => '999111',
            'otp_reference' => $challenge->reference,
        ])->assertOk()->assertJsonPath('user.requires_specialty_selection', true);
    }

    public function test_forgot_password_verification_and_reset_flow(): void
    {
        $user = $this->activeUser();
        $this->fakeGeneratedOtp('654321');
        Http::fake([
            'https://api.infobip.com/sms/2/text/advanced*' => Http::response([
                'messages' => [[
                    'messageId' => '1000008899',
                    'status' => ['groupId' => 1, 'groupName' => 'PENDING'],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'mobile' => '963955123456',
        ])->assertOk();

        $challenge = UserOtp::query()->where('reference', $response->json('otp_reference'))->firstOrFail();

        $this->postJson('/api/auth/forgot-password/verify-otp', [
            'mobile' => '963955123456',
            'otp' => '654321',
            'otp_reference' => $challenge->reference,
        ])->assertOk()
            ->assertJsonPath('verified', true);

        $this->postJson('/api/auth/reset-password', [
            'mobile' => '963955123456',
            'otp_reference' => $challenge->reference,
            'new_password' => 'new-secret',
            'new_password_confirmation' => 'new-secret',
        ])->assertOk()
            ->assertJsonPath('message', 'Password reset successfully');

        $user->refresh();

        $this->assertTrue(Hash::check('new-secret', $user->password));
        $this->assertDatabaseMissing('user_otps', [
            'id' => $challenge->id,
            'used_at' => null,
        ]);
    }

    protected function activeUser(): User
    {
        $company = Company::factory()->create([
            'code' => 'DAM-01',
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_name' => 'Active Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 5,
            'active_users' => 1,
            'price' => 0,
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'phone' => '+963955123456',
            'password' => 'secret',
            'status' => 'active',
        ]);
    }
}
