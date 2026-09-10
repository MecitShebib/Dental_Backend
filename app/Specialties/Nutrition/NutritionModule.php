<?php

namespace App\Specialties\Nutrition;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Dietavaria's v1 prototype (2026-09-09), built the same way Estevaria was:
 * a real treatment catalog, a NutritionCarePlanService (a consultation plus
 * an N-session follow-up program, spaced at a chosen interval, via the
 * generic MilestoneCarePlanService), and a real frontend entry point
 * (NutritionCarePlanModal on Client Details, gated by canAccessNutrition)
 * all exist and are tested.
 */
class NutritionModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::NUTRITION;
    }

    public function brandName(): string
    {
        return 'Dietavaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    /**
     * The two package-able session types NutritionCarePlanService offers,
     * plus the one-off consultation every program starts with, plus a
     * standalone plan-revision item. Prices in Turkish Lira, same scale as
     * the other specialty catalogs.
     */
    protected function catalogItems(): array
    {
        return [
            ['code' => 'nutrition_consultation', 'name_ar' => 'استشارة تغذية أولية', 'name_en' => 'Initial Nutrition Consultation', 'name_tr' => 'İlk Beslenme Danışmanlığı', 'default_price' => 250],
            ['code' => 'followup_session', 'name_ar' => 'جلسة متابعة', 'name_en' => 'Follow-up Session', 'name_tr' => 'Takip Seansı', 'default_price' => 400],
            ['code' => 'body_composition_analysis', 'name_ar' => 'تحليل تكوين الجسم', 'name_en' => 'Body Composition Analysis', 'name_tr' => 'Vücut Kompozisyon Analizi', 'default_price' => 350],
            ['code' => 'meal_plan_revision', 'name_ar' => 'تحديث خطة التغذية', 'name_en' => 'Meal Plan Revision', 'name_tr' => 'Beslenme Planı Güncelleme', 'default_price' => 300],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::NUTRITION)->value('id');

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
