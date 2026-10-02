<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A clinic-defined group of ready-made WhatsApp messages (e.g. "Diabetes
 * patients"), picked from the "Send WhatsApp message" popup.
 */
class MessageGroup extends Model
{
    use BelongsToCompany, HasUuid;

    protected $fillable = ['company_id', 'specialty_id', 'name'];

    public function messages(): HasMany
    {
        return $this->hasMany(CustomMessage::class)->orderBy('title');
    }
}
