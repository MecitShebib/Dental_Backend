<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The five specialties the Doctovaria platform is designed around. All five
 * now have a real (if v1/narrow) backend+frontend -- see each
 * App\Specialties\*\*Module::isBuilt() -- so all five are seeded active
 * (company-subscribable). is_active is a separate, admin-toggleable
 * "available for subscription" flag from isBuilt() ("does real
 * functionality exist"); it just happens both are true for every specialty
 * today.
 */
class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            [
                'key' => Specialty::DENTAL,
                'brand_name' => 'Dentavaria',
                'name_ar' => 'طب الأسنان',
                'name_en' => 'Dentistry',
                'name_tr' => 'Diş Hekimliği',
                'icon' => Specialty::DENTAL,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => Specialty::GYNECOLOGY,
                'brand_name' => 'Gynevaria',
                'name_ar' => 'أمراض النساء والتوليد',
                'name_en' => 'Gynecology & Obstetrics',
                'name_tr' => 'Kadın Hastalıkları ve Doğum',
                'icon' => 'gynecology',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => Specialty::INTERNAL_MEDICINE,
                'brand_name' => 'Medivaria',
                'name_ar' => 'الطب الباطني',
                'name_en' => 'Internal Medicine',
                'name_tr' => 'Dahiliye',
                'icon' => 'internal-medicine',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => Specialty::ORTHOPEDICS,
                'brand_name' => 'Orthovaria',
                'name_ar' => 'جراحة العظام',
                'name_en' => 'Orthopedics',
                'name_tr' => 'Ortopedi',
                'icon' => 'orthopedics',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => Specialty::COSMETIC,
                'brand_name' => 'Estevaria',
                'name_ar' => 'الطب التجميلي',
                'name_en' => 'Cosmetic Medicine',
                'name_tr' => 'Estetik / Kozmetik Tıp',
                'icon' => 'cosmetic',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($specialties as $specialty) {
            // Plain updateOrCreate() relies on HasUuid's "creating" model
            // event to assign a uuid, but DatabaseSeeder runs this seeder
            // inside WithoutModelEvents -- on a genuinely empty table (fresh
            // install/restore) that suppresses the event and the insert
            // fails a NOT NULL constraint. Assign it explicitly on create
            // only, so an existing row's uuid is never disturbed.
            $existing = Specialty::query()->where('key', $specialty['key'])->first();
            if ($existing) {
                $existing->fill($specialty)->save();
            } else {
                Specialty::query()->create([...$specialty, 'uuid' => (string) Str::uuid()]);
            }
        }
    }
}
