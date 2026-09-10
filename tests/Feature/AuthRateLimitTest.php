<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
            'services.iletimerkezi.sender' => 'Dentavaria',
            'services.otp.digits' => 6,
        ]);

        Http::fake([
            'https://api.iletimerkezi.com/v1/send-sms/json*' => Http::response([
                'response' => [
                    'status' => ['code' => 200, 'message' => 'OK'],
                    'order' => ['id' => '1000007721'],
                ],
            ], 200),
        ]);
    }

    public function test_otp_request_route_is_throttled_per_mobile(): void
    {
        $this->activeUser();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/login', [
                'mobile' => '963955123456',
                'password' => 'secret',
            ])->assertOk();
        }

        $this->postJson('/api/auth/login', [
            'mobile' => '963955123456',
            'password' => 'secret',
        ])->assertStatus(429);
    }

    public function test_otp_verify_route_is_throttled_per_mobile_and_ip(): void
    {
        $this->activeUser();

        $reference = $this->postJson('/api/auth/login', [
            'mobile' => '963955123456',
            'password' => 'secret',
        ])->assertOk()->json('otp_reference');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login/verify-otp', [
                'mobile' => '963955123456',
                'password' => 'secret',
                'otp' => '000000',
                'otp_reference' => $reference,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => '963955123456',
            'password' => 'secret',
            'otp' => '000000',
            'otp_reference' => $reference,
        ])->assertStatus(429);
    }

    public function test_admin_login_route_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'phone' => '900000000',
                'password' => 'wrong',
            ])->assertSessionHasErrors('phone');
        }

        $this->post('/admin/login', [
            'phone' => '900000000',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_otp_challenge_locks_after_max_wrong_attempts(): void
    {
        $user = $this->activeUser();
        $service = app(MobileOtpService::class);
        $challenge = $service->issue($user, UserOtp::PURPOSE_LOGIN, '963955123456');

        for ($i = 0; $i < UserOtp::MAX_ATTEMPTS; $i++) {
            try {
                $service->verify($challenge, '000000');
                $this->fail('Expected a validation exception for a wrong OTP.');
            } catch (ValidationException) {
                // expected
            }
        }

        $this->assertTrue($challenge->fresh()->isLocked());

        $this->expectException(ValidationException::class);
        $service->verify($challenge->fresh(), '123456');
    }

    protected function activeUser(): User
    {
        $company = Company::factory()->create(['status' => 'active']);

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
