<?php

namespace App\Specialties\GeneralPractice;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Genervaria's v1 (2026-09-27): a small flat treatment catalog and
 * a GeneralFollowupCarePlanService (examination, a two-week follow-up,
 * and a periodic check-up at three months),
 * via the generic MilestoneCarePlanService. Codes are gp_-prefixed
 * because treatment_catalog codes are unique per company across every
 * specialty (seedCatalog() is updateOrCreate'd on company_id + code).
 */
class GeneralPracticeModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::GENERAL_PRACTICE;
    }

    public function brandName(): string
    {
        return 'Genervaria';
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
            ['code' => 'gp_examination', 'name_ar' => 'فحص عام', 'name_en' => 'General Examination', 'name_tr' => 'Genel Muayene', 'default_price' => 400],
            ['code' => 'gp_followup', 'name_ar' => 'زيارة متابعة', 'name_en' => 'Follow-up Visit', 'name_tr' => 'Kontrol Muayenesi', 'default_price' => 250],
            ['code' => 'gp_lab_panel', 'name_ar' => 'لوحة تحاليل أساسية', 'name_en' => 'Basic Lab Panel', 'name_tr' => 'Temel Tahlil Paneli', 'default_price' => 500],
            ['code' => 'gp_checkup', 'name_ar' => 'فحص دوري شامل', 'name_en' => 'Periodic Check-up', 'name_tr' => 'Periyodik Sağlık Kontrolü', 'default_price' => 700],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::GENERAL_PRACTICE)->value('id');

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
