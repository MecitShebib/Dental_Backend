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
            'version' => optional($this->whenLoaded('company'))->version,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->phone,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'branch_name' => $this->branch_name,
            'status' => $this->status?->value ?? $this->status,
            'is_project_admin' => $this->is_project_admin,
            'is_doctor' => $this->is_doctor,
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
