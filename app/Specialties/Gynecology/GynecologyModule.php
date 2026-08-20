<?php

namespace App\Specialties\Gynecology;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Gynevaria's v1 prototype (2026-08-17): a real treatment catalog and a
 * PrenatalCarePlanService (LMP -> trimester-milestone appointments +
 * charges, via the generic CarePlanService) exist and are tested, and now a
 * real frontend entry point exists too -- a "Prenatal Care Plan" action on
 * Client Details (gated by canAccessGynecology), opening
 * PrenatalCarePlanModal.jsx. isBuilt() -> true reflects that this is now a
 * genuinely usable (if narrow -- one workflow, no dedicated pregnancy-
 * timeline view or gynecology-specific navigation yet) feature, not just
 * backend plumbing -- see the memory entry for the full scope note.
 */
class GynecologyModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::GYNECOLOGY;
    }

    public function brandName(): string
    {
        return 'Gynevaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * Deliberately small and flat (no odontogram-style procedure matrix --
     * gynecology has no equivalent structured widget yet), matching exactly
     * the four service types named in the original product spec. Prices in
     * Turkish Lira, same scale as TreatmentCatalogSeeder's dental catalog.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'prenatal_checkup', 'name_ar' => 'فحص ما قبل الولادة', 'name_en' => 'Prenatal Checkup', 'name_tr' => 'Doğum Öncesi Kontrol', 'default_price' => 800],
            ['code' => 'ultrasound', 'name_ar' => 'تصوير بالموجات فوق الصوتية', 'name_en' => 'Ultrasound', 'name_tr' => 'Ultrason', 'default_price' => 1200],
            ['code' => 'delivery_package', 'name_ar' => 'باقة الولادة', 'name_en' => 'Delivery Package', 'name_tr' => 'Doğum Paketi', 'default_price' => 25000],
            ['code' => 'postpartum_checkup', 'name_ar' => 'فحص ما بعد الولادة', 'name_en' => 'Postpartum Checkup', 'name_tr' => 'Lohusalık Kontrolü', 'default_price' => 600],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::GYNECOLOGY)->value('id');

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
