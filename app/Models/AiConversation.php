<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use BelongsToCompanyViaClient, HasUuid;

    /**
     * `language` is the one language ('en'/'ar'/'tr', same vocabulary as
     * clients.preferred_language) this whole thread is conducted in. It is
     * resolved once, when the conversation is first created, and never
     * re-derived per message -- see
     * AiConversationService::resolveConversationLanguage() for why.
     */
    protected $fillable = [
        'uuid',
        'client_id',
        'specialty_id',
        'language',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class)->orderBy('created_at');
    }
}
