<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kvkk' => [
        // TEMPORARY (2026-09-12, user request): flips RequiresKvkkConsent
        // and AnalyzeXrayImageJob's matching guard from "enforce" to
        // "log and allow" while set to false. Every AI request that would
        // otherwise have been blocked while this is off is a real,
        // live KVKK m.9 compliance gap (patient health data goes to OpenAI,
        // a US processor, without the patient's required explicit consent)
        // -- this is a knowingly-accepted short-term risk, not a fix. Set
        // KVKK_AI_CONSENT_REQUIRED=true (or remove the env line) to
        // re-enable; no code changes needed to turn it back on.
        'ai_consent_required' => env('KVKK_AI_CONSENT_REQUIRED', true),
    ],

    'admin_login' => [
        // Second factor on every admin-panel login (Admin\AuthController) --
        // the OTP always goes here, never to the logging-in admin's own
        // address, and the login/OTP screens never show or ask for it.
        // Whoever holds this inbox is the real gatekeeper for admin access.
        'otp_email' => env('ADMIN_LOGIN_OTP_EMAIL', 'info@doctovaria.com.tr'),
    ],

    'support' => [
        // Platform (Doctovaria) support inbox + contact details shown on the
        // in-app Help & Support page; the page's request form emails SUPPORT_EMAIL.
        'email' => env('SUPPORT_EMAIL', 'hello@doctovaria.com'),
        'phone' => env('SUPPORT_PHONE'),
        'whatsapp' => env('SUPPORT_WHATSAPP'),
        'ai_token_price_per_million' => env('AI_TOKEN_PRICE_PER_MILLION', '$20'),
    ],

    // Identity printed on /privacy-policy and /terms-of-service (see
    // App\Support\LegalContent). KVKK m.10 expects the data controller's
    // identity on the privacy notice -- fill in the legal entity's real
    // title/address/MERSİS/KEP here; unset lines are simply omitted.
    'legal' => [
        'entity_name' => env('LEGAL_ENTITY_NAME', 'Doctovaria'),
        'entity_address' => env('LEGAL_ENTITY_ADDRESS'),
        'entity_mersis' => env('LEGAL_ENTITY_MERSIS'),
        'entity_kep' => env('LEGAL_ENTITY_KEP'),
        'privacy_email' => env('PRIVACY_EMAIL', env('SUPPORT_EMAIL', 'hello@doctovaria.com')),
    ],

    'iletimerkezi' => [
        'enabled' => env('ILETIMERKEZI_ENABLED', false),
        // İleti Merkezi (Turkey-domiciled) rather than a foreign provider
        // (previously Infobip, Croatia) -- this keeps OTP/reminder SMS a
        // domestic KVKK m.8 transfer instead of a cross-border m.9 one.
        // Fixed endpoint (see IletiMerkeziSmsService), no base_url needed.
        'api_key' => env('ILETIMERKEZI_API_KEY'),
        'api_hash' => env('ILETIMERKEZI_API_HASH'),
        'sender' => env('ILETIMERKEZI_SENDER', 'Dentavaria'),
    ],

    // Days a login (SPA/mobile) token stays valid before the user has to sign
    // in again. Integration tokens (Settings > API Token) don't expire.
    'auth' => [
        'login_token_days' => (int) env('LOGIN_TOKEN_DAYS', 30),
    ],

    // OTP-generation settings -- independent of which provider actually
    // delivers the code (previously lived under services.turkeysms.*).
    'otp' => [
        'digits' => (int) env('MOBILE_OTP_DIGITS', 6),
        'fixed_code' => env('MOBILE_OTP_FIXED_CODE'),
        // "sms" (default, via IletiMerkeziSmsService) or "email" (to the
        // user's own User::$email, via OtpCodeMail) -- covers both the login
        // and forgot-password OTP flows, since both go through
        // MobileOtpService::issue(). Switching to "email" also requires
        // MAIL_MAILER etc. to be configured with a real, working mailer:
        // with the default log driver, OTP emails are only written to the
        // log, never actually delivered.
        'channel' => env('MOBILE_OTP_CHANNEL', 'sms') === 'email' ? 'email' : 'sms',
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

    'patient_recall' => [
        'default_interval_days' => (int) env('PATIENT_RECALL_DEFAULT_INTERVAL_DAYS', 180),
    ],

    'whatsapp' => [
        // Meta WhatsApp Cloud API. Each company brings its own access token
        // and phone_number_id (see WhatsAppSettingsController) -- this is
        // just the shared API endpoint, not a credential.
        'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com/v20.0'),
    ],

    'zoho_crm' => [
        // Zoho accounts/API domains vary by data center (.com, .eu, .in...).
        // Each company sets its own via the CRM settings endpoint; these are
        // just the defaults offered in that form.
        'accounts_base_url' => env('ZOHO_ACCOUNTS_BASE_URL', 'https://accounts.zoho.com'),
        'api_base_url' => env('ZOHO_API_BASE_URL', 'https://www.zohoapis.com'),
    ],

];
