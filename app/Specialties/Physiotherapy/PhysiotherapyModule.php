<?php

namespace App\Specialties\Physiotherapy;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Physiovaria's v1 (2026-09-27): a small flat treatment catalog and
 * a PhysioSessionCarePlanService (initial evaluation, a 4-week series of
 * therapy sessions with a mid-course re-evaluation, and a final evaluation),
 * via the generic MilestoneCarePlanService. Codes are phy_-prefixed
 * because treatment_catalog codes are unique per company across every
 * specialty (seedCatalog() is updateOrCreate'd on company_id + code).
 */
class PhysiotherapyModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::PHYSIOTHERAPY;
    }

    public function brandName(): string
    {
        return 'Physiovaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * Deliberately small and flat, matching the other specialty catalogs.
     * Prices in Turkish Lira.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'phy_initial_evaluation', 'name_ar' => 'تقييم علاج فيزيائي أولي', 'name_en' => 'Initial Physiotherapy Evaluation', 'name_tr' => 'İlk Fizyoterapi Değerlendirmesi', 'default_price' => 400],
            ['code' => 'phy_therapy_session', 'name_ar' => 'جلسة علاج فيزيائي', 'name_en' => 'Physiotherapy Session', 'name_tr' => 'Fizyoterapi Seansı', 'default_price' => 350],
            ['code' => 'phy_reevaluation', 'name_ar' => 'إعادة تقييم', 'name_en' => 'Re-evaluation', 'name_tr' => 'Ara Değerlendirme', 'default_price' => 300],
            ['code' => 'phy_final_evaluation', 'name_ar' => 'تقييم نهائي', 'name_en' => 'Final Evaluation', 'name_tr' => 'Son Değerlendirme', 'default_price' => 400],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::PHYSIOTHERAPY)->value('id');

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
