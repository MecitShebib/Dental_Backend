<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'company_name' => optional($this->whenLoaded('company'))->name,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->phone,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            // Prefers the real Branch relation's name (set via the branch_id
            // picklist) over the legacy free-text branch_name column, which
            // predates the proper Branch model and is only still populated on
            // older records saved before that column existed.
            'branch_name' => $this->branch?->name ?? $this->branch_name,
            'branch_id' => $this->branch_id,
            'status' => $this->status?->value ?? $this->status,
            'is_project_admin' => $this->is_project_admin,
            'is_doctor' => $this->is_doctor,
            // Set for a doctor (one specialty); null for staff, who work
            // across every specialty the company subscribes to -- see
            // Specialty/Sidebar's launcher-routing use of this.
            'specialty_id' => $this->specialty_id,
            'specialty_key' => $this->whenLoaded('specialty', fn () => $this->specialty?->key),
            // Set via setAttribute() by AuthController before serializing
            // (see requiresSpecialtySelection()) -- not a real column. Part
            // of the user payload rather than a sibling response field so it
            // survives into the persisted authUser on the frontend and
            // AppLayout can redirect correctly on every render, not just
            // the one right after login.
            'requires_specialty_selection' => (bool) ($this->requires_specialty_selection ?? false),
            'notes' => $this->notes,
            'last_login_at' => optional($this->last_login_at)->toDateTimeString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(), []),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
            ])->values(), []),
        ];
    }
}
