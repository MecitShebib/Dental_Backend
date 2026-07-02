<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(protected SubscriptionAccessService $subscriptionAccess)
    {
    }

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isProjectAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isProjectAdmin()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account is not allowed to access the admin panel.'])->onlyInput('email');
        }

        if (! $this->subscriptionAccess->canLogin($user)) {
            Auth::logout();

            return back()->withErrors(['email' => $this->subscriptionAccess->loginErrorMessage($user)])->onlyInput('email');
        }

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
