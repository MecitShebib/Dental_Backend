<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TreatmentCatalog;
use Illuminate\Database\Seeder;

class TreatmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->get();

        if ($companies->isEmpty()) {
            return;
        }

        $items = [
            ['code' => 'consultation', 'name_ar' => 'استشارة', 'name_en' => 'Consultation', 'default_price' => 50000, 'color' => '#2563eb'],
            ['code' => 'filling', 'name_ar' => 'حشوة', 'name_en' => 'Filling', 'default_price' => 150000, 'color' => '#059669'],
            ['code' => 'crown', 'name_ar' => 'تلبيسة', 'name_en' => 'Crown', 'default_price' => 450000, 'color' => '#d97706'],
            ['code' => 'implant', 'name_ar' => 'زرعة', 'name_en' => 'Implant', 'default_price' => 3200000, 'color' => '#dc2626'],
            ['code' => 'root_canal', 'name_ar' => 'عصب', 'name_en' => 'Root Canal', 'default_price' => 780000, 'color' => '#7c3aed'],
            ['code' => 'extraction', 'name_ar' => 'خلع', 'name_en' => 'Extraction', 'default_price' => 180000, 'color' => '#0f766e'],
        ];

        foreach ($companies as $company) {
            foreach ($items as $index => $item) {
                TreatmentCatalog::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $item['code'],
                    ],
                    [
                        ...$item,
                        'company_id' => $company->id,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
