<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserOtp;
use App\Services\Concerns\GeneratesOtpCodes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileOtpService
{
    use GeneratesOtpCodes;

    public function issue(User $user, string $purpose, string $mobile): UserOtp
    {
        UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();

        if ($this->providerEnabled()) {
            $this->sendOtpSms($mobile, $otp);
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
            'otp' => $this->providerEnabled() ? 'sent_via_sms_provider' : $otp,
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

    protected function referenceFor(string $purpose): string
    {
        $prefix = match ($purpose) {
            UserOtp::PURPOSE_LOGIN => 'login_otp_ref',
            UserOtp::PURPOSE_FORGOT_PASSWORD => 'forgot_otp_ref',
            default => 'otp_ref',
        };

        return $prefix.'_'.Str::lower((string) Str::uuid());
    }

    protected function sendOtpSms(string $mobile, string $otp): void
    {
        $sent = app(IletiMerkeziSmsService::class)->send($mobile, "Your Dentavaria verification code is: {$otp}");

        if (! $sent) {
            throw ValidationException::withMessages([
                'mobile' => ['Failed to send OTP SMS.'],
            ]);
        }
    }
}
