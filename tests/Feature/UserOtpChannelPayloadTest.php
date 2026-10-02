<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Settings > Change Password tells the user where the verification code
 * will go *before* it's sent, so the user payload must carry the configured
 * OTP channel (MOBILE_OTP_CHANNEL) -- otherwise that screen can only guess
 * and showed the phone number even when codes go by email.
 */
class UserOtpChannelPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_payload_exposes_the_configured_otp_channel(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        config(['services.otp.channel' => 'email']);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.feature_flags.otp_channel', 'email');

        config(['services.otp.channel' => 'sms']);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.feature_flags.otp_channel', 'sms');
    }
}
