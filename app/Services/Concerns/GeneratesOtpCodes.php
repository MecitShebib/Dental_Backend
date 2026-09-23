<?php

namespace App\Services\Concerns;

use App\Services\IletiMerkeziSmsService;

/**
 * Shared by every OTP-issuing service (MobileOtpService for staff login,
 * PublicBookingOtpService for the unauthenticated booking page) so they stay
 * behaviorally identical for the parts that don't depend on who's being
 * verified: same ILETIMERKEZI_ENABLED gate, same MOBILE_OTP_FIXED_CODE /
 * digits config, same phone normalization/masking.
 */
trait GeneratesOtpCodes
{
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

    protected function providerEnabled(): bool
    {
        return app(IletiMerkeziSmsService::class)->enabled();
    }

    /**
     * True while a fixed testing code (MOBILE_OTP_FIXED_CODE) is configured,
     * i.e. every OTP challenge would resolve to the same known value. Used by
     * the login flow to skip the OTP screen entirely rather than make the
     * user type a code that provides no real second factor.
     */
    public function usesFixedCode(): bool
    {
        return (string) config('services.otp.fixed_code', '') !== '';
    }

    protected function generateOtp(): string
    {
        $fixed = (string) config('services.otp.fixed_code', '');
        if ($fixed !== '') {
            return $fixed;
        }

        $digits = max(1, (int) config('services.otp.digits', 6));
        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_pad('', $digits, '9');

        return (string) random_int($min, $max);
    }
}
