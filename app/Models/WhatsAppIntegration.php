<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppIntegration extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_integrations';

    protected $fillable = [
        'uuid',
        'company_id',
        'access_token',
        'phone_number_id',
        'business_account_id',
        'status',
        'connected_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            // Laravel's built-in Eloquent cast; encrypted/decrypted via APP_KEY
            // automatically on save/read, so it's never stored in plaintext.
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
