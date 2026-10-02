<?php

namespace App\Services;

use App\Mail\AdminLoginOtpMail;
use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\Concerns\GeneratesOtpCodes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        $channel = config('services.otp.channel', 'sms');

        if ($channel === 'email') {
            $this->sendOtpEmail($user, $otp);
        } elseif ($this->providerEnabled()) {
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

        Log::info('OTP sent.', [
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'mobile' => $mobile,
            'otp' => ($channel === 'email' || $this->providerEnabled()) ? 'sent' : $otp,
            'reference' => $challenge->reference,
        ]);

        return $challenge;
    }

    /**
     * The admin panel's own second factor -- unlike issue() above (which
     * sends to the logging-in user's own mobile/email, chosen by
     * MOBILE_OTP_CHANNEL), this always emails a fixed, project-wide inbox
     * (ADMIN_LOGIN_OTP_EMAIL) that no admin ever sees or types; whoever
     * holds that inbox is the real gatekeeper for admin access. Kept
     * separate from issue() since it doesn't participate in the
     * SMS-vs-email channel toggle at all -- it's always email, always to
     * this one address, regardless of MOBILE_OTP_CHANNEL.
     */
    public function issueAdminLoginOtp(User $user, ?string $ip): UserOtp
    {
        UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', UserOtp::PURPOSE_ADMIN_LOGIN)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();
        $destination = (string) config('services.admin_login.otp_email');

        if (! $this->usesFixedCode()) {
            Mail::to($destination)->send(new AdminLoginOtpMail($user, $otp, $ip));
        }

        $challenge = UserOtp::query()->create([
            'user_id' => $user->id,
            'mobile' => $destination,
            'otp_code' => Hash::make($otp),
            'purpose' => UserOtp::PURPOSE_ADMIN_LOGIN,
            'reference' => $this->referenceFor(UserOtp::PURPOSE_ADMIN_LOGIN),
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::info('Admin login OTP sent.', [
            'user_id' => $user->id,
            'ip' => $ip,
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
            UserOtp::PURPOSE_ADMIN_LOGIN => 'admin_login_otp_ref',
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

    protected function sendOtpEmail(User $user, string $otp): void
    {
        try {
            Mail::to($user->email)->send(new OtpCodeMail($user, $otp));
        } catch (Throwable $e) {
            Log::error('Failed to send OTP email.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'mobile' => ['Failed to send OTP email.'],
            ]);
        }
    }
}
