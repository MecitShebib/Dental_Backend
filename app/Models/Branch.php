<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'address',
        'phone',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Which specialties this branch operates -- no rows at all means
     * unrestricted (offered from every specialty), see the branch_specialty
     * migration's docblock.
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class);
    }
}
