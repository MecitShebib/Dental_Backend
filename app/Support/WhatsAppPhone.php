<?php

namespace App\Support;

class WhatsAppPhone
{
    /**
     * WhatsApp (wa.me / Cloud API) wants the full international number,
     * digits only. Local Turkish numbers ("0555 ...") are assumed to be +90,
     * the platform's home market.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '90'.substr($digits, 1);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }
}
