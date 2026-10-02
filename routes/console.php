<?php

use App\Http\Controllers\Admin\SystemStatusController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every task runs IN-PROCESS (Schedule::call + Artisan::call) rather than via
// Schedule::command(): the production host disables proc_open, and
// Schedule::command() launches each task as a child process through
// Symfony Process, so `schedule:run` failed with "The Process class relies
// on proc_open". The only cron needed is `php artisan schedule:run` every
// minute. Tasks run one after another in the order defined below.
$task = fn (string $command, array $parameters = []) => Schedule::call(fn () => Artisan::call($command, $parameters))
    ->name($command);

// Heartbeat for Admin > System Status ("is the cron running?").
Schedule::call(fn () => Cache::forever(SystemStatusController::SCHEDULER_CACHE_KEY, now()->toIso8601String()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

$task('appointments:send-reminders')->everyTenMinutes();
$task('patients:send-recalls')->daily();

// KVKK saklama/imha politikası -- see PurgeExpiredPersonalData for what
// this actually deletes/anonymizes.
$task('kvkk:purge')->daily()->at('01:00');
$task('documents:purge-expired')->daily()->at('01:15');

// KVKK veri güvenliği monitoring -- see DetectAnomalousAccess.
$task('kvkk:detect-anomalous-access')->hourly();

// NOTE: backup:run dumps the database with `mysqldump`, which itself needs
// proc_open -- it can only succeed once the host allows proc_open for the
// cron's PHP (clean/monitor don't need it).
$task('backup:clean')->daily()->at('01:30');
$task('backup:run')->daily()->at('02:00');
$task('backup:monitor')->daily()->at('03:00');

// Last on purpose, since it can run for up to 50 seconds. This host has no
// persistent process (no SSH), so `php artisan queue:work` can never run as
// a long-lived worker -- every ShouldQueue job (AI X-ray analysis, CRM push,
// satisfaction surveys, reminders/recalls above) would otherwise sit in the
// `jobs` table forever. --stop-when-empty exits once drained instead of
// blocking past this minute's slot; withoutOverlapping() guards against a
// slow run still executing when the next minute ticks.
$task('queue:work', ['--stop-when-empty' => true, '--max-time' => 50])
    ->everyMinute()
    ->withoutOverlapping(10);
