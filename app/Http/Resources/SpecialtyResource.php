<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialtyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'brand_name' => $this->brand_name,
            'name' => $this->name_en ?? $this->name_ar ?? $this->name_tr,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'name_tr' => $this->name_tr,
            'icon' => $this->icon,
            // Specialty::is_active: an admin can toggle a specialty on for
            // subscriptions in principle. is_built: whether real software
            // exists behind it (see SpecialtyModule::isBuilt()) -- kept
            // separate so the launcher can tell "not offered" from "offered
            // but not ready yet" apart.
            'is_active' => (bool) $this->is_active,
            // Both of the below are set by the controller via
            // setAttribute() (same pattern as Company::active_users_count in
            // Api\CompanyController::show()) -- neither is a real column.
            'is_built' => (bool) ($this->is_built ?? false),
            'is_subscribed' => (bool) ($this->is_subscribed ?? false),
        ];
    }
}
