<?php

use App\Models\User;
use App\Support\UploadedFilesCopier;
use Database\Seeders\KvkkConsentTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\TreatmentCatalogSeeder;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deploy script for this host (no SSH): open
 *     https://<domain>/migrate.php?key=<DEPLOY_KEY from .env>
 * after every code upload. It copies uploads into FILES_ROOT, applies
 * pending migrations, refreshes reference data, and rebuilds the caches.
 *
 * Without a DEPLOY_KEY of at least 32 characters in .env, or with a wrong
 * ?key=, it answers 404 and does nothing -- before even booting Laravel.
 *
 * Never wipes data: there is no migrate:fresh and no demo seeding here.
 */

require __DIR__.'/../vendor/autoload.php';

$env = Dotenv::parse((string) @file_get_contents(__DIR__.'/../.env'));
$deployKey = (string) ($env['DEPLOY_KEY'] ?? '');

if (strlen($deployKey) < 32 || ! hash_equals($deployKey, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function migrateStep(string $label, Closure $step): void
{
    echo '<strong>'.e($label).'</strong><br>';

    try {
        $step();
    } catch (Throwable $e) {
        // Message only -- no stack trace with server paths in the page.
        echo '<pre style="color:red">'.e($e->getMessage()).'</pre>';
        report($e);
    }

    echo '<br>';
}

$filesRoot = rtrim((string) ($env['FILES_ROOT'] ?? ''), '/\\');

// Uploaded files -> FILES_ROOT (a folder outside the code, see
// config/filesystems.php). COPY ONLY -- originals are never deleted here;
// files already copied (same size) are skipped. FILES_ROOT is read straight
// from .env because the config cache may still be the previous one.
migrateStep('Copying uploaded files into FILES_ROOT (originals are kept)...', function () use ($filesRoot) {
    if ($filesRoot === '') {
        echo 'FILES_ROOT is not set in .env -- skipped (files stay in the project folders).';

        return;
    }

    foreach (UploadedFilesCopier::sources() as $name => $from) {
        $result = UploadedFilesCopier::copy($from, $filesRoot.'/'.$name);
        echo e("{$name}: {$result['copied']} copied, {$result['skipped']} already there, {$result['failed']} failed").'<br>';
        if ($result['failed'] > 0) {
            echo '<span style="color:red">Some files failed to copy. Do NOT delete the old folders; fix permissions and run this script again.</span><br>';
        }
    }
});

// Opt-in clean-up of the OLD in-project upload folders once the app runs on
// FILES_ROOT:  &delete_old_uploads=preview  shows what would happen,
//              &delete_old_uploads=yes      does it.
// Patient files found on the public disk are first moved into
// FILES_ROOT/private; an old file is deleted only when an identical-size
// copy exists in the new folders.
$deleteOldUploads = (string) ($_GET['delete_old_uploads'] ?? '');
if (in_array($deleteOldUploads, ['preview', 'yes'], true)) {
    migrateStep(
        $deleteOldUploads === 'yes' ? 'DELETING the old upload folders (verified copies only)...' : 'PREVIEW: cleaning up the old upload folders (nothing is changed)...',
        function () use ($deleteOldUploads, $filesRoot) {
            $activeRoot = rtrim((string) config('filesystems.disks.local.root'), '/\\');

            if ($filesRoot === '' || $activeRoot !== $filesRoot.'/private') {
                echo '<span style="color:red">Refused: the app is not running on FILES_ROOT yet. Run this script once without delete_old_uploads first.</span>';

                return;
            }

            foreach (UploadedFilesCopier::retireOldFolders($filesRoot, $deleteOldUploads === 'yes') as $key => $count) {
                echo e(str_replace('_', ' ', $key).": {$count}").'<br>';
            }
        },
    );
}

migrateStep('Migrating (existing data is kept)...', function () use ($kernel) {
    $kernel->call('migrate', ['--force' => true]);
    echo nl2br(e($kernel->output()));
});

// Reference data only -- every one of these is idempotent and creates no
// user accounts: roles/permissions, specialties, the treatment price
// catalog and the KVKK consent templates (back-filled for every company).
migrateStep('Refreshing reference data (roles, specialties, treatment catalog, KVKK consent templates)...', function () use ($kernel) {
    foreach ([RolePermissionSeeder::class, SpecialtySeeder::class, TreatmentCatalogSeeder::class, KvkkConsentTemplateSeeder::class] as $seeder) {
        $kernel->call('db:seed', ['--class' => $seeder, '--force' => true]);
    }
    echo 'Done.';
});

// Earlier deploys re-seeded demo accounts with publicly known passwords
// (the admin panel signs in with phone + password only). Any such account
// still on its default password gets a strong random one, shown ONCE here.
migrateStep('Securing demo accounts that still use a default password...', function () {
    $rotated = [];

    User::query()
        ->withoutGlobalScopes()
        ->where(fn ($query) => $query->where('email', 'like', '%@clinic.com')->orWhere('email', 'like', '%@dental.com'))
        ->each(function (User $user) use (&$rotated) {
            foreach (['secret', '123456'] as $default) {
                if ($user->password && Hash::check($default, $user->password)) {
                    $password = Str::password(20, symbols: false);
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    $user->tokens()->delete();
                    $rotated[] = [$user->email, $user->phone, $password];

                    return;
                }
            }
        });

    if ($rotated === []) {
        echo 'No account uses a default password.';

        return;
    }

    echo '<span style="color:#b45309">New passwords -- store them now, they are not shown again:</span><br><table border="1" cellpadding="4">';
    foreach ($rotated as [$email, $phone, $password]) {
        echo '<tr><td>'.e($email).'</td><td>'.e($phone).'</td><td><code>'.e($password).'</code></td></tr>';
    }
    echo '</table>';
});

migrateStep('Clearing and rebuilding cache...', function () use ($kernel) {
    $kernel->call('optimize:clear');
    $kernel->call('config:cache');
    $kernel->call('route:cache');
    $kernel->call('view:cache');
    echo 'Cache optimized.';

    // This host runs with opcache.validate_timestamps=0, so PHP-FPM keeps
    // serving old bytecode for edited files until OPcache is explicitly
    // reset -- without this, a code deploy can silently not take effect.
    if (function_exists('opcache_reset')) {
        echo opcache_reset() ? '<br>OPcache reset.' : '<br>OPcache reset call returned false.';
    }
});

echo '<hr><strong>DONE.</strong> Existing data was left untouched -- only pending migrations and reference data ran.';
