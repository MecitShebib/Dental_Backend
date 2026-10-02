<?php

namespace Tests\Feature\Admin;

use App\Mail\AdminLoginOtpMail;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminLoginOtpTest extends TestCase
{
    use RefreshDatabase;

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

    protected function fakeGeneratedOtp(string $otp): void
    {
        $this->partialMock(MobileOtpService::class, function ($mock) use ($otp) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('generateOtp')->andReturn($otp);
        });
    }

    public function test_correct_password_redirects_to_the_otp_screen_without_logging_in(): void
    {
        Mail::fake();
        $this->adminUser();

        $response = $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);

        $response->assertRedirect(route('admin.login.otp'));
        $this->assertGuest();
    }

    public function test_the_otp_is_emailed_to_the_fixed_configured_address_not_the_admins_own_email(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->fakeGeneratedOtp('258147');

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);

        Mail::assertSent(AdminLoginOtpMail::class, function (AdminLoginOtpMail $mail) use ($admin) {
            return $mail->hasTo(config('services.admin_login.otp_email'))
                && ! $mail->hasTo($admin->email)
                && $mail->otp === '258147';
        });
    }

    public function test_neither_login_page_nor_otp_page_ever_renders_the_fixed_destination_email(): void
    {
        Mail::fake();
        $this->adminUser();
        $destination = config('services.admin_login.otp_email');

        $this->get('/admin/login')->assertDontSee($destination);

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);
        $this->get('/admin/login/otp')->assertOk()->assertDontSee($destination);
    }

    public function test_the_otp_screen_is_unreachable_without_first_passing_the_password_step(): void
    {
        $this->get('/admin/login/otp')->assertRedirect(route('admin.login'));
    }

    public function test_the_otp_endpoints_reject_a_request_with_no_pending_login(): void
    {
        $this->post('/admin/login/otp', ['otp' => '123456'])->assertRedirect(route('admin.login'));
        $this->post('/admin/login/otp/resend')->assertRedirect(route('admin.login'));
    }

    public function test_correct_otp_completes_the_login(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->fakeGeneratedOtp('951753');

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);
        $response = $this->post('/admin/login/otp', ['otp' => '951753']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_an_incorrect_otp_is_rejected_and_does_not_log_in(): void
    {
        Mail::fake();
        $this->adminUser();
        $this->fakeGeneratedOtp('111111');

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);
        $response = $this->post('/admin/login/otp', ['otp' => '000000']);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_an_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $this->adminUser();
        $this->fakeGeneratedOtp('222222');

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);

        UserOtp::query()->where('purpose', UserOtp::PURPOSE_ADMIN_LOGIN)->update(['expires_at' => now()->subMinute()]);

        $response = $this->post('/admin/login/otp', ['otp' => '222222']);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_resending_issues_a_new_code_and_invalidates_the_old_one(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->fakeGeneratedOtp('333333');
        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);

        $this->fakeGeneratedOtp('444444');
        $this->post('/admin/login/otp/resend')->assertRedirect(route('admin.login.otp'));

        // The old code no longer works -- verification always checks against
        // the latest challenge for this pending login, not any older one.
        $this->post('/admin/login/otp', ['otp' => '333333'])->assertSessionHasErrors('otp');
        $this->assertGuest();

        // ...only the freshly resent one does.
        $this->post('/admin/login/otp', ['otp' => '444444'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_an_otp_cannot_be_reused_after_a_successful_login(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->fakeGeneratedOtp('555555');

        $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);
        $this->post('/admin/login/otp', ['otp' => '555555'])->assertRedirect(route('admin.dashboard'));
        Auth::logout();

        // verifyOtp() clears the pending-login session key on success, so a
        // genuine replay couldn't even reach the OTP check without a fresh
        // login() call first (which would issue a brand new challenge) --
        // re-seed the session key directly to prove the underlying "already
        // used" guard on the original challenge still holds regardless.
        $response = $this->withSession(['admin_login.pending_user_id' => $admin->id])
            ->post('/admin/login/otp', ['otp' => '555555']);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_a_fixed_testing_otp_code_bypasses_the_otp_screen_entirely(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        config(['services.otp.fixed_code' => '505050']);

        $response = $this->post('/admin/login', ['phone' => '963955000001', 'password' => 'secret']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        Mail::assertNotSent(AdminLoginOtpMail::class);
    }
}
