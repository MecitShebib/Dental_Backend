<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use App\Services\SubscriptionAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin login is two steps: phone+password, then an OTP emailed to a fixed,
 * project-wide inbox (config('services.admin_login.otp_email')) -- never the
 * logging-in admin's own address, and never shown or typed anywhere in this
 * flow. The pending (password-verified, not-yet-authenticated) user id lives
 * in the session between the two requests; Auth::login() itself only ever
 * happens after the OTP is verified (or the local-dev fixed-code bypass).
 */
class AuthController extends Controller
{
    protected const SESSION_PENDING_USER_ID = 'admin_login.pending_user_id';

    public function __construct(
        protected SubscriptionAccessService $subscriptionAccess,
        protected MobileOtpService $otpService,
    ) {}

    public function showLogin()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->isProjectAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->findAuthorizableAdmin($request->string('phone'), $request->string('password'));

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        // While a fixed testing OTP is configured, every challenge resolves
        // to the same known code -- it verifies nothing, so skip the OTP
        // screen entirely and log the admin straight in, same as the mobile
        // API's own login() does.
        if ($this->otpService->usesFixedCode()) {
            return $this->completeLogin($user, $request);
        }

        $this->otpService->issueAdminLoginOtp($user, $request->ip());
        $request->session()->put(self::SESSION_PENDING_USER_ID, $user->id);

        return redirect()->route('admin.login.otp');
    }

    public function showOtp(Request $request)
    {
        if (! $request->session()->has(self::SESSION_PENDING_USER_ID)) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'string']]);

        $userId = $request->session()->get(self::SESSION_PENDING_USER_ID);
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['phone' => 'Your session expired. Please sign in again.']);
        }

        // Re-check admin/subscription status from scratch rather than
        // trusting the state from the first request -- nothing stops time
        // (and a company's subscription) from passing between the two steps.
        if (! $user->isProjectAdmin() || ! $this->subscriptionAccess->canLogin($user)) {
            $request->session()->forget(self::SESSION_PENDING_USER_ID);

            return redirect()->route('admin.login')->withErrors(['phone' => 'This account can no longer access the admin panel.']);
        }

        $challenge = $this->otpService->findChallenge($user, UserOtp::PURPOSE_ADMIN_LOGIN);

        if (! $challenge) {
            return redirect()->route('admin.login')->withErrors(['phone' => 'Your session expired. Please sign in again.']);
        }

        try {
            $this->otpService->verify($challenge, $request->string('otp'));
        } catch (ValidationException $e) {
            return back()->withErrors(['otp' => $e->validator->errors()->first('otp')]);
        }

        $this->otpService->markUsed($challenge);
        $request->session()->forget(self::SESSION_PENDING_USER_ID);

        return $this->completeLogin($user, $request);
    }

    public function resendOtp(Request $request)
    {
        $userId = $request->session()->get(self::SESSION_PENDING_USER_ID);
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['phone' => 'Your session expired. Please sign in again.']);
        }

        $this->otpService->issueAdminLoginOtp($user, $request->ip());

        return redirect()->route('admin.login.otp')->with('status', 'A new code has been sent.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Phone+password check shared by login(). Returns the matched User on
     * success, or a redirect-back-with-errors response on failure -- callers
     * check the return type since PHP has no union-return early-exit sugar.
     */
    protected function findAuthorizableAdmin(string $phone, string $password): User|RedirectResponse
    {
        // Phone numbers can be stored with varying formatting (+, spaces,
        // dashes) -- compare on digits only, same normalization the mobile
        // OTP login already applies, rather than an exact-string match.
        $normalizedPhone = preg_replace('/\D+/', '', $phone);

        $user = User::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$normalizedPhone])
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return back()->withErrors(['phone' => 'Invalid credentials.'])->onlyInput('phone');
        }

        if (! $user->isProjectAdmin()) {
            return back()->withErrors(['phone' => 'This account is not allowed to access the admin panel.'])->onlyInput('phone');
        }

        if (! $this->subscriptionAccess->canLogin($user)) {
            return back()->withErrors(['phone' => $this->subscriptionAccess->loginErrorMessage($user)])->onlyInput('phone');
        }

        return $user;
    }

    protected function completeLogin(User $user, Request $request): RedirectResponse
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Single-session-per-account: logging in from another browser/device
        // must sign the previous admin session out. Safe to run unconditionally
        // -- if SESSION_DRIVER isn't 'database' this table is simply unused and
        // the delete is a no-op.
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return redirect()->route('admin.dashboard');
    }
}
