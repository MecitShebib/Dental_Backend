<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(protected SubscriptionAccessService $subscriptionAccess)
    {
    }

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

        // Phone numbers can be stored with varying formatting (+, spaces,
        // dashes) -- compare on digits only, same normalization the mobile
        // OTP login already applies, rather than an exact-string match.
        $normalizedPhone = preg_replace('/\D+/', '', $request->string('phone'));

        $user = User::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$normalizedPhone])
            ->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return back()->withErrors(['phone' => 'Invalid credentials.'])->onlyInput('phone');
        }

        if (! $user->isProjectAdmin()) {
            return back()->withErrors(['phone' => 'This account is not allowed to access the admin panel.'])->onlyInput('phone');
        }

        if (! $this->subscriptionAccess->canLogin($user)) {
            return back()->withErrors(['phone' => $this->subscriptionAccess->loginErrorMessage($user)])->onlyInput('phone');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
