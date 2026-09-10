<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An OTP challenge for the unauthenticated /book/{company} page. Mirrors
 * UserOtp's shape (see app/Models/UserOtp.php) but keyed by Company + a bare
 * phone number instead of a User -- there's no account involved in public
 * booking, so this can't reuse UserOtp's user_id foreign key.
 */
class PublicBookingOtp extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'company_id',
        'mobile',
        'otp_code',
        'attempts',
        'reference',
        'expires_at',
        'verified_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }
}
