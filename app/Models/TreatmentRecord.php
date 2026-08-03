<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentRecord extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'treatment_plan',
        'currency_code',
        'total_services_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_services_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(TreatmentRecordTooth::class);
    }
}
