<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use Illuminate\Database\Seeder;

class TreatmentCatalogSeeder extends Seeder
{
    /**
     * Simple, company-manageable service list (Settings > Pricing). Prices are
     * in Turkish Lira (TRY), matching this table's default_price scale.
     */
    protected function companyItems(): array
    {
        return [
            ['code' => 'consultation', 'name_ar' => 'استشارة', 'name_en' => 'Consultation', 'name_tr' => 'Konsültasyon', 'default_price' => 500],
            ['code' => 'filling', 'name_ar' => 'حشوة', 'name_en' => 'Filling', 'name_tr' => 'Dolgu', 'default_price' => 1500],
            ['code' => 'crown', 'name_ar' => 'تلبيسة', 'name_en' => 'Crown', 'name_tr' => 'Kron', 'default_price' => 6000],
            ['code' => 'implant', 'name_ar' => 'زرعة', 'name_en' => 'Implant', 'name_tr' => 'İmplant', 'default_price' => 20000],
            ['code' => 'root_canal', 'name_ar' => 'عصب', 'name_en' => 'Root Canal', 'name_tr' => 'Kanal Tedavisi', 'default_price' => 4000],
            ['code' => 'extraction', 'name_ar' => 'خلع', 'name_en' => 'Extraction', 'name_tr' => 'Çekim', 'default_price' => 1500],
        ];
    }

    /**
     * Every restoration TYPE the vendored odontogram-v2 widget's combined
     * restoration dropdown can produce (registry/restorations.ts's
     * RESTORATION_MATRIX), each with a base price. The final price per
     * type+material combo is base x the material's multiplier below --
     * combos not offered by RESTORATION_MATRIX for a given type (e.g. no
     * metal veneer) are simply never generated/seeded.
     */
    protected function restorationTypeBases(): array
    {
        return [
            'crown' => ['name_en' => 'Crown', 'name_ar' => 'تلبيسة', 'name_tr' => 'Kron', 'base' => 11500],
            'bridge' => ['name_en' => 'Bridge Unit', 'name_ar' => 'وحدة جسر', 'name_tr' => 'Köprü Ünitesi', 'base' => 13000],
            'inlay' => ['name_en' => 'Inlay', 'name_ar' => 'حشوة داخلية (إينلاي)', 'name_tr' => 'İnley', 'base' => 6500],
            'onlay' => ['name_en' => 'Onlay', 'name_ar' => 'حشوة تعويضية (أونلاي)', 'name_tr' => 'Onley', 'base' => 7500],
            'veneer' => ['name_en' => 'Veneer', 'name_ar' => 'قشرة تجميلية (فينير)', 'name_tr' => 'Veneer (Kaplama)', 'base' => 7000],
        ];
    }

    protected function restorationMaterialMultipliers(): array
    {
        return [
            'emax' => ['name_en' => 'E-max', 'name_ar' => 'إيماكس', 'name_tr' => 'E-max', 'mult' => 0.96],
            'gold' => ['name_en' => 'Gold', 'name_ar' => 'ذهب', 'name_tr' => 'Altın', 'mult' => 1.13],
            'gradia' => ['name_en' => 'Gradia', 'name_ar' => 'غراديا', 'name_tr' => 'Gradia', 'mult' => 0.74],
            'zircon' => ['name_en' => 'Zirconia', 'name_ar' => 'زركون', 'name_tr' => 'Zirkonyum', 'mult' => 1.09],
            'metal' => ['name_en' => 'Metal', 'name_ar' => 'معدن', 'name_tr' => 'Metal', 'mult' => 0.70],
            'metal-ceramic' => ['name_en' => 'Metal-Ceramic', 'name_ar' => 'خزف معدني', 'name_tr' => 'Metal-Seramik', 'mult' => 0.87],
            'telescope' => ['name_en' => 'Telescopic', 'name_ar' => 'تلسكوبي', 'name_tr' => 'Teleskopik', 'mult' => 1.22],
            // Temporary is a flat low price regardless of type/base rather than a multiplier.
            'temporary' => ['name_en' => 'Temporary', 'name_ar' => 'مؤقت', 'name_tr' => 'Geçici', 'mult' => null],
        ];
    }

