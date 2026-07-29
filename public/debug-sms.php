<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

// TEMPORARY diagnostic tool for the Turkey SMS integration. Delete this
// file (or password-protect it) once the issue is found -- it can send a
// real SMS to any number you pass it, with no authentication.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

echo "=== Turkey SMS config (as loaded by Laravel right now) ===\n";
echo 'enabled:       '.var_export(config('services.turkeysms.enabled'), true)."\n";
echo 'base_url:      '.config('services.turkeysms.base_url')."\n";
$apiKey = (string) config('services.turkeysms.api_key');
echo 'api_key:       '.($apiKey !== '' ? substr($apiKey, 0, 6).'...('.strlen($apiKey).' chars)' : '(EMPTY -- this is the bug if so)')."\n";
echo 'title:         '.config('services.turkeysms.title')."\n";
echo 'otp_digits:    '.config('services.turkeysms.otp_digits')."\n\n";

$mobile = $_GET['mobile'] ?? null;

if ($mobile) {
    $normalized = preg_replace('/\D+/', '', trim($mobile));
    $otp = (string) random_int(100000, 999999);
    echo "=== Live test: sending a real SMS to \"{$mobile}\" (normalized: {$normalized}), code {$otp} ===\n";

    try {
        $response = Http::acceptJson()
            ->timeout(15)
            ->post(rtrim((string) config('services.turkeysms.base_url'), '/').'/api/v3/gonder/add-content', [
                'api_key' => $apiKey,
                'sentto' => $normalized,
                'title' => (string) config('services.turkeysms.title', 'ELECMINDS'),
                'text' => "Your Dentavaria verification code is: {$otp}",
            ]);

        echo 'HTTP status: '.$response->status()."\n";
        echo "Raw response body:\n".$response->body()."\n";
    } catch (Throwable $e) {
        echo 'Exception while calling Turkey SMS: '.$e->getMessage()."\n";
    }
} else {
    echo "No live test run. Add ?mobile=905XXXXXXXXX to this URL to actually send a real SMS\n";
    echo "and see Turkey SMS's raw response (this is the fastest way to see their exact error).\n";
}

echo "\n=== Recent Turkey SMS / OTP related log lines (storage/logs/laravel.log) ===\n";
$logPath = storage_path('logs/laravel.log');

if (file_exists($logPath)) {
    $lines = file($logPath);
    $relevant = array_values(array_filter($lines, function ($line) {
        return stripos($line, 'turkey sms') !== false || stripos($line, 'otp') !== false;
    }));
    $relevant = array_slice($relevant, -100);
    echo $relevant ? implode('', $relevant) : "(no matching log lines found)\n";
} else {
    echo "Log file not found at {$logPath}\n";
}
