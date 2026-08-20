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

    'infobip' => [
        'enabled' => env('INFOBIP_ENABLED', false),
        // Infobip issues a personalized base URL per account (no shared
        // default across customers, unlike Turkey SMS's fixed domain).
        'base_url' => env('INFOBIP_BASE_URL'),
        'api_key' => env('INFOBIP_API_KEY'),
        'sender' => env('INFOBIP_SENDER', 'Dentavaria'),
    ],

    // OTP-generation settings -- independent of which provider actually
    // delivers the code (previously lived under services.turkeysms.*).
    'otp' => [
        'digits' => (int) env('MOBILE_OTP_DIGITS', 6),
        'fixed_code' => env('MOBILE_OTP_FIXED_CODE'),
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
