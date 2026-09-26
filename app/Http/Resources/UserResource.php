<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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
            // Only meaningful for a doctor -- the AI assistant is never
            // shown to anyone else regardless of this flag.
            'ai_enabled' => $this->ai_enabled,
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
            // Global (not per-company) env-driven toggles the frontend needs
            // on every render -- baked into this payload rather than a
            // separate endpoint since it's the one response already fetched
            // once at bootstrap and persisted into authUser. See
            // config/features.php.
            'feature_flags' => [
                'three_d_odontogram_enabled' => (bool) config('features.three_d_odontogram'),
            ],
            'notes' => $this->notes,
            // A doctor's own signature/stamp image, pasted onto their
            // generated prescriptions (see Settings > Signature & Stamp and
            // PrescriptionResource's doctor_signature_url/doctor_stamp_url).
            // Signed rather than a plain Storage URL, same private-disk
            // convention as X-ray images; null until the doctor saves one.
            'signature_url' => $this->signature_path
                ? URL::temporarySignedRoute('users.signature-file', now()->addMinutes(60), ['user' => $this->id])
                : null,
            'stamp_url' => $this->stamp_path
                ? URL::temporarySignedRoute('users.stamp-file', now()->addMinutes(60), ['user' => $this->id])
                : null,
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
