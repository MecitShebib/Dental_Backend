<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // Limits how many OTP SMS can be sent to a single phone number,
        // regardless of the requester's IP (the abuse this guards against
        // is SMS-bombing a victim's phone, which costs real money).
        RateLimiter::for('otp-request', function ($request) {
            $mobile = (string) $request->input('mobile');

            return [
                Limit::perMinute(3)->by($mobile),
                Limit::perHour(10)->by($mobile),
            ];
        });

        // Limits OTP verification attempts per mobile+IP. A hard per-challenge
        // attempt cap also exists in MobileOtpService as defense in depth.
        RateLimiter::for('otp-verify', function ($request) {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(5)->by($mobile.'|'.$request->ip());
        });

        RateLimiter::for('admin-login', function ($request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Blanket abuse/scraping cap for the rest of the authenticated API.
        // Generous enough that it never bothers real usage.
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
