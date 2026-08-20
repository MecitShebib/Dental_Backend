<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmIntegration extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'crm_integrations';

    protected $fillable = [
        'uuid',
        'company_id',
        'provider',
        'client_id',
        'client_secret',
        'refresh_token',
        'accounts_base_url',
        'api_base_url',
        'access_token',
        'access_token_expires_at',
        'status',
        'connected_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
