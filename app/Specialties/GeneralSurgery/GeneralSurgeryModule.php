<?php

namespace App\Specialties\GeneralSurgery;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Surgivaria's v1 (2026-09-27): a small flat treatment catalog and
 * a PerioperativeCarePlanService (pre-op evaluation, surgery, wound care,
 * post-op control),
 * via the generic MilestoneCarePlanService. Codes are gs_-prefixed
 * because treatment_catalog codes are unique per company across every
 * specialty (seedCatalog() is updateOrCreate'd on company_id + code).
 */
class GeneralSurgeryModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::GENERAL_SURGERY;
    }

    public function brandName(): string
    {
        return 'Surgivaria';
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
            ['code' => 'gs_preop_evaluation', 'name_ar' => 'تقييم ما قبل العملية', 'name_en' => 'Pre-operative Evaluation', 'name_tr' => 'Ameliyat Öncesi Değerlendirme', 'default_price' => 600],
            ['code' => 'gs_surgery', 'name_ar' => 'إجراء جراحي', 'name_en' => 'Surgical Procedure', 'name_tr' => 'Cerrahi İşlem', 'default_price' => 15000],
            ['code' => 'gs_wound_care', 'name_ar' => 'العناية بالجرح وإزالة الغرز', 'name_en' => 'Wound Care & Suture Removal', 'name_tr' => 'Pansuman ve Dikiş Alımı', 'default_price' => 300],
            ['code' => 'gs_postop_control', 'name_ar' => 'متابعة ما بعد العملية', 'name_en' => 'Post-operative Control', 'name_tr' => 'Ameliyat Sonrası Kontrol', 'default_price' => 450],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::GENERAL_SURGERY)->value('id');

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
