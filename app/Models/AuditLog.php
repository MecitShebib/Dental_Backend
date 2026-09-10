<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who touched (viewed/created/updated/deleted/exported) which personal-data
 * record and when -- the access trail KVKK m.12 ("veri güvenliğine ilişkin
 * yükümlülükler") expects a data controller/processor to be able to produce.
 * Written by the Auditable trait (see app/Models/Concerns/Auditable.php)
 * plus a few explicit calls for read/export actions that have no Eloquent
 * event of their own.
 */
class AuditLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->created_at ??= now();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, $auditable, ?User $actor = null, ?array $meta = null): self
    {
        $request = request();

        return static::create([
            'company_id' => $actor?->company_id ?? $auditable->company_id ?? null,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta,
        ]);
    }
}
