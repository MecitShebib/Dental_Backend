<?php

namespace App\Providers;

use App\Models\CariParty;
use App\Models\Client;
use App\Models\LabPartner;
use App\Models\User;
use App\Models\Visit;
use App\Observers\ClientObserver;
use App\Observers\VisitObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        Client::observe(ClientObserver::class);
        Visit::observe(VisitObserver::class);

        // A cari_transactions.partyable_type stores one of these short
        // aliases instead of a full class path, so a future namespace/class
        // rename never breaks already-stored rows.
        Relation::enforceMorphMap([
            'cari_party' => CariParty::class,
            'user' => User::class,
            'lab_partner' => LabPartner::class,
        ]);
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

        // Public booking is unauthenticated by design, so it's the one
        // surface where a stranger can both read (doctor lists/availability)
        // and write (create a client + appointment) without a token. Reads
        // are generous since a patient legitimately clicks around picking a
        // time; writes are tight since each one creates real data.
        RateLimiter::for('public-booking-read', function ($request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('satisfaction-survey-submit', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('public-booking-write', function ($request) {
            $phone = (string) $request->input('client_phone');

            return [
                Limit::perHour(5)->by($phone.'|'.$request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        // A telephony provider posting real call events -- generous cap (a
        // busy clinic can legitimately get dozens of calls an hour). Keyed by
        // the raw booking_slug route segment (a string, available before
        // route-model-binding resolves it) rather than IP, since a provider's
        // webhook calls all originate from the same few server IPs shared
        // across every one of its customers.
        RateLimiter::for('call-webhook', function ($request) {
            return Limit::perMinute(60)->by($request->route('company', $request->ip()));
        });
    }
}
