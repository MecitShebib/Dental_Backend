<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'visit_id',
        'token',
        'rating',
        'wait_time_rating',
        'staff_rating',
        'cleanliness_rating',
        'comment',
        'invite_sent_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'wait_time_rating' => 'integer',
            'staff_rating' => 'integer',
            'cleanliness_rating' => 'integer',
            'invite_sent_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function isNegative(): bool
    {
        return $this->rating !== null && $this->rating <= 2;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SatisfactionSurvey $survey): void {
            if (empty($survey->token)) {
                $survey->token = Str::random(32);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
