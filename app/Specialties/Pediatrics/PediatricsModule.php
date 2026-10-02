<?php

namespace App\Specialties\Pediatrics;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Pediavaria's v1 (2026-09-27): a small flat treatment catalog and
 * a WellChildCarePlanService (well-child / vaccination follow-up timeline
 * anchored to the child's 1st-month visit),
 * via the generic MilestoneCarePlanService. Codes are ped_-prefixed
 * because treatment_catalog codes are unique per company across every
 * specialty (seedCatalog() is updateOrCreate'd on company_id + code).
 */
class PediatricsModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::PEDIATRICS;
    }

    public function brandName(): string
    {
        return 'Pediavaria';
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
            ['code' => 'ped_well_child_visit', 'name_ar' => 'فحص متابعة الطفل', 'name_en' => 'Well-Child Visit', 'name_tr' => 'Çocuk İzlem Muayenesi', 'default_price' => 400],
            ['code' => 'ped_vaccination_visit', 'name_ar' => 'زيارة تطعيم', 'name_en' => 'Vaccination Visit', 'name_tr' => 'Aşı Uygulaması', 'default_price' => 250],
            ['code' => 'ped_growth_assessment', 'name_ar' => 'تقييم النمو والتطور', 'name_en' => 'Growth & Development Assessment', 'name_tr' => 'Büyüme-Gelişim Değerlendirmesi', 'default_price' => 300],
            ['code' => 'ped_sick_visit', 'name_ar' => 'فحص طفل مريض', 'name_en' => 'Sick Child Visit', 'name_tr' => 'Hasta Çocuk Muayenesi', 'default_price' => 450],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::PEDIATRICS)->value('id');

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
