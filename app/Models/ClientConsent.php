<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConsent extends Model
{
    use BelongsToCompanyViaClient, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'consent_template_id',
        'visit_id',
        'title',
        'body',
        'sections',
        'signature_path',
        'signed_at',
        'ip_address',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'sections' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
