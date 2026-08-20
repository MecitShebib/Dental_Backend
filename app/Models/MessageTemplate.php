<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'key',
        'channel',
        'language',
        'subject',
        'body',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
