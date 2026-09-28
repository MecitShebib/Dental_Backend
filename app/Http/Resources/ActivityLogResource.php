<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'action' => $this->action,
            'category' => AuditLog::categoryFor($this->auditable_type),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'is_doctor' => (bool) $this->user->is_doctor,
                'role' => $this->user->roles->first()?->name,
            ] : null),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null),
            'subject_label' => $this->meta['subject_label'] ?? null,
            'changed_fields' => $this->meta['changed_fields'] ?? null,
            'ip_address' => $this->ip_address,
        ];
    }
}
