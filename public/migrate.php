<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

/**
 * Same isolated-step pattern as setup.php, but this script only ever runs
 * `migrate` (applies pending migrations, keeps existing data) -- never
 * `migrate:fresh`. Use this for every deploy after the first one; reserve
 * setup.php for the initial, empty-database setup only.
 */
function migrateStep(string $label, Closure $step): void
{
    echo "<strong>{$label}</strong><br>";

    try {
        $step();
    } catch (Throwable $e) {
        echo "<pre style=\"color:red\">{$e->getMessage()}\n\n{$e->getTraceAsString()}</pre>";
    }

    echo '<br>';
}

echo 'Running pending migrations...<br><br>';

migrateStep('Migrating (existing data is kept)...', function () use ($kernel) {
    $kernel->call('migrate', ['--force' => true]);
    echo nl2br($kernel->output());
});

migrateStep('Running database seeders (roles/permissions, treatment catalog, demo company)...', function () {
    // DatabaseSeeder itself calls RolePermissionSeeder and TreatmentCatalogSeeder,
    // then updateOrCreate's the seeded demo company/admin/doctors/subscription --
    // every step in it is updateOrCreate-based, so this is safe to run repeatedly
    // and matches exactly what setup.php ran on the very first deploy.
    (new DatabaseSeeder)->run();
    echo 'Seeders finished.';
});

migrateStep('Clearing and rebuilding cache...', function () use ($kernel) {
    $kernel->call('optimize:clear');
    echo nl2br($kernel->output());

    $kernel->call('config:cache');
    $kernel->call('route:cache');
    $kernel->call('view:cache');
    echo 'Cache optimized.';

    // This host runs with opcache.validate_timestamps=0, so PHP-FPM keeps
    // serving old bytecode for edited files until OPcache is explicitly
    // reset -- without this, a code deploy can silently not take effect.
    if (function_exists('opcache_reset')) {
        echo opcache_reset() ? '<br>OPcache reset.' : '<br>OPcache reset call returned false.';
    } else {
        echo '<br>opcache_reset() not available in this SAPI.';
    }
});

echo '<hr><strong>DONE.</strong> Existing clients, visits, appointments, and everything else were left untouched -- only new migrations ran.<br>';
echo '<strong style="color:red">Delete this file (public/migrate.php) or password-protect it once you\'re done deploying for the day.</strong> '
    .'It has no authentication and runs migrations on every request.';
