<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')->everyTenMinutes();
Schedule::command('patients:send-recalls')->daily();

// This host has no persistent process (no SSH), so `php artisan queue:work`
// can never run as a long-lived worker -- every ShouldQueue job (AI X-ray
// analysis, CRM push, satisfaction surveys, reminders/recalls above) would
// otherwise sit in the `jobs` table forever. --stop-when-empty exits once
// drained instead of blocking past this minute's slot; withoutOverlapping()
// guards against a slow run still executing when the next minute ticks.
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50'])
    ->everyMinute()
    ->withoutOverlapping();

// KVKK saklama/imha politikası -- see PurgeExpiredPersonalData for what
// this actually deletes/anonymizes.
Schedule::command('kvkk:purge')->daily()->at('01:00');

// KVKK veri güvenliği monitoring -- see DetectAnomalousAccess.
Schedule::command('kvkk:detect-anomalous-access')->hourly();

Schedule::command('backup:clean')->daily()->at('01:30');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('backup:monitor')->daily()->at('03:00');
