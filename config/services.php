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

    'turkeysms' => [
        'enabled' => env('TURKEYSMS_ENABLED', false),
        'base_url' => env('TURKEYSMS_BASE_URL', 'https://turkeysms.com.tr'),
        'api_key' => env('TURKEYSMS_API_KEY'),
        'otp_digits' => (int) env('TURKEYSMS_OTP_DIGITS', 6),
        'otp_lang' => (int) env('TURKEYSMS_OTP_LANG', 2),
        'report' => (int) env('TURKEYSMS_REPORT', 1),
        'response_type' => env('TURKEYSMS_RESPONSE_TYPE', 'json'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

];
