<?php

// FILES_ROOT: an optional folder OUTSIDE the code (e.g. /home/technova/files)
// holding every uploaded file -- private/ (X-rays, signatures, shared PDFs...)
// and public/ (message attachments...). Keeps uploads out of the deployed /
// WinSCP-synced project tree so a deploy can never overwrite or delete them.
// Unset = the original in-project locations (storage/app/private and
// public/storage). public/migrate.php copies existing files across.
$filesRoot = rtrim((string) env('FILES_ROOT', ''), '/\\');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => $filesRoot !== '' ? $filesRoot.'/private' : storage_path('app/private'),
            // Deliberately false (KVKK/veri güvenliği): this disk holds X-ray
            // images, consent signatures, and expense attachments. Laravel's
            // auto "serve" route (GET+PUT /storage/{path}) has no auth check
            // of its own, which would make every file on this disk directly
            // fetchable by URL. Access instead goes exclusively through the
            // signed, resource-specific routes in routes/api.php (e.g.
            // xray-images.file) so each file is scoped to the record + tenant
            // it belongs to.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            // Points directly at public_path('storage') instead of the conventional
            // storage_path('app/public') + storage:link symlink. Some shared hosts
            // disable both symlink() and exec(), which makes `storage:link` fail
            // unconditionally (Laravel's Filesystem::link() falls back to exec('ln -s')
            // when symlink() is missing, and that call itself fails the same way).
            // Writing straight into the web root sidesteps the need for a link at all.
            //
            // With FILES_ROOT set, this folder sits outside the web root, so
            // its files are served by the app's /files/{path} route
            // (PublicFileController) instead of directly by the web server.
            'root' => $filesRoot !== '' ? $filesRoot.'/public' : public_path('storage'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').($filesRoot !== '' ? '/files' : '/storage'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
