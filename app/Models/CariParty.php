<?php

namespace App\Models;

use App\Enums\CariPartyType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A manually-managed "cari hesap" counterparty -- a supplier, a contracted
 * institution, a health/insurance agency, a cash register, or a bank account.
 * Doctors/staff and labs are NOT CariParty rows -- their ledgers hang
 * directly off the existing User/LabPartner records instead (see
 * CariTransaction::partyable) so their data is never duplicated.
 */
class CariParty extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'type',
        'name',
        'phone',
        'email',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CariPartyType::class,
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(CariTransaction::class, 'partyable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
