<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileOtpService
{
    public function issue(User $user, string $purpose, string $mobile): UserOtp
    {
        UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();

        if ($this->providerEnabled()) {
            $this->sendTurkeySmsOtp($mobile, $otp);
        }

        $challenge = UserOtp::query()->create([
            'user_id' => $user->id,
            'mobile' => $this->normalizeMobile($mobile),
            'otp_code' => Hash::make($otp),
            'purpose' => $purpose,
            'reference' => $this->referenceFor($purpose),
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::info('OTP sent to mobile.', [
            'user_id' => $user->id,
            'purpose' => $purpose,
            'mobile' => $mobile,
            'otp' => $this->providerEnabled() ? 'sent_via_turkeysms' : $otp,
            'reference' => $challenge->reference,
        ]);

        return $challenge;
    }

    public function findChallenge(User $user, string $purpose, ?string $reference = null): ?UserOtp
    {
        return UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->when(
                $reference,
                fn ($query) => $query->where('reference', $reference),
                fn ($query) => $query->latest('id')
            )
            ->first();
    }

    public function verify(UserOtp $challenge, string $otp): void
    {
        if ($challenge->isUsed()) {
            throw ValidationException::withMessages([
                'otp' => ['This OTP has already been used.'],
            ]);
        }

        if ($challenge->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['This OTP has expired.'],
            ]);
        }

        if ($challenge->isLocked()) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new OTP.'],
            ]);
        }

        if (! Hash::check($otp, $challenge->otp_code)) {
            $challenge->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid.'],
            ]);
        }
    }

    public function markVerified(UserOtp $challenge): void
    {
        if ($challenge->verified_at === null) {
            $challenge->forceFill(['verified_at' => now()])->save();
        }
    }

    public function markUsed(UserOtp $challenge): void
    {
        $challenge->forceFill([
            'verified_at' => $challenge->verified_at ?? now(),
            'used_at' => now(),
        ])->save();
    }

    public function maskMobile(string $mobile): string
    {
        $normalized = $this->normalizeMobile($mobile);
        $lastFour = substr($normalized, -4);

        return str_repeat('*', max(strlen($normalized) - 4, 0)).$lastFour;
    }

    public function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\D+/', '', trim($mobile)) ?? '';
    }

    protected function referenceFor(string $purpose): string
    {
        $prefix = match ($purpose) {
            UserOtp::PURPOSE_LOGIN => 'login_otp_ref',
            UserOtp::PURPOSE_FORGOT_PASSWORD => 'forgot_otp_ref',
            default => 'otp_ref',
        };

        return $prefix.'_'.Str::lower((string) Str::uuid());
    }

    protected function providerEnabled(): bool
    {
        return (bool) config('services.turkeysms.enabled');
    }

    protected function generateOtp(): string
    {
        $fixed = (string) config('services.turkeysms.fixed_otp', '');
        if ($fixed !== '') {
            return $fixed;
        }

        $digits = max(1, (int) config('services.turkeysms.otp_digits', 6));
        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_pad('', $digits, '9');

        return (string) random_int($min, $max);
    }

    protected function sendTurkeySmsOtp(string $mobile, string $otp): void
    {
        $apiKey = (string) config('services.turkeysms.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'mobile' => ['Turkey SMS API key is not configured.'],
            ]);
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->post(rtrim((string) config('services.turkeysms.base_url'), '/').'/api/v3/gonder/add-content', [
                'api_key' => $apiKey,
                'sentto' => $this->normalizeMobile($mobile),
                'title' => (string) config('services.turkeysms.title', 'ELECMINDS'),
                'text' => "Your Dentavaria verification code is: {$otp}",
            ]);

        if (! $response->successful()) {
            Log::error('Turkey SMS request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'mobile' => ['Failed to send OTP SMS.'],
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! ($payload['result'] ?? false)) {
            Log::error('Turkey SMS response was invalid.', [
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'mobile' => [$payload['result_message'] ?? 'Failed to send OTP SMS.'],
            ]);
        }
    }
}