    /**
     * Mirrors Dental_FrontEnd's vendored registry/restorations.ts RESTORATION_MATRIX
     * exactly -- which materials each restoration type actually offers.
     */
    protected function restorationMatrix(): array
    {
        return [
            'crown' => ['emax', 'gold', 'gradia', 'zircon', 'metal', 'metal-ceramic', 'telescope', 'temporary'],
            'bridge' => ['emax', 'gold', 'gradia', 'zircon', 'metal', 'metal-ceramic', 'telescope', 'temporary'],
            'inlay' => ['emax', 'gold', 'gradia', 'zircon', 'temporary'],
            'onlay' => ['emax', 'gold', 'gradia', 'zircon', 'temporary'],
            'veneer' => ['emax', 'gold', 'gradia', 'zircon', 'temporary'],
        ];
    }

    protected function temporaryPriceFor(string $type): float
    {
        return match ($type) {
            'crown' => 1800,
            'bridge' => 3500,
            default => 900, // inlay / onlay / veneer
        };
    }

    /**
     * Generates one catalog item per valid (type, material) combo -- code
     * "restoration:{type}:{material}", matching buildOdontogramV2Entries()'s
     * combined-restoration entry on the frontend.
     */
    protected function restorationItems(): array
    {
        $types = $this->restorationTypeBases();
        $materials = $this->restorationMaterialMultipliers();
        $items = [];

        foreach ($this->restorationMatrix() as $type => $validMaterials) {
            foreach ($validMaterials as $material) {
                $price = $materials[$material]['mult'] === null
                    ? $this->temporaryPriceFor($type)
                    : round($types[$type]['base'] * $materials[$material]['mult'], -2);

                $items[] = [
                    'code' => "restoration:{$type}:{$material}",
                    'name_en' => "{$types[$type]['name_en']} - {$materials[$material]['name_en']}",
                    'name_ar' => "{$types[$type]['name_ar']} - {$materials[$material]['name_ar']}",
                    'name_tr' => "{$types[$type]['name_tr']} - {$materials[$material]['name_tr']}",
                    'default_price' => (float) $price,
                ];
            }
        }

        return $items;
    }

