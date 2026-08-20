<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'phone_number',
        'direction',
        'status',
        'duration_seconds',
        'recording_url',
        'external_id',
        'occurred_at',
        'notes',
        'followed_up_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'occurred_at' => 'datetime',
            'followed_up_at' => 'datetime',
        ];
    }

    public function needsFollowUp(): bool
    {
        return $this->status === 'missed' && $this->followed_up_at === null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
