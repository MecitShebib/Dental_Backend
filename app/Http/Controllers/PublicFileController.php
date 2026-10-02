<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves the public disk (message attachments, ...) at /files/{path} when
 * FILES_ROOT moves it outside the web root (see config/filesystems.php) --
 * the web server can't reach it directly there, and this host allows no
 * symlinks. Only the public disk; private files keep their own signed,
 * record-scoped routes.
 */
class PublicFileController extends Controller
{
    public function show(string $path)
    {
        // Reject traversal outright rather than relying on normalisation.
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
