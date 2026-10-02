<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One ready-made WhatsApp message inside a MessageGroup: a title plus text
 * and/or an attachment (image or PDF, sent to the patient as a link). The
 * attachment lives on the public disk until the message is deleted or the
 * attachment is replaced/removed.
 */
class CustomMessage extends Model
{
    use BelongsToCompany, HasUuid;

    public const ATTACHMENT_DISK = 'public';

    protected $fillable = ['company_id', 'message_group_id', 'title', 'body', 'system_key', 'language', 'attachment_path', 'attachment_name', 'attachment_mime'];

    protected static function booted(): void
    {
        static::deleting(fn (CustomMessage $message) => $message->deleteAttachmentFile());
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MessageGroup::class, 'message_group_id');
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk(self::ATTACHMENT_DISK)->url($this->attachment_path) : null;
    }

    public function attachmentType(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return str_starts_with((string) $this->attachment_mime, 'image/') ? 'image' : 'pdf';
    }

    public function deleteAttachmentFile(): void
    {
        if ($this->attachment_path) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($this->attachment_path);
        }
    }
}
