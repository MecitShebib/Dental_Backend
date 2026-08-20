<?php

namespace App\Specialties\Cosmetic;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Estevaria's v1 prototype (2026-08-17): a real treatment catalog, a
 * CosmeticCarePlanService (a consultation plus an N-session treatment
 * package, spaced at a chosen interval, via the generic
 * MilestoneCarePlanService), and a real frontend entry point
 * (CosmeticCarePlanModal on Client Details, gated by canAccessCosmetic) all
 * exist and are tested.
 */
class CosmeticModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::COSMETIC;
    }

    public function brandName(): string
    {
        return 'Estevaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * The three package-able treatment types CosmeticCarePlanService offers,
     * plus the one-off consultation every package starts with. Prices in
     * Turkish Lira, same scale as the other specialty catalogs.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'cosmetic_consultation', 'name_ar' => 'استشارة تجميلية', 'name_en' => 'Cosmetic Consultation', 'name_tr' => 'Estetik Konsültasyon', 'default_price' => 300],
            ['code' => 'laser_session', 'name_ar' => 'جلسة ليزر', 'name_en' => 'Laser Session', 'name_tr' => 'Lazer Seansı', 'default_price' => 800],
            ['code' => 'botox_session', 'name_ar' => 'جلسة بوتوكس', 'name_en' => 'Botox Session', 'name_tr' => 'Botoks Seansı', 'default_price' => 1500],
            ['code' => 'filler_session', 'name_ar' => 'جلسة فيلر', 'name_en' => 'Filler Session', 'name_tr' => 'Dolgu Seansı', 'default_price' => 2000],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::COSMETIC)->value('id');

        foreach ($this->catalogItems() as $index => $item) {
            TreatmentCatalog::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $item['code']],
                [
                    ...$item,
                    'company_id' => $company->id,
                    'specialty_id' => $specialtyId,
                    'scope' => TreatmentCatalog::SCOPE_COMPANY,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
