<?php

namespace App\Specialties\Orthopedics;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Orthovaria's v1 prototype (2026-08-17): a real treatment catalog, a
 * RehabCarePlanService (injury/procedure -> physical-therapy timeline of
 * appointments + charges, via the generic MilestoneCarePlanService), and a
 * real frontend entry point (SimpleCarePlanModal on Client Details, gated by
 * canAccessOrthopedics) all exist and are tested.
 */
class OrthopedicsModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::ORTHOPEDICS;
    }

    public function brandName(): string
    {
        return 'Orthovaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * Deliberately small and flat, matching Gynevaria/Medivaria's catalog
     * style. Prices in Turkish Lira, same scale as the other specialty
     * catalogs.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'ortho_assessment', 'name_ar' => 'تقييم عظمي', 'name_en' => 'Orthopedic Assessment', 'name_tr' => 'Ortopedik Değerlendirme', 'default_price' => 500],
            ['code' => 'physical_therapy_session', 'name_ar' => 'جلسة علاج طبيعي', 'name_en' => 'Physical Therapy Session', 'name_tr' => 'Fizik Tedavi Seansı', 'default_price' => 300],
            ['code' => 'followup_xray', 'name_ar' => 'أشعة سينية للمتابعة', 'name_en' => 'Follow-up X-Ray', 'name_tr' => 'Takip Röntgeni', 'default_price' => 400],
            ['code' => 'final_assessment', 'name_ar' => 'تقييم نهائي', 'name_en' => 'Final Rehab Assessment', 'name_tr' => 'Son Rehabilitasyon Değerlendirmesi', 'default_price' => 450],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::ORTHOPEDICS)->value('id');

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
