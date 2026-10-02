<?php

namespace App\Services;

use App\Mail\PublicBookingOtpMail;
use App\Models\Company;
use App\Models\PublicBookingOtp;
use App\Services\Concerns\GeneratesOtpCodes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Same shape as MobileOtpService (issue -> findChallenge -> verify -> markUsed),
 * but for the unauthenticated /book/{company} page: challenges are keyed by
 * Company + phone number instead of a User, since no account is involved.
 */
class PublicBookingOtpService
{
    use GeneratesOtpCodes;

    /** "email" or "sms" -- the same MOBILE_OTP_CHANNEL the staff login uses. */
    public function channel(): string
    {
        return config('services.otp.channel', 'sms') === 'email' ? 'email' : 'sms';
    }

    /**
     * With the email channel the code goes to $email and the challenge is
     * keyed by it ($mobile is optional); with sms it goes to $mobile.
     */
    public function issue(Company $company, ?string $mobile, ?string $email = null): PublicBookingOtp
    {
        if ($this->channel() === 'email') {
            return $this->issueByEmail($company, (string) $email, $mobile);
        }

        $mobile = (string) $mobile;
        $normalized = $this->normalizeMobile($mobile);

        PublicBookingOtp::query()
            ->where('company_id', $company->id)
            ->where('mobile', $normalized)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();

        if ($this->providerEnabled()) {
            $sent = app(IletiMerkeziSmsService::class)->send(
                $mobile,
                "Your {$company->name} appointment verification code is: {$otp}"
            );

            if (! $sent) {
                throw ValidationException::withMessages([
                    'client_phone' => ['Failed to send the verification code. Please try again.'],
                ]);
            }
        }

        $challenge = PublicBookingOtp::query()->create([
            'company_id' => $company->id,
            'mobile' => $normalized,
            'otp_code' => Hash::make($otp),
            'reference' => 'booking_otp_ref_'.Str::lower((string) Str::uuid()),
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::info('Public booking OTP sent.', [
            'company_id' => $company->id,
            'mobile' => $mobile,
            'otp' => $this->providerEnabled() ? 'sent_via_sms_provider' : $otp,
            'reference' => $challenge->reference,
        ]);

        return $challenge;
    }

    protected function issueByEmail(Company $company, string $email, ?string $mobile): PublicBookingOtp
    {
        $email = mb_strtolower(trim($email));

        PublicBookingOtp::query()
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();

        try {
            Mail::to($email)->send(new PublicBookingOtpMail($company->name, $otp));
        } catch (Throwable $e) {
            Log::error('Failed to send public booking OTP email.', ['company_id' => $company->id, 'exception' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'client_email' => ['Failed to send the verification code. Please try again.'],
            ]);
        }

        $challenge = PublicBookingOtp::query()->create([
            'company_id' => $company->id,
            'mobile' => $mobile ? $this->normalizeMobile($mobile) : null,
            'email' => $email,
            'otp_code' => Hash::make($otp),
            'reference' => 'booking_otp_ref_'.Str::lower((string) Str::uuid()),
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::info('Public booking OTP sent.', [
            'company_id' => $company->id,
            'channel' => 'email',
            'reference' => $challenge->reference,
        ]);

        return $challenge;
    }

    /** Email-channel counterpart of findChallenge(): the challenge must belong to that email. */
    public function findChallengeByEmail(Company $company, string $email, string $reference): ?PublicBookingOtp
    {
        return PublicBookingOtp::query()
            ->where('company_id', $company->id)
            ->where('email', mb_strtolower(trim($email)))
            ->where('reference', $reference)
            ->first();
    }

    public function findChallenge(Company $company, string $mobile, ?string $reference = null): ?PublicBookingOtp
    {
        return PublicBookingOtp::query()
            ->where('company_id', $company->id)
            ->where('mobile', $this->normalizeMobile($mobile))
            ->when(
                $reference,
                fn ($query) => $query->where('reference', $reference),
                fn ($query) => $query->latest('id')
            )
            ->first();
    }

    public function verify(PublicBookingOtp $challenge, string $otp): void
    {
        if ($challenge->isUsed()) {
            throw ValidationException::withMessages([
                'otp' => ['This code has already been used.'],
            ]);
        }

        if ($challenge->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['This code has expired.'],
            ]);
        }

        if ($challenge->isLocked()) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new code.'],
            ]);
        }

        if (! Hash::check($otp, $challenge->otp_code)) {
            $challenge->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['The verification code is incorrect.'],
            ]);
        }
    }

    public function markUsed(PublicBookingOtp $challenge): void
    {
        $challenge->forceFill([
            'verified_at' => $challenge->verified_at ?? now(),
            'used_at' => now(),
        ])->save();
    }
}
