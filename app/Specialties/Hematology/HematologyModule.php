<?php

namespace App\Specialties\Hematology;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Hemavaria's v1 (2026-09-27): a small flat treatment catalog and
 * a BloodCountCarePlanService (consultation, periodic CBC controls over
 * twelve weeks, and a treatment evaluation),
 * via the generic MilestoneCarePlanService. Codes are hem_-prefixed
 * because treatment_catalog codes are unique per company across every
 * specialty (seedCatalog() is updateOrCreate'd on company_id + code).
 */
class HematologyModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::HEMATOLOGY;
    }

    public function brandName(): string
    {
        return 'Hemavaria';
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
            ['code' => 'hem_consultation', 'name_ar' => 'استشارة أمراض الدم', 'name_en' => 'Hematology Consultation', 'name_tr' => 'Hematoloji Muayenesi', 'default_price' => 600],
            ['code' => 'hem_cbc_control', 'name_ar' => 'فحص تعداد الدم', 'name_en' => 'CBC Control', 'name_tr' => 'Hemogram Kontrolü', 'default_price' => 250],
            ['code' => 'hem_iron_infusion', 'name_ar' => 'تسريب حديد وريدي', 'name_en' => 'IV Iron Infusion', 'name_tr' => 'Damar Yolu Demir Tedavisi', 'default_price' => 900],
            ['code' => 'hem_evaluation', 'name_ar' => 'تقييم العلاج', 'name_en' => 'Treatment Evaluation', 'name_tr' => 'Tedavi Değerlendirmesi', 'default_price' => 450],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::HEMATOLOGY)->value('id');

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