    /**
     * One row per procedure/condition the vendored V2 odontogram widget can
     * select on a tooth (see App\Services\OdontogramV2Vocabulary, which this
     * list mirrors exactly). code = "{category}:{value}" (or
     * "restoration:{type}:{material}" for the combined restoration axis --
     * see restorationItems()), matching the `category`/`value` fields
     * odontogramV2.js's buildOdontogramV2Entries() already puts on every
     * priced entry -- the frontend sends these same codes back verbatim when
     * it asks the backend to price a saved odontogram. Prices are in Turkish
     * Lira (TRY), realistic for the private dental market this app targets.
     */
    protected function odontogramItems(): array
    {
        return [
            ...$this->restorationItems(),

            // toothSelection -- what's actually in/on the tooth
            ['code' => 'toothSelection:implant', 'name_en' => 'Dental Implant', 'name_ar' => 'زرعة سنية', 'name_tr' => 'Diş İmplantı', 'default_price' => 20000],
            ['code' => 'toothSelection:tooth-under-gum', 'name_en' => 'Subgingival Tooth Treatment', 'name_ar' => 'علاج السن تحت اللثة', 'name_tr' => 'Diş Eti Altı Diş Tedavisi', 'default_price' => 1200],
            ['code' => 'toothSelection:no-tooth-after-extraction', 'name_en' => 'Post-Extraction Site Care', 'name_ar' => 'عناية بموضع الخلع', 'name_tr' => 'Çekim Sonrası Bölge Bakımı', 'default_price' => 800],

            // toothSubstrate -- what's left of the tooth to build on
            ['code' => 'toothSubstrate:radix', 'name_en' => 'Radix (Root) Anchor', 'name_ar' => 'دعامة جذرية (راديكس)', 'name_tr' => 'Radiks (Kök) Desteği', 'default_price' => 4000],
            ['code' => 'toothSubstrate:broken', 'name_en' => 'Broken Tooth Substrate (Assessment)', 'name_ar' => 'أساس السن مكسور (تقييم)', 'name_tr' => 'Kırık Diş Alt Yapısı (Değerlendirme)', 'default_price' => 0],
            ['code' => 'toothSubstrate:crownprep', 'name_en' => 'Crown Preparation', 'name_ar' => 'تحضير للتلبيسة', 'name_tr' => 'Kron Hazırlığı', 'default_price' => 1500],

            // prosthesis -- implant attachment or removable denture
            ['code' => 'prosthesis:healing-abutment', 'name_en' => 'Healing Abutment', 'name_ar' => 'دعامة شفاء (هيلينغ أباتمنت)', 'name_tr' => 'İyileşme Abutmentı', 'default_price' => 2500],
            ['code' => 'prosthesis:locator', 'name_en' => 'Locator Attachment', 'name_ar' => 'ملحق لوكاتور', 'name_tr' => 'Locator Tutucu', 'default_price' => 6000],
            ['code' => 'prosthesis:locator-denture', 'name_en' => 'Locator-Retained Overdenture', 'name_ar' => 'طقم علوي مثبت بلوكاتور', 'name_tr' => 'Locator Destekli Üst Protez', 'default_price' => 18000],
            ['code' => 'prosthesis:bar', 'name_en' => 'Bar Attachment', 'name_ar' => 'ملحق بقضيب', 'name_tr' => 'Barlı Tutucu', 'default_price' => 13000],
            ['code' => 'prosthesis:bar-denture', 'name_en' => 'Bar-Retained Overdenture', 'name_ar' => 'طقم مثبت بقضيب', 'name_tr' => 'Barlı Üst Protez', 'default_price' => 22000],
            ['code' => 'prosthesis:removable-partial', 'name_en' => 'Removable Partial Denture', 'name_ar' => 'طقم جزئي متحرك', 'name_tr' => 'Hareketli Parsiyel Protez', 'default_price' => 5500],
            ['code' => 'prosthesis:removable-full', 'name_en' => 'Removable Full Denture', 'name_ar' => 'طقم كامل متحرك', 'name_tr' => 'Hareketli Tam Protez', 'default_price' => 9000],

            // endo -- root canal treatment stage/material
            ['code' => 'endo:endo-medical-filling', 'name_en' => 'Root Canal Medication (Temporary Dressing)', 'name_ar' => 'حشوة عصب دوائية مؤقتة', 'name_tr' => 'Kanal İçi Geçici İlaçlama', 'default_price' => 1000],
            ['code' => 'endo:endo-filling', 'name_en' => 'Root Canal Treatment (Complete)', 'name_ar' => 'حشوة عصب كاملة', 'name_tr' => 'Kanal Tedavisi (Tam)', 'default_price' => 4500],
            ['code' => 'endo:endo-filling-incomplete', 'name_en' => 'Root Canal Treatment (Incomplete)', 'name_ar' => 'حشوة عصب غير كاملة', 'name_tr' => 'Kanal Tedavisi (Eksik)', 'default_price' => 2500],
            ['code' => 'endo:endo-glass-pin', 'name_en' => 'Fiber (Glass) Post', 'name_ar' => 'وتد زجاجي (فايبر)', 'name_tr' => 'Fiber (Cam) Post', 'default_price' => 3200],
            ['code' => 'endo:endo-metal-pin', 'name_en' => 'Metal Post', 'name_ar' => 'وتد معدني', 'name_tr' => 'Metal Post', 'default_price' => 2800],

            // fillingMaterial -- priced per surface; the app multiplies this by
            // how many surfaces were marked, so this is a per-surface unit price.
            ['code' => 'fillingMaterial:amalgam', 'name_en' => 'Amalgam Filling (per surface)', 'name_ar' => 'حشوة أملغم (للسطح الواحد)', 'name_tr' => 'Amalgam Dolgu (yüzey başına)', 'default_price' => 1800],
            ['code' => 'fillingMaterial:composite', 'name_en' => 'Composite Filling (per surface)', 'name_ar' => 'حشوة كومبوزيت (للسطح الواحد)', 'name_tr' => 'Kompozit Dolgu (yüzey başına)', 'default_price' => 2200],
            ['code' => 'fillingMaterial:gic', 'name_en' => 'Glass Ionomer Filling (per surface)', 'name_ar' => 'حشوة زجاجية أيونية (للسطح الواحد)', 'name_tr' => 'Cam İyonomer Dolgu (yüzey başına)', 'default_price' => 1600],
            ['code' => 'fillingMaterial:temporary', 'name_en' => 'Temporary Filling (per surface)', 'name_ar' => 'حشوة مؤقتة (للسطح الواحد)', 'name_tr' => 'Geçici Dolgu (yüzey başına)', 'default_price' => 800],

            // fillingDefect -- a problem found on an existing filling (per surface)
            ['code' => 'fillingDefect:marginal', 'name_en' => 'Marginal Filling Defect Repair (per surface)', 'name_ar' => 'إصلاح عيب هامشي بالحشوة (للسطح الواحد)', 'name_tr' => 'Kenar Dolgu Kusuru Onarımı (yüzey başına)', 'default_price' => 600],
            ['code' => 'fillingDefect:fracture', 'name_en' => 'Fractured Filling Repair (per surface)', 'name_ar' => 'إصلاح حشوة متصدعة (للسطح الواحد)', 'name_tr' => 'Kırık Dolgu Onarımı (yüzey başına)', 'default_price' => 900],
            ['code' => 'fillingDefect:wear', 'name_en' => 'Worn Filling Repair (per surface)', 'name_ar' => 'إصلاح حشوة متآكلة (للسطح الواحد)', 'name_tr' => 'Aşınmış Dolgu Onarımı (yüzey başına)', 'default_price' => 500],

            // mods -- periodontal/apical/caries conditions marked on a tooth
            ['code' => 'mods:inflammation', 'name_en' => 'Periapical Inflammation Treatment', 'name_ar' => 'علاج التهاب ذروي', 'name_tr' => 'Periapikal İltihap Tedavisi', 'default_price' => 700],
            ['code' => 'mods:parodontal', 'name_en' => 'Periodontal Treatment', 'name_ar' => 'علاج لثوي (بيريودنتال)', 'name_tr' => 'Periodontal Tedavi', 'default_price' => 1500],
            ['code' => 'mods:caries', 'name_en' => 'Caries Treatment', 'name_ar' => 'علاج تسوس', 'name_tr' => 'Çürük Tedavisi', 'default_price' => 350],

            // wearEdge / wearCervical -- typed tooth wear (replaces the old
            // bruxismWear/bruxismNeckWear booleans)
            ['code' => 'wearEdge:attrition', 'name_en' => 'Incisal/Occlusal Attrition Treatment', 'name_ar' => 'علاج تآكل الحافة القاطعة/الإطباقية', 'name_tr' => 'Kesici/Oklüzal Aşınma Tedavisi', 'default_price' => 700],
            ['code' => 'wearEdge:erosion', 'name_en' => 'Incisal/Occlusal Erosion Treatment', 'name_ar' => 'علاج تآكل حمضي للحافة القاطعة/الإطباقية', 'name_tr' => 'Kesici/Oklüzal Erozyon Tedavisi', 'default_price' => 700],
            ['code' => 'wearCervical:abrasion', 'name_en' => 'Cervical Abrasion Treatment', 'name_ar' => 'علاج تآكل عنق السن (احتكاكي)', 'name_tr' => 'Servikal Abrazyon Tedavisi', 'default_price' => 600],
            ['code' => 'wearCervical:abfraction', 'name_en' => 'Cervical Abfraction Treatment', 'name_ar' => 'علاج تآكل عنق السن (إجهادي)', 'name_tr' => 'Servikal Abfraksiyon Tedavisi', 'default_price' => 600],
            ['code' => 'wearCervical:erosion', 'name_en' => 'Cervical Erosion Treatment', 'name_ar' => 'علاج تآكل عنق السن (حمضي)', 'name_tr' => 'Servikal Erozyon Tedavisi', 'default_price' => 600],

            // discoloration -- whitening/bleaching treatment by cause
            ['code' => 'discoloration:tetracycline', 'name_en' => 'Tetracycline Discoloration Whitening', 'name_ar' => 'تبييض تصبغ التتراسيكلين', 'name_tr' => 'Tetrasiklin Renklenmesi Beyazlatma', 'default_price' => 3500],
            ['code' => 'discoloration:fluorosis', 'name_en' => 'Fluorosis Discoloration Whitening', 'name_ar' => 'تبييض تصبغ الفلوروزس', 'name_tr' => 'Florozis Renklenmesi Beyazlatma', 'default_price' => 3500],
            ['code' => 'discoloration:nonvital', 'name_en' => 'Non-Vital Tooth Whitening', 'name_ar' => 'تبييض سن ميت العصب', 'name_tr' => 'Nonvital Diş Beyazlatma', 'default_price' => 2500],
            ['code' => 'discoloration:extrinsic', 'name_en' => 'Extrinsic Stain Removal', 'name_ar' => 'إزالة تصبغ خارجي', 'name_tr' => 'Dış Leke Temizliği', 'default_price' => 1200],
            ['code' => 'discoloration:other', 'name_en' => 'Discoloration Treatment (Other)', 'name_ar' => 'علاج تصبغ (أخرى)', 'name_tr' => 'Renklenme Tedavisi (Diğer)', 'default_price' => 1500],

            // orthoAppliance -- orthodontic appliance placement
            ['code' => 'orthoAppliance:bracket', 'name_en' => 'Orthodontic Bracket Placement', 'name_ar' => 'تركيب بريكيت تقويم', 'name_tr' => 'Ortodontik Braket Takılması', 'default_price' => 8000],
            ['code' => 'orthoAppliance:band', 'name_en' => 'Orthodontic Band Placement', 'name_ar' => 'تركيب حلقة تقويم', 'name_tr' => 'Ortodontik Bant Takılması', 'default_price' => 4500],

            // mobility -- graded periodontal splinting/treatment (replaces the
            // old flat mods:mobility toggle)
            ['code' => 'mobility:m1', 'name_en' => 'Tooth Mobility Treatment (Grade 1)', 'name_ar' => 'علاج ترهل السن (الدرجة 1)', 'name_tr' => 'Diş Hareketliliği Tedavisi (Derece 1)', 'default_price' => 550],
            ['code' => 'mobility:m2', 'name_en' => 'Tooth Mobility Treatment (Grade 2)', 'name_ar' => 'علاج ترهل السن (الدرجة 2)', 'name_tr' => 'Diş Hareketliliği Tedavisi (Derece 2)', 'default_price' => 900],
            ['code' => 'mobility:m3', 'name_en' => 'Tooth Mobility Treatment (Grade 3)', 'name_ar' => 'علاج ترهل السن (الدرجة 3)', 'name_tr' => 'Diş Hareketliliği Tedavisi (Derece 3)', 'default_price' => 1400],

            // periImplant -- graded peri-implant disease treatment
            ['code' => 'periImplant:mucositis', 'name_en' => 'Peri-Implant Mucositis Treatment', 'name_ar' => 'علاج التهاب مخاطية ما حول الزرعة', 'name_tr' => 'Peri-İmplant Mukozit Tedavisi', 'default_price' => 1200],
            ['code' => 'periImplant:peri-implantitis-mild', 'name_en' => 'Peri-Implantitis Treatment (Mild)', 'name_ar' => 'علاج التهاب ما حول الزرعة (خفيف)', 'name_tr' => 'Peri-İmplantit Tedavisi (Hafif)', 'default_price' => 2500],
            ['code' => 'periImplant:peri-implantitis-moderate', 'name_en' => 'Peri-Implantitis Treatment (Moderate)', 'name_ar' => 'علاج التهاب ما حول الزرعة (متوسط)', 'name_tr' => 'Peri-İmplantit Tedavisi (Orta)', 'default_price' => 4500],
            ['code' => 'periImplant:peri-implantitis-severe', 'name_en' => 'Peri-Implantitis Treatment (Severe)', 'name_ar' => 'علاج التهاب ما حول الزرعة (شديد)', 'name_tr' => 'Peri-İmplantit Tedavisi (Şiddetli)', 'default_price' => 7000],

            // pulpDx -- graded pulp diagnosis/management (replaces the old
            // flat pulpInflam boolean)
            ['code' => 'pulpDx:reversible-pulpitis', 'name_en' => 'Reversible Pulpitis Management', 'name_ar' => 'علاج التهاب لب قابل للعكس', 'name_tr' => 'Reversibl Pulpitis Tedavisi', 'default_price' => 500],
            ['code' => 'pulpDx:irreversible-pulpitis', 'name_en' => 'Irreversible Pulpitis Management', 'name_ar' => 'علاج التهاب لب غير قابل للعكس', 'name_tr' => 'İrreversibl Pulpitis Tedavisi', 'default_price' => 1200],
            ['code' => 'pulpDx:necrosis', 'name_en' => 'Pulp Necrosis Management', 'name_ar' => 'علاج نخر اللب', 'name_tr' => 'Pulpa Nekrozu Tedavisi', 'default_price' => 1800],

            // resorptionType -- root resorption treatment
            ['code' => 'resorptionType:internal', 'name_en' => 'Internal Resorption Treatment', 'name_ar' => 'علاج الارتشاف الداخلي', 'name_tr' => 'İç Rezorpsiyon Tedavisi', 'default_price' => 2500],
            ['code' => 'resorptionType:external-cervical', 'name_en' => 'External Cervical Resorption Treatment', 'name_ar' => 'علاج الارتشاف العنقي الخارجي', 'name_tr' => 'Dış Servikal Rezorpsiyon Tedavisi', 'default_price' => 2500],

            // rootCaries -- root-surface caries, staged
            ['code' => 'rootCaries:active', 'name_en' => 'Active Root Caries Treatment', 'name_ar' => 'علاج تسوس جذري نشط', 'name_tr' => 'Aktif Kök Çürüğü Tedavisi', 'default_price' => 900],
            ['code' => 'rootCaries:arrested', 'name_en' => 'Arrested Root Caries Monitoring', 'name_ar' => 'متابعة تسوس جذري متوقف', 'name_tr' => 'Durdurulmuş Kök Çürüğü Takibi', 'default_price' => 300],
            ['code' => 'rootCaries:active-cavitated', 'name_en' => 'Active Cavitated Root Caries Treatment', 'name_ar' => 'علاج تسوس جذري نشط مع تجويف', 'name_tr' => 'Kaviteli Aktif Kök Çürüğü Tedavisi', 'default_price' => 1400],

            // indicators -- boolean flags on a tooth. Pure assessment/diagnostic
            // markers (e.g. "a crown is recommended") are priced at 0: nothing is
            // performed yet, so nothing is billable.
            ['code' => 'indicators:crownNeeded', 'name_en' => 'Crown Recommendation (Assessment)', 'name_ar' => 'توصية بتلبيسة (تقييم)', 'name_tr' => 'Kron Önerisi (Değerlendirme)', 'default_price' => 0],
            ['code' => 'indicators:crownReplace', 'name_en' => 'Crown Replacement (Assessment)', 'name_ar' => 'توصية باستبدال التلبيسة (تقييم)', 'name_tr' => 'Kron Değişimi (Değerlendirme)', 'default_price' => 0],
            ['code' => 'indicators:missingClosed', 'name_en' => 'Closed Missing-Tooth Space (Assessment)', 'name_ar' => 'فراغ سن مفقود مغلق (تقييم)', 'name_tr' => 'Kapalı Diş Boşluğu (Değerlendirme)', 'default_price' => 0],
            ['code' => 'indicators:extractionPlan', 'name_en' => 'Tooth Extraction', 'name_ar' => 'خلع سن', 'name_tr' => 'Diş Çekimi', 'default_price' => 2300],
            ['code' => 'indicators:extractionWound', 'name_en' => 'Post-Extraction Wound Care', 'name_ar' => 'عناية بجرح الخلع', 'name_tr' => 'Çekim Yarası Bakımı', 'default_price' => 0],
            ['code' => 'indicators:bridgePillar', 'name_en' => 'Bridge Abutment (Assessment)', 'name_ar' => 'دعامة جسر (تقييم)', 'name_tr' => 'Köprü Dayanağı (Değerlendirme)', 'default_price' => 0],
            ['code' => 'indicators:fissureSealing', 'name_en' => 'Fissure Sealant', 'name_ar' => 'تغليف الشقوق والحفر', 'name_tr' => 'Fissür Örtücü', 'default_price' => 500],
            ['code' => 'indicators:contactMesial', 'name_en' => 'Mesial Contact Restoration', 'name_ar' => 'ترميم نقطة تماس إنسي', 'name_tr' => 'Mesiyal Kontak Onarımı', 'default_price' => 400],
            ['code' => 'indicators:contactDistal', 'name_en' => 'Distal Contact Restoration', 'name_ar' => 'ترميم نقطة تماس وحشي', 'name_tr' => 'Distal Kontak Onarımı', 'default_price' => 400],
            ['code' => 'indicators:brokenMesial', 'name_en' => 'Mesial Chip Repair', 'name_ar' => 'إصلاح كسر إنسي', 'name_tr' => 'Mesiyal Kırık Onarımı', 'default_price' => 400],
            ['code' => 'indicators:brokenIncisal', 'name_en' => 'Incisal Chip Repair', 'name_ar' => 'إصلاح كسر قاطع', 'name_tr' => 'Kesici Kırık Onarımı', 'default_price' => 400],
            ['code' => 'indicators:brokenDistal', 'name_en' => 'Distal Chip Repair', 'name_ar' => 'إصلاح كسر وحشي', 'name_tr' => 'Distal Kırık Onarımı', 'default_price' => 400],
            ['code' => 'indicators:parapulpalPin', 'name_en' => 'Parapulpal Pin', 'name_ar' => 'دبوس شبه لبي', 'name_tr' => 'Parapulpal Pin', 'default_price' => 900],
            ['code' => 'indicators:endoResection', 'name_en' => 'Apical Resection', 'name_ar' => 'قطع ذروة الجذر', 'name_tr' => 'Apikal Rezeksiyon', 'default_price' => 5500],
            ['code' => 'indicators:calculus', 'name_en' => 'Scaling (Calculus Removal)', 'name_ar' => 'إزالة الجير (تقليح)', 'name_tr' => 'Diş Taşı Temizliği (Küretaj)', 'default_price' => 600],
            ['code' => 'indicators:crownLeakage', 'name_en' => 'Crown/Bridge Recementation', 'name_ar' => 'إعادة تلصيق تلبيسة/جسر', 'name_tr' => 'Kron/Köprü Yeniden Simantasyonu', 'default_price' => 800],
        ];
    }

