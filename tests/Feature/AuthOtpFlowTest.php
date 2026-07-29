<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
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
            'services.turkeysms.enabled' => true,
            'services.turkeysms.api_key' => 'test-api-key',
            'services.turkeysms.base_url' => 'https://turkeysms.com.tr',
            'services.turkeysms.title' => 'ELECMINDS',
            'services.turkeysms.otp_digits' => 6,
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
            'https://turkeysms.com.tr/api/v3/gonder/add-content*' => Http::response([
                'result' => true,
                'sms_id' => '1000007721',
                'result_code' => 'TS-1024',
                'result_message' => 'The message was sent successfully',
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
            'https://turkeysms.com.tr/api/v3/gonder/add-content*' => Http::response([
                'result' => true,
                'sms_id' => '1000007721',
                'result_code' => 'TS-1024',
                'result_message' => 'The message was sent successfully',
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
            ->assertJsonPath('user.mobile', '+963955123456');

        $this->assertDatabaseMissing('user_otps', [
            'id' => $challenge->id,
            'used_at' => null,
        ]);
    }

    public function test_forgot_password_verification_and_reset_flow(): void
    {
        $user = $this->activeUser();
        $this->fakeGeneratedOtp('654321');
        Http::fake([
            'https://turkeysms.com.tr/api/v3/gonder/add-content*' => Http::response([
                'result' => true,
                'sms_id' => '1000008899',
                'result_code' => 'TS-1024',
                'result_message' => 'The message was sent successfully',
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
