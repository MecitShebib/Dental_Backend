<?php

namespace App\Specialties\InternalMedicine;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Medivaria's v1 prototype (2026-08-17): a real treatment catalog, a
 * ChronicCarePlanService (chronic-disease follow-up schedule -> appointments
 * + charges, via the generic MilestoneCarePlanService), and a real frontend
 * entry point (SimpleCarePlanModal on Client Details, gated by
 * canAccessInternalMedicine) all exist and are tested.
 */
class InternalMedicineModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::INTERNAL_MEDICINE;
    }

    public function brandName(): string
    {
        return 'Medivaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * Deliberately small and flat, matching Gynevaria's catalog style --
     * internal medicine has no equivalent of the odontogram's structured
     * procedure matrix. Prices in Turkish Lira, same scale as the other
     * specialty catalogs.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'chronic_initial_assessment', 'name_ar' => 'تقييم أولي لمرض مزمن', 'name_en' => 'Initial Chronic Disease Assessment', 'name_tr' => 'Kronik Hastalık İlk Değerlendirmesi', 'default_price' => 600],
            ['code' => 'chronic_followup_visit', 'name_ar' => 'زيارة متابعة', 'name_en' => 'Follow-up Visit', 'name_tr' => 'Takip Ziyareti', 'default_price' => 350],
            ['code' => 'lab_panel', 'name_ar' => 'فحوصات مخبرية', 'name_en' => 'Lab Panel', 'name_tr' => 'Laboratuvar Paneli', 'default_price' => 450],
            ['code' => 'vital_signs_check', 'name_ar' => 'فحص العلامات الحيوية', 'name_en' => 'Vital Signs Check', 'name_tr' => 'Vital Bulgu Kontrolü', 'default_price' => 150],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->value('id');

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