    /**
     * Seeds (or re-syncs, via updateOrCreate -- safe to call repeatedly) the
     * full catalog for a single company. Called from run() for every existing
     * company at seed time, and directly from Admin\CompanyController::store()
     * so a company created afterward (the normal, ongoing way companies are
     * created in this app -- there's no other Company::create() call site)
     * isn't left with an empty catalog and a broken Settings > Pricing screen
     * and unpriced odontogram until someone remembers to re-run the seeder.
     */
    public function seedCompany(Company $company): void
    {
        // Every item this seeder knows about is dental (Dentavaria) content;
        // a future specialty gets its own seeder rather than a parameter
        // here, since there's nothing else to seed yet.
        $dentalSpecialtyId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');

        foreach ($this->companyItems() as $index => $item) {
            TreatmentCatalog::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $item['code']],
                [
                    ...$item,
                    'company_id' => $company->id,
                    'specialty_id' => $dentalSpecialtyId,
                    'scope' => TreatmentCatalog::SCOPE_COMPANY,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        foreach ($this->odontogramItems() as $index => $item) {
            TreatmentCatalog::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $item['code']],
                [
                    ...$item,
                    'company_id' => $company->id,
                    'specialty_id' => $dentalSpecialtyId,
                    'scope' => TreatmentCatalog::SCOPE_ODONTOGRAM,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->seedCompany($company));
    }
}
