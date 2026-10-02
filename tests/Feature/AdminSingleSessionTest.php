<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MobileOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The test suite normally forces SESSION_DRIVER=array (see phpunit.xml) so
 * individual tests don't need a real sessions table -- this file explicitly
 * opts back into 'database' since that's exactly the mechanism
 * Admin\AuthController::login() uses to sign out a user's other sessions.
 */
class AdminSingleSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    protected function adminUser(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'is_project_admin' => true,
            'status' => 'active',
            'phone' => '+963955000001',
            'password' => 'secret',
        ]);
    }

    public function test_logging_into_admin_from_a_new_browser_signs_out_the_previous_session(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        DB::table('sessions')->insert([
            'id' => 'stale-admin-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Old Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subMinutes(10)->timestamp,
        ]);

        $this->partialMock(MobileOtpService::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('generateOtp')->andReturn('147258');
        });

        $this->post('/admin/login', [
            'phone' => '963955000001',
            'password' => 'secret',
        ])->assertRedirect(route('admin.login.otp'));

        // Password alone never creates a session -- the stale one is only
        // cleared once the OTP step below actually completes the login.
        $this->assertDatabaseHas('sessions', ['id' => 'stale-admin-session']);

        $this->post('/admin/login/otp', ['otp' => '147258'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseMissing('sessions', ['id' => 'stale-admin-session']);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $admin->id)->count());
    }

    public function test_a_failed_login_attempt_does_not_touch_existing_sessions(): void
    {
        $admin = $this->adminUser();

        DB::table('sessions')->insert([
            'id' => 'still-good-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Old Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subMinutes(10)->timestamp,
        ]);

        $this->post('/admin/login', [
            'phone' => '963955000001',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseHas('sessions', ['id' => 'still-good-session']);
    }
}
