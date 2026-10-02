<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A patient document PDF shared over WhatsApp as a link (/d/{uuid}). The
 * random UUID is the link's secret -- it's generated in the browser so the
 * WhatsApp chat can open instantly, before the upload finishes. Stored on
 * the private disk; deleted after LIFETIME_DAYS.
 */
class SharedDocument extends Model
{
    public const LIFETIME_DAYS = 30;

    public const DISK = 'local';

    protected $fillable = ['uuid', 'company_id', 'client_id', 'created_by', 'title', 'filename', 'path', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (SharedDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Deletes every expired document (row + file). Returns how many. */
    public static function purgeExpired(): int
    {
        $count = 0;
        static::query()->where('expires_at', '<', now())->each(function (SharedDocument $document) use (&$count) {
            $document->delete();
            $count++;
        });

        return $count;
    }
}
