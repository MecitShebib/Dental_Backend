<?php

namespace App\Services;

use App\Models\Company;

/**
 * Single point every outbound patient text message (reminders, recalls,
 * booking confirmations) goes through, so the "which channel" decision
 * lives in one place instead of being duplicated at each call site.
 * Prefers a company's own connected WhatsApp number when available (no per-
 * message SMS cost); falls back to the shared Infobip SMS gateway otherwise.
 */
class MessagingService
{
    public function __construct(
        protected InfobipSmsService $infobipSms,
        protected WhatsAppService $whatsApp,
    ) {}

    public function send(Company $company, string $phone, string $text): bool
    {
        if ($this->whatsApp->enabledFor($company)) {
            return $this->whatsApp->send($company, $phone, $text);
        }

        if ($this->infobipSms->enabled()) {
            return $this->infobipSms->send($phone, $text);
        }

        return false;
    }
}
