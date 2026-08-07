<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ApiTokenResource extends JsonResource
{
    /**
     * Sanctum's `personal_access_tokens.name` is shared with login-session
     * tokens (see AuthController::verifyLoginOtp, named 'api-token') -- this
     * prefix is how integration tokens created from Settings are told apart
     * from those, both when querying (ApiTokenController) and when display
     * names are rendered here.
     */
    public const PREFIX = 'integration:';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => Str::after($this->name, self::PREFIX),
            'created_at' => $this->created_at,
            'last_used_at' => $this->last_used_at,
        ];
    }
}
