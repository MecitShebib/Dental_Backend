<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentCharge extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI_PLAN = 'ai_plan';

    public const SOURCE_VISIT = 'visit';

    public const SOURCE_APPOINTMENT = 'appointment';

    protected $fillable = [
        'uuid',
        'client_id',
        'source_type',
        'source_id',
        'amount',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
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
