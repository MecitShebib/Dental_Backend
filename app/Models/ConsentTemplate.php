<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentTemplate extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    /** Pre-existing treatment consent (signature pad for a procedure). */
    public const KIND_CLINICAL = 'clinical';

    /** KVKK m.10 Aydınlatma Metni -- informational, patient acknowledges. */
    public const KIND_KVKK_DISCLOSURE = 'kvkk_disclosure';

    /** KVKK m.6/2 Açık Rıza Beyanı -- required before AI/cross-border features. */
    public const KIND_KVKK_EXPLICIT_CONSENT = 'kvkk_explicit_consent';

    protected $fillable = [
        'uuid',
        'company_id',
        'title',
        'kind',
        'body',
        'sections',
        'language',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sections' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function clientConsents(): HasMany
    {
        return $this->hasMany(ClientConsent::class);
    }
}
