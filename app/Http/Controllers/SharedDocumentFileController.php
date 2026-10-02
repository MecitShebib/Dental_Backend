<?php

namespace App\Http\Controllers;

use App\Models\SharedDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Public link sent to the patient over WhatsApp (/d/{uuid}). The random
 * UUID is the secret; the file is gone after SharedDocument::LIFETIME_DAYS.
 */
class SharedDocumentFileController extends Controller
{
    public function show(string $uuid)
    {
        $document = SharedDocument::query()->where('uuid', strtolower($uuid))->first();

        if ($document && $document->isExpired()) {
            $document->delete();
            $document = null;
        }

        $disk = Storage::disk(SharedDocument::DISK);
        abort_if(! $document || ! $disk->exists($document->path), 404);

        return response()->file($disk->path($document->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"document.pdf\"; filename*=UTF-8''".rawurlencode($document->filename),
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
