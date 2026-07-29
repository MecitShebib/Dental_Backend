<?php

use Illuminate\Contracts\Console\Kernel;

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function setupFixPermissions(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    @chmod($path, 0775);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        @chmod($item->getPathname(), $item->isDir() ? 0775 : 0664);
    }
}

/**
 * Runs one setup step in isolation so a failure here doesn't stop the steps
 * after it from running (this is the difference between "one broken thing"
 * and "the whole setup silently stops halfway through").
 */
function setupStep(string $label, Closure $step): void
{
    echo "<strong>{$label}</strong><br>";

    try {
        $step();
    } catch (Throwable $e) {
        echo "<pre style=\"color:red\">{$e->getMessage()}\n\n{$e->getTraceAsString()}</pre>";
    }

    echo '<br>';
}

echo 'Starting setup...<br><br>';

setupStep('Fixing storage/bootstrap-cache permissions...', function () {
    setupFixPermissions(__DIR__.'/../storage');
    setupFixPermissions(__DIR__.'/../bootstrap/cache');
    echo 'Permissions fixed.';
});

setupStep('Running migrations + seeders...', function () use ($kernel) {
    $kernel->call('migrate:fresh', [
        '--force' => true,
        '--seed' => true,
    ]);
    echo nl2br($kernel->output());
});

setupStep('Preparing public/storage (no symlink needed)...', function () {
    // config/filesystems.php's "public" disk now points its root directly at
    // public_path('storage') instead of storage_path('app/public'), so files
    // written via Storage::disk('public') land straight in the web root --
    // no storage:link/symlink() required. This host disables both symlink()
    // and exec(), which is exactly what made the old storage:link step fail.
    $publicStoragePath = __DIR__.'/storage';

    if (! is_dir($publicStoragePath)) {
        mkdir($publicStoragePath, 0775, true);
    }

    echo is_dir($publicStoragePath)
        ? 'public/storage is ready.'
        : 'Could not create public/storage -- check permissions on public/.';
});

setupStep('Clearing and rebuilding cache...', function () use ($kernel) {
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

echo '<hr><strong>Health checks</strong><br>';

if (file_exists(__DIR__.'/app/index.html')) {
    echo '&#9989; Frontend build found at public/app/index.html<br>';
} else {
    echo "&#9888;&#65039; public/app/index.html NOT found. The React app (Dental_FrontEnd) hasn't been placed yet: "
        .'run <code>npm run build</code> in Dental_FrontEnd/app/frontend, then upload everything inside its '
        ."<code>dist/</code> folder into this Laravel project's <code>public/app/</code> folder.<br>";
}

if (is_dir(__DIR__.'/storage') && is_writable(__DIR__.'/storage')) {
    echo '&#9989; public/storage exists and is writable<br>';
} else {
    echo '&#9888;&#65039; public/storage is missing or not writable &mdash; uploaded images '
        .'(odontogram plans, etc.) will fail to save.<br>';
}

if (config('app.env') === 'production' && config('app.debug') === false) {
    echo '&#9989; APP_ENV=production and APP_DEBUG=false<br>';
} else {
    echo '&#9888;&#65039; APP_ENV/APP_DEBUG are not set for production in .env &mdash; leaving debug mode on '
        .'publicly exposes stack traces (file paths, DB credentials, etc.) to any visitor who triggers an error.<br>';
}

echo '<hr><strong>DONE.</strong><br>';
echo '<strong style="color:red">Delete this file (public/setup.php) now, or password-protect it.</strong> '
    .'It re-runs migrate:fresh (wipes and re-seeds the whole database) with no authentication &mdash; '
    .'anyone who finds this URL can erase your production data.';
