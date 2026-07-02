<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
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

        $otp = $this->providerEnabled()
            ? $this->sendTurkeySmsOtp($mobile)
            : (string) random_int(100000, 999999);

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

        if (! Hash::check($otp, $challenge->otp_code)) {
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

    protected function sendTurkeySmsOtp(string $mobile): string
    {
        $apiKey = (string) config('services.turkeysms.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'mobile' => ['Turkey SMS API key is not configured.'],
            ]);
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get(rtrim((string) config('services.turkeysms.base_url'), '/').'/api/v3/otp/otp_get.php', [
                'api_key' => $apiKey,
                'mobile' => $this->normalizeMobile($mobile),
                'digits' => (int) config('services.turkeysms.otp_digits', 6),
                'report' => (int) config('services.turkeysms.report', 1),
                'lang' => (int) config('services.turkeysms.otp_lang', 2),
                'response_type' => (string) config('services.turkeysms.response_type', 'json'),
            ]);

        if (! $response->successful()) {
            Log::error('Turkey SMS OTP request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'mobile' => ['Failed to send OTP SMS.'],
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! ($payload['result'] ?? false) || empty($payload['otp_code'])) {
            Log::error('Turkey SMS OTP response was invalid.', [
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'mobile' => [$payload['result_message'] ?? 'Failed to send OTP SMS.'],
            ]);
        }

        return (string) $payload['otp_code'];
    }
}
