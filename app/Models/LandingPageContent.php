<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageContent extends Model
{
    public const LOCALES = ['en', 'ar', 'tr'];

    public const SPECIALTIES = ['dental', 'gynecology', 'internal_medicine', 'orthopedics', 'cosmetic', 'nutrition', 'pediatrics', 'physiotherapy', 'hematology', 'general_surgery', 'general_practice'];

    /** URL slug (also the "product" brand identity) for each specialty key. */
    public const SPECIALTY_SLUGS = [
        'dental' => 'dentavaria',
        'gynecology' => 'gynevaria',
        'internal_medicine' => 'medivaria',
        'orthopedics' => 'orthovaria',
        'cosmetic' => 'estevaria',
        'nutrition' => 'dietavaria',
        'pediatrics' => 'pediavaria',
        'physiotherapy' => 'physiovaria',
        'hematology' => 'hemavaria',
        'general_surgery' => 'surgivaria',
        'general_practice' => 'genervaria',
    ];

    /** Brand accent color per specialty -- matches Dental_FrontEnd's Sidebar.jsx SPECIALTY_ACCENTS/DENTAL_ACCENT exactly. */
    /** Stable keys of the pricing tiers, in display order (see mergePricing()/planVisibility()). */
    public const PLANS = ['starter', 'essentials', 'professional', 'growth', 'enterprise'];

    public const PLAN_LABELS = [
        'starter' => 'Starter (1 doctor + 1 assistant)',
        'essentials' => 'Essentials (3 doctors + 2 assistants)',
        'professional' => 'Professional (6 doctors + 3 assistants)',
        'growth' => 'Growth (10 doctors + 5 assistants)',
        'enterprise' => 'Enterprise (custom)',
    ];

    public const SPECIALTY_ACCENTS = [
        'dental' => '#1f4e8c',
        'gynecology' => '#a6295e',
        'internal_medicine' => '#1f7a5c',
        'orthopedics' => '#b56a1f',
        'cosmetic' => '#7a4fb5',
        'nutrition' => '#1f8ca6',
        'pediatrics' => '#3a86ff',
        'physiotherapy' => '#5c8a1f',
        'hematology' => '#b3261e',
        'general_surgery' => '#37474f',
        'general_practice' => '#8a6d1f',
    ];

    protected $fillable = ['content'];

    protected $casts = [
        'content' => 'array',
    ];

    public static function specialtyKeyForSlug(string $slug): ?string
    {
        $key = array_search($slug, self::SPECIALTY_SLUGS, true);

        return $key === false ? null : $key;
    }

    /**
     * The hub page (product list) in one locale, merged over defaults.
     */
    public static function hub(string $locale = 'en'): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';
        $row = static::query()->first();
        $saved = $row?->content['hub'][$locale] ?? [];

        return static::mergeLocaleDefaults($saved, static::hubDefaultsFor($locale));
    }

    /** All three locales of the hub page -- what the admin edit form needs. */
    public static function hubAll(): array
    {
        $row = static::query()->first();
        $saved = $row?->content['hub'] ?? [];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $result[$locale] = static::mergeLocaleDefaults($saved[$locale] ?? [], static::hubDefaultsFor($locale));
        }

        return $result;
    }

    /**
     * One specialty's full landing page, in one locale, merged over defaults.
     */
    public static function specialty(string $specialty, string $locale = 'en'): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';
        $row = static::query()->first();
        $saved = $row?->content[$specialty][$locale] ?? [];

        return static::mergeLocaleDefaults($saved, static::specialtyDefaultsFor($specialty, $locale));
    }

    /** All three locales of one specialty's page -- what the admin edit form needs. */
    public static function specialtyAll(string $specialty): array
    {
        $row = static::query()->first();
        $saved = $row?->content[$specialty] ?? [];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $result[$locale] = static::mergeLocaleDefaults($saved[$locale] ?? [], static::specialtyDefaultsFor($specialty, $locale));
        }

        return $result;
    }

    /** Every specialty, every locale -- what the admin edit form needs to populate all its tabs. */
    public static function allSpecialtiesAll(): array
    {
        $result = [];
        foreach (self::SPECIALTIES as $specialty) {
            $result[$specialty] = static::specialtyAll($specialty);
        }

        return $result;
    }

    protected static function mergeLocaleDefaults(array $saved, array $defaults): array
    {
        foreach ($defaults as $section => $value) {
            if (! array_key_exists($section, $saved)) {
                continue;
            }

            if ($section === 'pricing') {
                $defaults[$section] = static::mergePricing((array) $saved[$section], $value);

                continue;
            }

            if ($section === 'products') {
                $defaults[$section] = static::mergeProducts((array) $saved[$section], $value);

                continue;
            }

            if ($section === 'hero' && is_array($saved[$section])) {
                $saved[$section] = static::dropSupersededDefaults($saved[$section]);
            }

            if (is_array($value) && array_is_list($value)) {
                $merged = [];
                foreach ($saved[$section] as $index => $row) {
                    $merged[] = is_array($row) && isset($value[$index])
                        ? array_merge($value[$index], $row)
                        : $row;
                }
                $defaults[$section] = $merged;
            } elseif (is_array($value)) {
                $defaults[$section] = array_merge($value, (array) $saved[$section]);
            } else {
                $defaults[$section] = $saved[$section];
            }
        }

        return $defaults;
    }

    /**
     * Hub product cards are matched to the code's product list by their
     * specialty `key`, and the output is always the full default list in
     * default order -- so a product added in code (e.g. the five specialties
     * of 2026-09-27) shows up even on a hub an admin saved earlier. Rows
     * saved before `key` was validated/persisted have no key; those were
     * saved in the then-default order, so they fall back to list position.
     */
    protected static function mergeProducts(array $saved, array $defaults): array
    {
        $savedByKey = collect($saved)->filter(fn ($row) => is_array($row) && ! empty($row['key']))->keyBy('key');
        $keyless = collect($saved)->filter(fn ($row) => is_array($row) && empty($row['key']))->values();

        return collect($defaults)->values()->map(function (array $product, int $index) use ($savedByKey, $keyless) {
            $row = $savedByKey->get($product['key']) ?? ($savedByKey->isEmpty() ? $keyless->get($index) : null);
            $overrides = array_filter((array) $row, fn ($value) => $value !== null && $value !== '');

            return [...$product, ...$overrides, 'key' => $product['key']];
        })->all();
    }

    /**
     * Default copy that has since been replaced in code. A saved value still
     * equal to one of these was never edited by an admin (it's the old
     * default the editor round-tripped), so it must not mask the new one.
     */
    protected const SUPERSEDED_DEFAULTS = [
        'One platform, six clinical specialties',
        'منصة واحدة، ستة تخصصات سريرية',
        'Tek platform, altı klinik uzmanlık alanı',
    ];

    protected static function dropSupersededDefaults(array $section): array
    {
        return array_filter($section, fn ($value) => ! in_array($value, self::SUPERSEDED_DEFAULTS, true));
    }

    /**
     * Pricing tiers are matched by their stable `plan` key, not by list
     * position, and the output always has the default tier set in the
     * default order -- so adding/reordering tiers in code can't be masked
     * or shifted by rows an admin saved earlier. Saved rows without a
     * `plan` key predate the 5-tier pricing (2026-09-26) and are ignored:
     * their prices/seat counts are obsolete.
     */
    protected static function mergePricing(array $saved, array $defaults): array
    {
        $savedByPlan = [];
        foreach ($saved as $row) {
            if (is_array($row) && ! empty($row['plan'])) {
                $savedByPlan[$row['plan']] = $row;
            }
        }

        return array_map(
            fn (array $tier) => isset($savedByPlan[$tier['plan']]) ? array_merge($tier, $savedByPlan[$tier['plan']]) : $tier,
            $defaults,
        );
    }

    /**
     * Global on/off switch per pricing plan (Admin > Landing Page), shared
     * by every specialty page, locale, and the static proposal/pitch
     * documents (via /api/public/plan-visibility). Missing = visible.
     *
     * @return array<string, bool>
     */
    public static function planVisibility(): array
    {
        $saved = static::query()->first()?->content['plans'] ?? [];

        return collect(self::PLANS)->mapWithKeys(fn (string $plan) => [$plan => (bool) ($saved[$plan] ?? true)])->all();
    }

    /**
     * Pricing for the landing page: the features every plan shares (shown
     * once above the cards, whether or not Starter itself is switched on)
     * and the visible cards, which list only their seat counts (doctor and
     * assistant lines) -- no "Everything in <previous plan>", support or
     * other extras.
     *
     * @return array{shared: list<string>, tiers: list<array>}
     */
    public static function pricingLayout(array $pricing): array
    {
        $lines = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $text)), fn ($line) => $line !== ''));
        $byPlan = collect($pricing)->keyBy(fn (array $tier) => $tier['plan'] ?? '');

        $shared = $byPlan->has('starter') && $byPlan->has('essentials')
            ? array_values(array_intersect($lines($byPlan['starter']['features'] ?? ''), $lines($byPlan['essentials']['features'] ?? '')))
            : [];

        $isSeatLine = fn (string $line) => (bool) preg_match('/doctor|assistant|doktor|asistan|طبيب|أطباء|مساعد/iu', $line);

        // "Everything in Essentials, for established clinics..." -> "For
        // established clinics..." (en / ar / tr wording of the stock copy).
        $withoutEverythingIn = function (string $description): string {
            $rest = preg_replace(['/^Everything in [^,]+,\s*/u', '/^كل ما في [^،,]+[،,]\s*/u', "/^\\S+'(?:deki|daki|teki|taki) her şey,\\s*/u"], '', $description);

            return mb_strtoupper(mb_substr($rest, 0, 1)).mb_substr($rest, 1);
        };

        $tiers = array_map(
            fn (array $tier) => [
                ...$tier,
                'description' => $withoutEverythingIn(trim($tier['description'] ?? '')),
                'features' => implode("\n", array_filter(array_diff($lines($tier['features'] ?? ''), $shared), $isSeatLine)),
            ],
            static::visiblePricing($pricing),
        );

        return ['shared' => $shared, 'tiers' => $tiers];
    }

    /** Pricing tiers with plans switched off in the admin removed. */
    public static function visiblePricing(array $pricing): array
    {
        $visibility = static::planVisibility();

        return array_values(array_filter($pricing, fn (array $tier) => $visibility[$tier['plan'] ?? ''] ?? true));
    }

    protected static function hubDefaultsFor(string $locale): array
    {
        return match ($locale) {
            'ar' => static::hubArDefaults(),
            'tr' => static::hubTrDefaults(),
            default => static::hubEnDefaults(),
        };
    }

    protected static function specialtyDefaultsFor(string $specialty, string $locale): array
    {
        return match ($specialty) {
            'dental' => match ($locale) {
                'ar' => static::dentalArDefaults(),
                'tr' => static::dentalTrDefaults(),
                default => static::dentalEnDefaults(),
            },
            'gynecology' => match ($locale) {
                'ar' => static::gynecologyArDefaults(),
                'tr' => static::gynecologyTrDefaults(),
                default => static::gynecologyEnDefaults(),
            },
            'internal_medicine' => match ($locale) {
                'ar' => static::internalMedicineArDefaults(),
                'tr' => static::internalMedicineTrDefaults(),
                default => static::internalMedicineEnDefaults(),
            },
            'orthopedics' => match ($locale) {
                'ar' => static::orthopedicsArDefaults(),
                'tr' => static::orthopedicsTrDefaults(),
                default => static::orthopedicsEnDefaults(),
            },
            'cosmetic' => match ($locale) {
                'ar' => static::cosmeticArDefaults(),
                'tr' => static::cosmeticTrDefaults(),
                default => static::cosmeticEnDefaults(),
            },
            'nutrition' => match ($locale) {
                'ar' => static::nutritionArDefaults(),
                'tr' => static::nutritionTrDefaults(),
                default => static::nutritionEnDefaults(),
            },
            'pediatrics' => match ($locale) {
                'ar' => static::pediatricsArDefaults(),
                'tr' => static::pediatricsTrDefaults(),
                default => static::pediatricsEnDefaults(),
            },
            'physiotherapy' => match ($locale) {
                'ar' => static::physiotherapyArDefaults(),
                'tr' => static::physiotherapyTrDefaults(),
                default => static::physiotherapyEnDefaults(),
            },
            'hematology' => match ($locale) {
                'ar' => static::hematologyArDefaults(),
                'tr' => static::hematologyTrDefaults(),
                default => static::hematologyEnDefaults(),
            },
            'general_surgery' => match ($locale) {
                'ar' => static::generalSurgeryArDefaults(),
                'tr' => static::generalSurgeryTrDefaults(),
                default => static::generalSurgeryEnDefaults(),
            },
            'general_practice' => match ($locale) {
                'ar' => static::generalPracticeArDefaults(),
                'tr' => static::generalPracticeTrDefaults(),
                default => static::generalPracticeEnDefaults(),
            },
            default => [],
        };
    }

    // ── Hub (product list) ──────────────────────────────────────────────

    protected static function hubEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'One platform, eleven clinical specialties',
                'headline' => 'The clinical operating system for modern healthcare practices.',
                'subtext' => 'Doctovaria is built specifically for how each specialty actually works. Pick yours to see the clinical workflow, features, and pricing built around it.',
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'Dental care', 'body' => 'Per-tooth odontogram charting, lab case tracking, and treatment plans built around how dental teams actually work.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'Gynecology & obstetrics', 'body' => "Prenatal care plans, milestone-based visit scheduling, and clinical records built for women's health practices."],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'Internal medicine', 'body' => 'Chronic care plans, recurring visit scheduling, and lab result tracking for long-term patient management.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'Orthopedics', 'body' => 'Rehab care plans, procedure checklists, and milestone scheduling for orthopedic and physical therapy practices.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'Cosmetic medicine', 'body' => 'Session-based treatment plans and procedure tracking built for aesthetic and cosmetic practices.'],
                ['key' => 'nutrition', 'name' => 'Dietavaria', 'tagline' => 'Dietetics & nutrition', 'body' => 'Follow-up program tracking, body composition monitoring, and care plans built for dietitian and nutrition practices.'],
                ['key' => 'pediatrics', 'name' => 'Pediavaria', 'tagline' => 'Pediatrics', 'body' => 'Well-child and vaccination follow-up plans, growth tracking, and records built for pediatric practices.'],
                ['key' => 'physiotherapy', 'name' => 'Physiovaria', 'tagline' => 'Physiotherapy', 'body' => 'Physiotherapy session plans, pain and range-of-motion tracking, and records built for physiotherapy practices.'],
                ['key' => 'hematology', 'name' => 'Hemavaria', 'tagline' => 'Hematology', 'body' => 'Blood count follow-up plans, CBC and transfusion tracking, and records built for hematology practices.'],
                ['key' => 'general_surgery', 'name' => 'Surgivaria', 'tagline' => 'General surgery', 'body' => 'Perioperative care plans, operation and post-op follow-up records built for general surgery practices.'],
                ['key' => 'general_practice', 'name' => 'Genervaria', 'tagline' => 'General practice', 'body' => 'General follow-up plans, vital signs and referral tracking, and records built for general practice clinics.'],
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern healthcare practices.',
                'contact_email' => 'hello@doctovaria.com',
                'copyright_name' => 'Doctovaria',
            ],
        ];
    }

    protected static function hubArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'منصة واحدة، أحد عشر تخصصًا سريريًا',
                'headline' => 'نظام التشغيل السريري لممارسات الرعاية الصحية الحديثة.',
                'subtext' => 'بُنيت Doctovaria خصيصًا لطريقة عمل كل تخصص فعليًا. اختر تخصصك لترى سير العمل السريري والمزايا والأسعار المصممة له تحديدًا.',
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'طب الأسنان', 'body' => 'تخطيط أسنان لكل سن، وتتبع حالات المخبر، وخطط علاج مبنية على طريقة عمل فرق طب الأسنان فعليًا.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'أمراض النساء والتوليد', 'body' => 'خطط رعاية ما قبل الولادة، وجدولة زيارات مبنية على مراحل، وسجلات سريرية مصممة لممارسات صحة المرأة.'],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'الطب الباطني', 'body' => 'خطط رعاية الأمراض المزمنة، وجدولة زيارات متكررة، وتتبع نتائج المخبر لإدارة المرضى على المدى الطويل.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'جراحة العظام', 'body' => 'خطط رعاية إعادة التأهيل، وقوائم إجراءات، وجدولة مبنية على مراحل لممارسات جراحة العظام والعلاج الطبيعي.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'الطب التجميلي', 'body' => 'خطط علاج مبنية على الجلسات وتتبع الإجراءات مصممة لممارسات الطب التجميلي والتجميل.'],
                ['key' => 'nutrition', 'name' => 'Dietavaria', 'tagline' => 'التغذية والحمية', 'body' => 'تتبع برامج المتابعة ومراقبة تكوين الجسم وخطط رعاية مصممة لعيادات التغذية وأخصائيي الحمية.'],
                ['key' => 'pediatrics', 'name' => 'Pediavaria', 'tagline' => 'طب الأطفال', 'body' => 'خطط متابعة الطفل والتطعيم وتتبع النمو وسجلات مصممة لعيادات طب الأطفال.'],
                ['key' => 'physiotherapy', 'name' => 'Physiovaria', 'tagline' => 'العلاج الفيزيائي', 'body' => 'خطط جلسات العلاج الفيزيائي وتتبع الألم ومدى الحركة وسجلات مصممة لعيادات العلاج الفيزيائي.'],
                ['key' => 'hematology', 'name' => 'Hemavaria', 'tagline' => 'أمراض الدم', 'body' => 'خطط متابعة تعداد الدم وتتبع التحاليل ونقل الدم وسجلات مصممة لعيادات أمراض الدم.'],
                ['key' => 'general_surgery', 'name' => 'Surgivaria', 'tagline' => 'الجراحة العامة', 'body' => 'خطط الرعاية المحيطة بالجراحة وسجلات العمليات والمتابعة بعد الجراحة لعيادات الجراحة العامة.'],
                ['key' => 'general_practice', 'name' => 'Genervaria', 'tagline' => 'الطب العام', 'body' => 'خطط المتابعة العامة وتتبع العلامات الحيوية والإحالات وسجلات مصممة لعيادات الطب العام.'],
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات الرعاية الصحية الحديثة.',
                'contact_email' => 'hello@doctovaria.com',
                'copyright_name' => 'Doctovaria',
            ],
        ];
    }

    protected static function hubTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Tek platform, on bir klinik uzmanlık alanı',
                'headline' => 'Modern sağlık pratiklerinin klinik işletim sistemi.',
                'subtext' => 'Doctovaria, her uzmanlık alanının gerçekte nasıl çalıştığına özel olarak inşa edilmiştir. Kendi uzmanlık alanınızı seçin ve onun için özel olarak kurulmuş klinik iş akışını, özellikleri ve fiyatlandırmayı görün.',
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'Diş hekimliği', 'body' => 'Diş bazında odontogram grafiği, laboratuvar vaka takibi ve diş hekimliği ekiplerinin gerçekte nasıl çalıştığına göre kurulmuş tedavi planları.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'Kadın hastalıkları ve doğum', 'body' => 'Doğum öncesi bakım planları, kilometre taşı bazlı randevu planlaması ve kadın sağlığı pratikleri için kurulmuş klinik kayıtlar.'],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'Dahiliye', 'body' => 'Kronik bakım planları, tekrarlayan randevu planlaması ve uzun vadeli hasta yönetimi için laboratuvar sonucu takibi.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'Ortopedi', 'body' => 'Rehabilitasyon bakım planları, prosedür kontrol listeleri ve ortopedi ile fizik tedavi pratikleri için kilometre taşı bazlı planlama.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'Estetik tıp', 'body' => 'Estetik ve kozmetik pratikler için kurulmuş seans bazlı tedavi planları ve prosedür takibi.'],
                ['key' => 'nutrition', 'name' => 'Dietavaria', 'tagline' => 'Diyetisyenlik ve beslenme', 'body' => 'Diyetisyen ve beslenme pratikleri için kurulmuş takip programı takibi, vücut kompozisyonu izleme ve bakım planları.'],
                ['key' => 'pediatrics', 'name' => 'Pediavaria', 'tagline' => 'Çocuk sağlığı ve hastalıkları', 'body' => 'Pediatri pratikleri için çocuk izlem ve aşı planları, büyüme takibi ve hasta kayıtları.'],
                ['key' => 'physiotherapy', 'name' => 'Physiovaria', 'tagline' => 'Fizyoterapi', 'body' => 'Fizyoterapi pratikleri için seans planları, ağrı ve eklem hareket açıklığı takibi ve hasta kayıtları.'],
                ['key' => 'hematology', 'name' => 'Hemavaria', 'tagline' => 'Hematoloji', 'body' => 'Hematoloji pratikleri için kan sayımı takip planları, hemogram ve transfüzyon takibi ve hasta kayıtları.'],
                ['key' => 'general_surgery', 'name' => 'Surgivaria', 'tagline' => 'Genel cerrahi', 'body' => 'Genel cerrahi pratikleri için perioperatif bakım planları, ameliyat ve ameliyat sonrası takip kayıtları.'],
                ['key' => 'general_practice', 'name' => 'Genervaria', 'tagline' => 'Pratisyen hekimlik', 'body' => 'Birinci basamak klinikler için genel takip planları, vital bulgu ve sevk takibi ve hasta kayıtları.'],
            ],
            'footer' => [
                'tagline' => 'Modern sağlık pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@doctovaria.com',
                'copyright_name' => 'Doctovaria',
            ],
        ];
    }

    // ── Dentavaria (dental) ──────────────────────────────────────────────

    protected static function dentalEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted treatment planning',
                'headline' => 'The clinical operating system for modern dental practices.',
                'subheadline' => 'Dentavaria unifies scheduling, treatment records, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Digital treatment records', 'body' => 'Per-tooth charting and odontogram history that stays in sync across every visit and every device.'],
                ['title' => 'AI treatment plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, multi-session treatment plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Dental lab workflow', 'body' => 'Track every lab case from sent to delivered, manage your lab partner network, and see lab costs reflected in your books automatically.'],
                ['title' => 'Open API & integrations', 'body' => 'Generate API tokens from Settings and connect outside equipment — an X-ray imaging system, a CBCT/CT scanner — straight into a patient\'s chart.'],
            ],
            'how_it_works' => [
                ['title' => 'Set up your clinic', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, multi-session treatment plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nVisual odontogram treatment charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nDental lab case tracking & payment ledger\nX-ray image gallery\nCBCT/DICOM scan viewer\nAuto-generated invoices\nBusiness reports (patient & lab balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nVisual odontogram treatment charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nDental lab case tracking & payment ledger\nX-ray image gallery\nCBCT/DICOM scan viewer\nAuto-generated invoices\nBusiness reports (patient & lab balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'EM', 'name' => 'Dr. Elena Marsh', 'role' => 'Owner, Aurora Dental', 'quote' => 'Dentavaria cut our no-show rate in half within a month. The scheduling conflicts just stopped happening.'],
                ['initials' => 'MO', 'name' => 'Dr. Marcus Oduya', 'role' => 'Clinical Director, Northshore Clinics', 'quote' => 'The AI treatment plan assistant saves my associates real time on documentation without cutting corners on care.'],
                ['initials' => 'PN', 'name' => 'Priya Nadar', 'role' => 'Practice Manager, Willowbrook Dental', 'quote' => 'Finally, a system that speaks both "dentist" and "spreadsheet." Billing reconciliation used to take days.'],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Dentavaria handle multiple locations?', 'answer' => 'Yes. Dentavaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI treatment plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial clinic tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most clinics are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Dentavaria running with your own scheduling, treatment records, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@dentavaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern dental practices.',
                'contact_email' => 'hello@dentavaria.com',
                'copyright_name' => 'Dentavaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Dentavaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Dentavaria to your clinic?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your clinic's size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your clinic',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function dentalArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط علاجي مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لعيادات الأسنان الحديثة.',
                'subheadline' => 'توحّد Dentavaria الجدولة وسجلات العلاج والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات علاج رقمية', 'body' => 'رسم بياني للأسنان وسجل تخطيط الأسنان يبقى متزامنًا عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة العلاج بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة علاج متعددة الجلسات ومنظمة — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'سير عمل المخبر', 'body' => 'تابع كل حالة مخبر من الإرسال حتى التسليم، وأدر شبكة مخابر الأسنان الشريكة، وشاهد تكاليف المخبر تنعكس تلقائيًا في حساباتك.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية — جهاز تصوير الأشعة، أو جهاز CBCT/CT — مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ عيادتك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة علاج منظمة متعددة الجلسات يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nرسم تخطيطي بصري لحالة الأسنان (Odontogram)\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع طلبيات المخبر وسجل مدفوعاته\nمعرض صور الأشعة\nعارض تصوير CBCT/DICOM\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء والمخابر)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للعيادات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nرسم تخطيطي بصري لحالة الأسنان (Odontogram)\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع طلبيات المخبر وسجل مدفوعاته\nمعرض صور الأشعة\nعارض تصوير CBCT/DICOM\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء والمخابر)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'EM', 'name' => 'د. إيلينا مارش', 'role' => 'مالكة، Aurora Dental', 'quote' => 'خفّضت Dentavaria نسبة عدم الحضور لدينا إلى النصف خلال شهر واحد. تعارضات الجدولة توقفت ببساطة.'],
                ['initials' => 'MO', 'name' => 'د. ماركوس أودويا', 'role' => 'المدير السريري، Northshore Clinics', 'quote' => 'مساعد خطة العلاج بالذكاء الاصطناعي يوفر لمساعديّ وقتًا حقيقيًا في التوثيق دون التنازل عن جودة الرعاية.'],
                ['initials' => 'PN', 'name' => 'بريا نادار', 'role' => 'مديرة العيادة، Willowbrook Dental', 'quote' => 'أخيرًا نظام يتحدث لغة الطبيب ولغة جداول البيانات معًا. تسوية الفوترة كانت تستغرق أيامًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Dentavaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Dentavaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة العلاج بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك عيادة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم العيادات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Dentavaria يعمل مع جدولتك وسجلات العلاج والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@dentavaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لعيادات الأسنان الحديثة.',
                'contact_email' => 'hello@dentavaria.com',
                'copyright_name' => 'Dentavaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Dentavaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Dentavaria إلى عيادتك؟',
                'subtext' => 'أخبرنا قليلاً عن عيادتك وسنُعدّ لك عرض سعر يناسب حجم عيادتك واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن عيادتك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function dentalTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli tedavi planlamasıyla',
                'headline' => 'Modern diş kliniklerinin klinik işletim sistemi.',
                'subheadline' => 'Dentavaria; randevu planlama, tedavi kayıtları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Dijital tedavi kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan diş bazlı grafik ve odontogram geçmişi.'],
                ['title' => 'Yapay zeka tedavi planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, çok seanslı bir tedavi planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Diş laboratuvarı iş akışı', 'body' => 'Her laboratuvar vakasını gönderimden teslimata kadar takip edin, laboratuvar ortağı ağınızı yönetin ve laboratuvar maliyetlerinin defterlerinize otomatik yansıdığını görün.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => 'Ayarlar\'dan API token oluşturun ve röntgen görüntüleme sistemi veya CBCT/CT cihazı gibi harici cihazları doğrudan hasta dosyasına bağlayın.'],
            ],
            'how_it_works' => [
                ['title' => 'Kliniğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, çok seanslı bir tedavi planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nGörsel diş şeması (odontogram) ile tedavi kaydı\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar vaka takibi ve ödeme defteri\nRöntgen görüntü galerisi\nCBCT/DICOM tarama görüntüleyici\nOtomatik fatura oluşturma\nİş raporları (danışan ve laboratuvar bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen klinikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nGörsel diş şeması (odontogram) ile tedavi kaydı\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar vaka takibi ve ödeme defteri\nRöntgen görüntü galerisi\nCBCT/DICOM tarama görüntüleyici\nOtomatik fatura oluşturma\nİş raporları (danışan ve laboratuvar bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'EM', 'name' => 'Dr. Elena Marsh', 'role' => 'Sahibi, Aurora Dental', 'quote' => 'Dentavaria bir ay içinde randevuya gelmeme oranımızı yarıya indirdi. Randevu çakışmaları basitçe sona erdi.'],
                ['initials' => 'MO', 'name' => 'Dr. Marcus Oduya', 'role' => 'Klinik Direktörü, Northshore Clinics', 'quote' => 'Yapay zeka tedavi planı asistanı, bakımdan ödün vermeden asistanlarımın belgeleme konusunda gerçek zaman kazanmasını sağlıyor.'],
                ['initials' => 'PN', 'name' => 'Priya Nadar', 'role' => 'Klinik Müdürü, Willowbrook Dental', 'quote' => "Sonunda hem 'diş hekimi' hem de 'hesap tablosu' dilini konuşan bir sistem. Faturalandırma mutabakatı günler sürerdi."],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Dentavaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Dentavaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka tedavi planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme kliniği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu klinik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Dentavaria'nın kendi randevu planlamanız, tedavi kayıtlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@dentavaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern diş kliniklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@dentavaria.com',
                'copyright_name' => 'Dentavaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Dentavaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Dentavaria'yı kliniğinize taşımaya hazır mısınız?",
                'subtext' => 'Kliniğiniz hakkında bize biraz bilgi verin, kliniğinizin büyüklüğüne ve ihtiyaçlarına uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Kliniğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Gynevaria (gynecology & obstetrics) ─────────────────────────────

    protected static function gynecologyEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern gynecology & obstetrics practices.',
                'subheadline' => 'Gynevaria unifies scheduling, prenatal care plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Prenatal & clinical records', 'body' => 'Milestone-based prenatal care plans and clinical records that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based care plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based care plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nPrenatal & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nPrenatal & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'AK', 'name' => 'Dr. Amara Kessler', 'role' => "Owner, Meridian Women's Health", 'quote' => "Gynevaria's milestone care plans mean nothing falls through the cracks across a full pregnancy journey."],
                ['initials' => 'SL', 'name' => 'Dr. Sana Lindqvist', 'role' => "Medical Director, Willowbrook Women's Clinic", 'quote' => 'Our front desk finally has one system for scheduling, billing, and patient records instead of three.'],
                ['initials' => 'RC', 'name' => 'Rosa Calderón', 'role' => 'Practice Manager, Northshore OB-GYN', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Gynevaria handle multiple locations?', 'answer' => 'Yes. Gynevaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Gynevaria running with your own scheduling, care plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@gynevaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern gynecology & obstetrics practices.',
                'contact_email' => 'hello@gynevaria.com',
                'copyright_name' => 'Gynevaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Gynevaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Gynevaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function gynecologyArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات أمراض النساء والتوليد الحديثة.',
                'subheadline' => 'توحّد Gynevaria الجدولة وخطط الرعاية ما قبل الولادة والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المريضات.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات ما قبل الولادة والسجلات السريرية', 'body' => 'خطط رعاية ما قبل الولادة مبنية على مراحل وسجلات سريرية تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة رعاية منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريضة، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريضة، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريضة.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المريضات ويسجّلن الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة رعاية منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية ما قبل الولادة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المريضات مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المريضات)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية ما قبل الولادة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المريضات مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المريضات)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'AK', 'name' => 'د. أمارة كيسلر', 'role' => "مالكة، Meridian Women's Health", 'quote' => 'خطط الرعاية المبنية على المراحل في Gynevaria تعني ألا يفوتنا شيء طوال رحلة الحمل الكاملة.'],
                ['initials' => 'SL', 'name' => 'د. سانا ليندكفيست', 'role' => "المديرة الطبية، Willowbrook Women's Clinic", 'quote' => 'أخيرًا يوجد لدى الاستقبال نظام واحد للجدولة والفوترة وسجلات المريضات بدلاً من ثلاثة.'],
                ['initials' => 'RC', 'name' => 'روزا كالديرون', 'role' => 'مديرة العيادة، Northshore OB-GYN', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المريضات آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Gynevaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Gynevaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Gynevaria يعمل مع جدولتك وخطط الرعاية والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@gynevaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات أمراض النساء والتوليد الحديثة.',
                'contact_email' => 'hello@gynevaria.com',
                'copyright_name' => 'Gynevaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Gynevaria؟ أرسلي لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Gynevaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function gynecologyTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern kadın hastalıkları ve doğum pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Gynevaria; randevu planlama, doğum öncesi bakım planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Doğum öncesi ve klinik kayıtlar', 'body' => 'Her ziyaret ve her cihazda senkronize kalan kilometre taşı bazlı doğum öncesi bakım planları ve klinik kayıtlar.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir bakım planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir bakım planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nDoğum öncesi ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nDoğum öncesi ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'AK', 'name' => 'Dr. Amara Kessler', 'role' => "Sahibi, Meridian Women's Health", 'quote' => "Gynevaria'nın kilometre taşı bazlı bakım planları, tüm gebelik sürecinde hiçbir şeyin gözden kaçmamasını sağlıyor."],
                ['initials' => 'SL', 'name' => 'Dr. Sana Lindqvist', 'role' => "Tıbbi Direktör, Willowbrook Women's Clinic", 'quote' => 'Resepsiyonumuz sonunda üç yerine randevu, faturalandırma ve hasta kayıtları için tek bir sisteme sahip.'],
                ['initials' => 'RC', 'name' => 'Rosa Calderón', 'role' => 'Klinik Müdürü, Northshore OB-GYN', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Gynevaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Gynevaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Gynevaria'nın kendi randevu planlamanız, bakım planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@gynevaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern kadın hastalıkları ve doğum pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@gynevaria.com',
                'copyright_name' => 'Gynevaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Gynevaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Gynevaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Medivaria (internal medicine) ───────────────────────────────────

    protected static function internalMedicineEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern internal medicine practices.',
                'subheadline' => 'Medivaria unifies scheduling, chronic care plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Chronic care records', 'body' => 'Recurring visit scheduling and chronic care plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, recurring-visit care plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart — built for long-term, ongoing patient management."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, recurring-visit care plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nChronic care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nChronic care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'TN', 'name' => 'Dr. Tomasz Nowak', 'role' => 'Owner, Meridian Internal Medicine', 'quote' => "Medivaria keeps every chronic patient's recurring visits on schedule automatically — nothing gets missed."],
                ['initials' => 'HB', 'name' => 'Dr. Hana Baptiste', 'role' => 'Medical Director, Northshore Internal Medicine Group', 'quote' => "One system for scheduling, billing, and long-term patient records — our team finally isn't juggling three tools."],
                ['initials' => 'DK', 'name' => 'Daniyar Kair', 'role' => 'Practice Manager, Bright Horizon Internal Medicine', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Medivaria handle multiple locations?', 'answer' => 'Yes. Medivaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Medivaria running with your own scheduling, care plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@medivaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern internal medicine practices.',
                'contact_email' => 'hello@medivaria.com',
                'copyright_name' => 'Medivaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Medivaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Medivaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function internalMedicineArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات الطب الباطني الحديثة.',
                'subheadline' => 'توحّد Medivaria الجدولة وخطط الرعاية المزمنة والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات الرعاية المزمنة', 'body' => 'جدولة زيارات متكررة وخطط رعاية للأمراض المزمنة تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة رعاية منظمة بزيارات متكررة — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض — مصمم لإدارة المرضى على المدى الطويل.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة رعاية منظمة بزيارات متكررة يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط الرعاية المزمنة\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط الرعاية المزمنة\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'TN', 'name' => 'د. توماش نوفاك', 'role' => 'مالك، Meridian Internal Medicine', 'quote' => 'تُبقي Medivaria زيارات كل مريض مزمن المتكررة منظمة تلقائيًا — لا يفوتنا شيء.'],
                ['initials' => 'HB', 'name' => 'د. هانا بابتيست', 'role' => 'المديرة الطبية، Northshore Internal Medicine Group', 'quote' => 'نظام واحد للجدولة والفوترة وسجلات المرضى على المدى الطويل — فريقنا لم يعد يتنقل بين ثلاث أدوات.'],
                ['initials' => 'DK', 'name' => 'دانيار كاير', 'role' => 'مدير العيادة، Bright Horizon Internal Medicine', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Medivaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Medivaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Medivaria يعمل مع جدولتك وخطط الرعاية والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@medivaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات الطب الباطني الحديثة.',
                'contact_email' => 'hello@medivaria.com',
                'copyright_name' => 'Medivaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Medivaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Medivaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function internalMedicineTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern dahiliye pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Medivaria; randevu planlama, kronik bakım planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Kronik bakım kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan tekrarlayan randevu planlaması ve kronik bakım planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, tekrarlayan ziyaretli bir bakım planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin — uzun vadeli hasta yönetimi için kurulmuştur.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, tekrarlayan ziyaretli bir bakım planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nKronik bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nKronik bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'TN', 'name' => 'Dr. Tomasz Nowak', 'role' => 'Sahibi, Meridian Internal Medicine', 'quote' => 'Medivaria, her kronik hastanın tekrarlayan ziyaretlerini otomatik olarak programda tutuyor.'],
                ['initials' => 'HB', 'name' => 'Dr. Hana Baptiste', 'role' => 'Tıbbi Direktör, Northshore Internal Medicine Group', 'quote' => 'Randevu planlama, faturalandırma ve uzun vadeli hasta kayıtları için tek sistem — ekibimiz artık üç araç arasında gidip gelmiyor.'],
                ['initials' => 'DK', 'name' => 'Daniyar Kair', 'role' => 'Klinik Müdürü, Bright Horizon Internal Medicine', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Medivaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Medivaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Medivaria'nın kendi randevu planlamanız, bakım planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@medivaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern dahiliye pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@medivaria.com',
                'copyright_name' => 'Medivaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Medivaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Medivaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Orthovaria (orthopedics) ─────────────────────────────────────────

    protected static function orthopedicsEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern orthopedic practices.',
                'subheadline' => 'Orthovaria unifies scheduling, rehab care plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Rehab care records', 'body' => 'Procedure checklists and milestone-based rehab care plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based rehab plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based rehab plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nRehab & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nRehab & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Orthopedics', 'quote' => "Orthovaria's milestone rehab plans keep every patient's recovery on track automatically."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian Sports Medicine', 'quote' => 'Finally one system for scheduling, billing, and rehab plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Orthopedics', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Orthovaria handle multiple locations?', 'answer' => 'Yes. Orthovaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Orthovaria running with your own scheduling, rehab plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@orthovaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern orthopedic practices.',
                'contact_email' => 'hello@orthovaria.com',
                'copyright_name' => 'Orthovaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Orthovaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Orthovaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function orthopedicsArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات جراحة العظام الحديثة.',
                'subheadline' => 'توحّد Orthovaria الجدولة وخطط رعاية إعادة التأهيل والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات إعادة التأهيل', 'body' => 'قوائم إجراءات وخطط رعاية إعادة تأهيل مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة رعاية تأهيل منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة رعاية تأهيل منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية إعادة التأهيل والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية إعادة التأهيل والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Orthopedics', 'quote' => 'خطط إعادة التأهيل المبنية على المراحل في Orthovaria تُبقي تعافي كل مريض على المسار الصحيح تلقائيًا.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian Sports Medicine', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط التأهيل بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Orthopedics', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Orthovaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Orthovaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Orthovaria يعمل مع جدولتك وخطط إعادة التأهيل والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@orthovaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات جراحة العظام الحديثة.',
                'contact_email' => 'hello@orthovaria.com',
                'copyright_name' => 'Orthovaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Orthovaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Orthovaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function orthopedicsTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern ortopedi pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Orthovaria; randevu planlama, rehabilitasyon bakım planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Rehabilitasyon bakım kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı rehabilitasyon bakım planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir rehabilitasyon planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir rehabilitasyon planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nRehabilitasyon ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nRehabilitasyon ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Orthopedics', 'quote' => "Orthovaria'nın kilometre taşı bazlı rehabilitasyon planları, her hastanın iyileşmesini otomatik olarak yolunda tutuyor."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian Sports Medicine', 'quote' => 'Sonunda randevu planlama, faturalandırma ve rehabilitasyon planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Orthopedics', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Orthovaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Orthovaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Orthovaria'nın kendi randevu planlamanız, rehabilitasyon planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@orthovaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern ortopedi pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@orthovaria.com',
                'copyright_name' => 'Orthovaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Orthovaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Orthovaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Pediavaria (pediatrics) ─────────────────────────────────────────

    protected static function pediatricsEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern pediatric practices.',
                'subheadline' => 'Pediavaria unifies scheduling, well-child and vaccination follow-up plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Well-child records', 'body' => 'Procedure checklists and milestone-based well-child and vaccination follow-up plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based well-child follow-up plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based well-child follow-up plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nWell-child & vaccination plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nWell-child & vaccination plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Pediatrics', 'quote' => "Pediavaria's vaccination and well-child reminders keep every child's follow-ups on schedule automatically."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian Kids Clinic', 'quote' => 'Finally one system for scheduling, billing, and vaccination plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Pediatrics', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Pediavaria handle multiple locations?', 'answer' => 'Yes. Pediavaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Pediavaria running with your own scheduling, vaccination plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@pediavaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern pediatric practices.',
                'contact_email' => 'hello@pediavaria.com',
                'copyright_name' => 'Pediavaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Pediavaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Pediavaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function pediatricsArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات طب الأطفال الحديثة.',
                'subheadline' => 'توحّد Pediavaria الجدولة وخطط متابعة الطفل والتطعيم والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات متابعة الطفل', 'body' => 'قوائم إجراءات وخطط متابعة الطفل والتطعيم مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة متابعة للطفل منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة متابعة للطفل منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط متابعة الطفل والتطعيم والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط متابعة الطفل والتطعيم والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Pediatrics', 'quote' => 'تذكيرات التطعيم والمتابعة في Pediavaria تُبقي مواعيد كل طفل في وقتها تلقائيًا.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian Kids Clinic', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط متابعة الطفل والتطعيم بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Pediatrics', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Pediavaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Pediavaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Pediavaria يعمل مع جدولتك وخطط متابعة الطفل والتطعيم والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@pediavaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات طب الأطفال الحديثة.',
                'contact_email' => 'hello@pediavaria.com',
                'copyright_name' => 'Pediavaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Pediavaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Pediavaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function pediatricsTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern pediatri pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Pediavaria; randevu planlama, çocuk izlem ve aşı planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Çocuk izlem kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı çocuk izlem ve aşı planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir çocuk izlem planı taslağına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir çocuk izlem planı taslağı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nÇocuk izlem ve aşı planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nÇocuk izlem ve aşı planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Pediatrics', 'quote' => "Pediavaria'nın aşı ve izlem hatırlatmaları, her çocuğun kontrollerini otomatik olarak takvimde tutuyor."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian Kids Clinic', 'quote' => 'Sonunda randevu planlama, faturalandırma ve çocuk izlem ve aşı planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Pediatrics', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Pediavaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Pediavaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Pediavaria'nın kendi randevu planlamanız, çocuk izlem ve aşı planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@pediavaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern pediatri pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@pediavaria.com',
                'copyright_name' => 'Pediavaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Pediavaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Pediavaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Physiovaria (physiotherapy) ─────────────────────────────────────────

    protected static function physiotherapyEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern physiotherapy practices.',
                'subheadline' => 'Physiovaria unifies scheduling, physiotherapy session plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Session records', 'body' => 'Procedure checklists and milestone-based physiotherapy session plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based physiotherapy session plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based physiotherapy session plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nPhysiotherapy session & milestone plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nPhysiotherapy session & milestone plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Physiotherapy', 'quote' => "Physiovaria's session plans keep every patient's therapy course on track automatically."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian Sports Physio', 'quote' => 'Finally one system for scheduling, billing, and session plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Physiotherapy', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Physiovaria handle multiple locations?', 'answer' => 'Yes. Physiovaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Physiovaria running with your own scheduling, session plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@physiovaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern physiotherapy practices.',
                'contact_email' => 'hello@physiovaria.com',
                'copyright_name' => 'Physiovaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Physiovaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Physiovaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function physiotherapyArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات العلاج الفيزيائي الحديثة.',
                'subheadline' => 'توحّد Physiovaria الجدولة وخطط جلسات العلاج الفيزيائي والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات الجلسات', 'body' => 'قوائم إجراءات وخطط جلسات العلاج الفيزيائي مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة جلسات علاج فيزيائي منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة جلسات علاج فيزيائي منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط جلسات العلاج الفيزيائي والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط جلسات العلاج الفيزيائي والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Physiotherapy', 'quote' => 'خطط الجلسات في Physiovaria تُبقي مسار علاج كل مريض على الطريق الصحيح تلقائيًا.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian Sports Physio', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط جلسات العلاج الفيزيائي بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Physiotherapy', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Physiovaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Physiovaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Physiovaria يعمل مع جدولتك وخطط جلسات العلاج الفيزيائي والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@physiovaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات العلاج الفيزيائي الحديثة.',
                'contact_email' => 'hello@physiovaria.com',
                'copyright_name' => 'Physiovaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Physiovaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Physiovaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function physiotherapyTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern fizyoterapi pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Physiovaria; randevu planlama, fizyoterapi seans planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Seans kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı fizyoterapi seans planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir fizyoterapi seans planı taslağına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir fizyoterapi seans planı taslağı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nFizyoterapi seans ve kilometre taşı planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nFizyoterapi seans ve kilometre taşı planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Physiotherapy', 'quote' => "Physiovaria'nın seans planları, her hastanın tedavi sürecini otomatik olarak yolunda tutuyor."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian Sports Physio', 'quote' => 'Sonunda randevu planlama, faturalandırma ve fizyoterapi seans planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Physiotherapy', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Physiovaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Physiovaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Physiovaria'nın kendi randevu planlamanız, seans planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@physiovaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern fizyoterapi pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@physiovaria.com',
                'copyright_name' => 'Physiovaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Physiovaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Physiovaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Hemavaria (hematology) ─────────────────────────────────────────

    protected static function hematologyEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern hematology practices.',
                'subheadline' => 'Hemavaria unifies scheduling, blood count follow-up plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Blood count records', 'body' => 'Procedure checklists and milestone-based blood count follow-up plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based blood count follow-up plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based blood count follow-up plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nBlood count & milestone follow-up charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nBlood count & milestone follow-up charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Hematology', 'quote' => "Hemavaria's CBC follow-up plans make sure no control test is ever missed."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian Blood Center', 'quote' => 'Finally one system for scheduling, billing, and follow-up plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Hematology', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Hemavaria handle multiple locations?', 'answer' => 'Yes. Hemavaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Hemavaria running with your own scheduling, follow-up plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@hemavaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern hematology practices.',
                'contact_email' => 'hello@hemavaria.com',
                'copyright_name' => 'Hemavaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Hemavaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Hemavaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function hematologyArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات أمراض الدم الحديثة.',
                'subheadline' => 'توحّد Hemavaria الجدولة وخطط متابعة تعداد الدم والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات تعداد الدم', 'body' => 'قوائم إجراءات وخطط متابعة تعداد الدم مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة متابعة لتعداد الدم منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة متابعة لتعداد الدم منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط متابعة تعداد الدم والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط متابعة تعداد الدم والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Hematology', 'quote' => 'خطط متابعة تعداد الدم في Hemavaria تضمن عدم تفويت أي فحص متابعة.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian Blood Center', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط متابعة تعداد الدم بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Hematology', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Hemavaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Hemavaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Hemavaria يعمل مع جدولتك وخطط متابعة تعداد الدم والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@hemavaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات أمراض الدم الحديثة.',
                'contact_email' => 'hello@hemavaria.com',
                'copyright_name' => 'Hemavaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Hemavaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Hemavaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function hematologyTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern hematoloji pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Hemavaria; randevu planlama, kan sayımı takip planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Kan sayımı kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı kan sayımı takip planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir kan sayımı takip planı taslağına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir kan sayımı takip planı taslağı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nKan sayımı ve kilometre taşı takip grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nKan sayımı ve kilometre taşı takip grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Hematology', 'quote' => "Hemavaria'nın hemogram takip planları sayesinde hiçbir kontrol tahlili atlanmıyor."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian Blood Center', 'quote' => 'Sonunda randevu planlama, faturalandırma ve kan sayımı takip planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Hematology', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Hemavaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Hemavaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Hemavaria'nın kendi randevu planlamanız, kan sayımı takip planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@hemavaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern hematoloji pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@hemavaria.com',
                'copyright_name' => 'Hemavaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Hemavaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Hemavaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Surgivaria (general surgery) ─────────────────────────────────────────

    protected static function generalSurgeryEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern general surgery practices.',
                'subheadline' => 'Surgivaria unifies scheduling, perioperative care plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Surgical care records', 'body' => 'Procedure checklists and milestone-based perioperative care plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based perioperative care plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based perioperative care plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nPerioperative & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nPerioperative & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Surgical Center', 'quote' => 'Surgivaria keeps every pre-op check, surgery date, and post-op control in one timeline.'],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian General Surgery', 'quote' => 'Finally one system for scheduling, billing, and perioperative plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Surgery', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Surgivaria handle multiple locations?', 'answer' => 'Yes. Surgivaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Surgivaria running with your own scheduling, perioperative plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@surgivaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern general surgery practices.',
                'contact_email' => 'hello@surgivaria.com',
                'copyright_name' => 'Surgivaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Surgivaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Surgivaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function generalSurgeryArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات الجراحة العامة الحديثة.',
                'subheadline' => 'توحّد Surgivaria الجدولة وخطط الرعاية المحيطة بالجراحة والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات الرعاية الجراحية', 'body' => 'قوائم إجراءات وخطط الرعاية المحيطة بالجراحة مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة رعاية جراحية منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة رعاية جراحية منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط الرعاية المحيطة بالجراحة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط الرعاية المحيطة بالجراحة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Surgical Center', 'quote' => 'يجمع Surgivaria كل فحوصات ما قبل العملية وموعد الجراحة ومتابعات ما بعدها في خط زمني واحد.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian General Surgery', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط الرعاية المحيطة بالجراحة بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Surgery', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Surgivaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Surgivaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Surgivaria يعمل مع جدولتك وخطط الرعاية المحيطة بالجراحة والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@surgivaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات الجراحة العامة الحديثة.',
                'contact_email' => 'hello@surgivaria.com',
                'copyright_name' => 'Surgivaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Surgivaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Surgivaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function generalSurgeryTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern genel cerrahi pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Surgivaria; randevu planlama, perioperatif bakım planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Cerrahi bakım kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı perioperatif bakım planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir perioperatif bakım planı taslağına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir perioperatif bakım planı taslağı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nPerioperatif ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nPerioperatif ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Surgical Center', 'quote' => 'Surgivaria, ameliyat öncesi kontrolleri, ameliyat tarihini ve ameliyat sonrası takipleri tek bir zaman çizelgesinde topluyor.'],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian General Surgery', 'quote' => 'Sonunda randevu planlama, faturalandırma ve perioperatif bakım planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Surgery', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Surgivaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Surgivaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Surgivaria'nın kendi randevu planlamanız, perioperatif bakım planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@surgivaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern genel cerrahi pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@surgivaria.com',
                'copyright_name' => 'Surgivaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Surgivaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Surgivaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Genervaria (general practice) ─────────────────────────────────────────

    protected static function generalPracticeEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern general practice clinics.',
                'subheadline' => 'Genervaria unifies scheduling, general follow-up plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Patient follow-up records', 'body' => 'Procedure checklists and milestone-based general follow-up plans that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, milestone-based follow-up plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab test result against a patient's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a patient's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, milestone-based follow-up plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nFollow-up & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nFollow-up & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Owner, Northshore Family Clinic', 'quote' => "Genervaria's follow-up plans make sure every patient comes back for their check-up."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Medical Director, Meridian Primary Care', 'quote' => 'Finally one system for scheduling, billing, and follow-up plans instead of several disconnected tools.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Practice Manager, Bright Horizon Medical Center', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is patient data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Genervaria handle multiple locations?', 'answer' => 'Yes. Genervaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Genervaria running with your own scheduling, follow-up plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@genervaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern general practice clinics.',
                'contact_email' => 'hello@genervaria.com',
                'copyright_name' => 'Genervaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Genervaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Genervaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function generalPracticeArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات الطب العام الحديثة.',
                'subheadline' => 'توحّد Genervaria الجدولة وخطط المتابعة العامة والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات متابعة المرضى', 'body' => 'قوائم إجراءات وخطط المتابعة العامة مبنية على مراحل تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة متابعة منظمة مبنية على مراحل — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري في ملف المريض، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة متابعة منظمة مبنية على مراحل يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط المتابعة العامة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط المتابعة العامة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'د. جوليا ماركيتي', 'role' => 'مالكة، Northshore Family Clinic', 'quote' => 'خطط المتابعة في Genervaria تضمن عودة كل مريض لفحصه الدوري.'],
                ['initials' => 'OA', 'name' => 'د. عمر السيد', 'role' => 'المدير الطبي، Meridian Primary Care', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وخطط المتابعة العامة بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'LP', 'name' => 'لينا بيتروفا', 'role' => 'مديرة العيادة، Bright Horizon Medical Center', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات المرضى آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Genervaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Genervaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Genervaria يعمل مع جدولتك وخطط المتابعة العامة والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@genervaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات الطب العام الحديثة.',
                'contact_email' => 'hello@genervaria.com',
                'copyright_name' => 'Genervaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Genervaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Genervaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function generalPracticeTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern birinci basamak pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Genervaria; randevu planlama, genel takip planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Hasta takip kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan prosedür kontrol listeleri ve kilometre taşı bazlı genel takip planları.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, kilometre taşı bazlı bir genel takip planı taslağına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar tahlil sonucunu hasta dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan hasta dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, kilometre taşı bazlı bir genel takip planı taslağı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nTakip ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nTakip ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'JM', 'name' => 'Dr. Julia Marchetti', 'role' => 'Sahibi, Northshore Family Clinic', 'quote' => "Genervaria'nın takip planları sayesinde her hasta kontrolüne geri dönüyor."],
                ['initials' => 'OA', 'name' => 'Dr. Omar Al-Sayed', 'role' => 'Tıbbi Direktör, Meridian Primary Care', 'quote' => 'Sonunda randevu planlama, faturalandırma ve genel takip planları için ayrı araçlar yerine tek bir sistem.'],
                ['initials' => 'LP', 'name' => 'Lena Petrova', 'role' => 'Klinik Müdürü, Bright Horizon Medical Center', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Hasta verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Genervaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Genervaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Genervaria'nın kendi randevu planlamanız, takip planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@genervaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern birinci basamak pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@genervaria.com',
                'copyright_name' => 'Genervaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Genervaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Genervaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Estevaria (cosmetic medicine) ────────────────────────────────────

    protected static function cosmeticEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted treatment planning',
                'headline' => 'The clinical operating system for modern cosmetic & aesthetic practices.',
                'subheadline' => 'Estevaria unifies scheduling, session-based treatment plans, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with clients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Session-based treatment records', 'body' => 'Session-based treatment plans and procedure tracking that stay in sync across every visit and every device.'],
                ['title' => 'AI treatment plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, multi-session treatment plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every client, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab or pre-procedure test result against a client's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a client's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Clients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, multi-session treatment plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nSession-based treatment plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (client balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nSession-based treatment plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (client balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'IF', 'name' => 'Dr. Isabelle Fontaine', 'role' => 'Owner, Aurora Aesthetics', 'quote' => "Estevaria's session-based plans make multi-visit packages effortless to track."],
                ['initials' => 'KY', 'name' => 'Dr. Kenji Yamada', 'role' => 'Medical Director, Meridian Cosmetic Center', 'quote' => 'Finally one system for scheduling, billing, and client records instead of several disconnected tools.'],
                ['initials' => 'AB', 'name' => 'Amina Bello', 'role' => 'Practice Manager, Bright Horizon Aesthetics', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is client data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Estevaria handle multiple locations?', 'answer' => 'Yes. Estevaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI treatment plan?', 'answer' => "The assistant drafts a starting point from the doctor's case description. Every plan is reviewed and confirmed by a licensed doctor before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Estevaria running with your own scheduling, treatment plans, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@estevaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern cosmetic & aesthetic practices.',
                'contact_email' => 'hello@estevaria.com',
                'copyright_name' => 'Estevaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Estevaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Estevaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function cosmeticArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط علاجي مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لممارسات الطب التجميلي الحديثة.',
                'subheadline' => 'توحّد Estevaria الجدولة وخطط العلاج المبنية على الجلسات والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع العملاء.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات علاج مبنية على الجلسات', 'body' => 'خطط علاج مبنية على الجلسات وتتبع الإجراءات تبقى متزامنة عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة العلاج بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة علاج متعددة الجلسات ومنظمة — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل عميل، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري أو فحص ما قبل الإجراء في ملف العميل، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف العميل.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز العملاء ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة علاج منظمة متعددة الجلسات يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط العلاج المبنية على الجلسات\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط خطط العلاج المبنية على الجلسات\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'IF', 'name' => 'د. إيزابيل فونتين', 'role' => 'مالكة، Aurora Aesthetics', 'quote' => 'خطط العلاج المبنية على الجلسات في Estevaria تجعل تتبع الباقات متعددة الجلسات أمرًا سهلاً.'],
                ['initials' => 'KY', 'name' => 'د. كينجي يامادا', 'role' => 'المدير الطبي، Meridian Cosmetic Center', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وسجلات العملاء بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'AB', 'name' => 'أمينة بيلو', 'role' => 'مديرة العيادة، Bright Horizon Aesthetics', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات العملاء آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Estevaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Estevaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة العلاج بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف الطبيب للحالة. تتم مراجعة كل خطة وتأكيدها من طبيب مرخّص قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Estevaria يعمل مع جدولتك وخطط العلاج والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@estevaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لممارسات الطب التجميلي الحديثة.',
                'contact_email' => 'hello@estevaria.com',
                'copyright_name' => 'Estevaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Estevaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Estevaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function cosmeticTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli tedavi planlamasıyla',
                'headline' => 'Modern estetik ve kozmetik pratiklerin klinik işletim sistemi.',
                'subheadline' => 'Estevaria; randevu planlama, seans bazlı tedavi planları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, danışanlara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Seans bazlı tedavi kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan seans bazlı tedavi planları ve prosedür takibi.'],
                ['title' => 'Yapay zeka tedavi planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, çok seanslı bir tedavi planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her danışan için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar veya işlem öncesi tahlil sonucunu danışan dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan danışan dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Danışanlar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, çok seanslı bir tedavi planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nSeans bazlı tedavi planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nSeans bazlı tedavi planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'IF', 'name' => 'Dr. Isabelle Fontaine', 'role' => 'Sahibi, Aurora Aesthetics', 'quote' => "Estevaria'nın seans bazlı planları, çok seanslı paketleri takip etmeyi zahmetsiz hale getiriyor."],
                ['initials' => 'KY', 'name' => 'Dr. Kenji Yamada', 'role' => 'Tıbbi Direktör, Meridian Cosmetic Center', 'quote' => 'Sonunda randevu planlama, faturalandırma ve danışan kayıtları için tek bir sistem.'],
                ['initials' => 'AB', 'name' => 'Amina Bello', 'role' => 'Klinik Müdürü, Bright Horizon Aesthetics', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Danışan verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Estevaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Estevaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka tedavi planı ne kadar doğru?', 'answer' => 'Asistan, hekimin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce lisanslı bir hekim tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Estevaria'nın kendi randevu planlamanız, tedavi planlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@estevaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern estetik ve kozmetik pratiklerin klinik işletim sistemi.',
                'contact_email' => 'hello@estevaria.com',
                'copyright_name' => 'Estevaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Estevaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Estevaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }

    // ── Dietavaria (dietetics & nutrition) ───────────────────────────────

    protected static function nutritionEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted care planning',
                'headline' => 'The clinical operating system for modern dietitian & nutrition practices.',
                'subheadline' => 'Dietavaria unifies scheduling, follow-up program tracking, billing, and AI-assisted care planning in one secure platform — so your team spends less time on admin and more time with clients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Follow-up program tracking', 'body' => 'Structured follow-up programs and body composition tracking that stay in sync across every visit and every device.'],
                ['title' => 'AI care plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, multi-session follow-up plan — reviewed and confirmed by the dietitian.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every client, in real time.'],
                ['title' => 'Accounting & payroll', 'body' => 'A full company fund ledger, expense and capital tracking, and payroll that automatically adds each doctor\'s revenue-share commission to their salary.'],
                ['title' => 'Lab & test result tracking', 'body' => "Record and track every lab result or body composition scan against a client's chart, linked to the visit or appointment that ordered it."],
                ['title' => 'Open API & integrations', 'body' => "Generate API tokens from Settings and connect outside equipment straight into a client's chart."],
            ],
            'how_it_works' => [
                ['title' => 'Set up your practice', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Clients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, multi-session follow-up plan the dietitian can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Starter', 'description' => 'For solo practitioners getting started.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "1 doctor\n1 assistant user (non-doctor staff)\nConflict-free appointment scheduling\nFollow-up program & body composition charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (client balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'essentials', 'name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)\nConflict-free appointment scheduling\nFollow-up program & body composition charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (client balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['plan' => 'professional', 'name' => 'Professional', 'description' => 'Everything in Essentials, for established clinics with a mid-sized team.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => 'Up to 6 doctors
Up to 3 assistant users (non-doctor staff)
Everything in Essentials
Priority support'],
                ['plan' => 'growth', 'name' => 'Growth', 'description' => 'Everything in Professional, with more seats for larger teams.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 10 doctors\nUp to 5 assistant users (non-doctor staff)\nEverything in Professional\nPriority support"],
                ['plan' => 'enterprise', 'name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
            ],
            'benefits' => [
                ['title' => 'Performance', 'body' => 'Built on Laravel with a lean API surface — fast on the front desk and in the field.'],
                ['title' => 'Security', 'body' => 'Token-based sessions, OTP verification, and per-clinic data isolation by default.'],
                ['title' => 'Scalability', 'body' => 'Multi-tenant from day one — add clinics and doctors without re-architecting anything.'],
                ['title' => 'Ease of use', 'body' => 'Front-desk staff and doctors are productive on day one, not after a week of training.'],
            ],
            'testimonials' => [
                ['initials' => 'RH', 'name' => 'Rachel Huang, RD', 'role' => 'Owner, Aurora Nutrition Clinic', 'quote' => "Dietavaria's follow-up programs make tracking long-term client plans effortless."],
                ['initials' => 'DO', 'name' => 'Dr. Daniel Osei', 'role' => 'Clinical Director, Meridian Wellness Group', 'quote' => 'Finally one system for scheduling, billing, and client records instead of several disconnected tools.'],
                ['initials' => 'SP', 'name' => 'Sofia Petrov', 'role' => 'Practice Manager, Willowbrook Dietetics', 'quote' => "Billing reconciliation used to take days. Now it's automatic."],
            ],
            'faq' => [
                ['question' => 'Is client data secure?', 'answer' => "Yes. Every session uses token-based authentication, every login is OTP-verified, and each clinic's data is fully isolated from every other clinic on the platform."],
                ['question' => 'Can Dietavaria handle multiple locations?', 'answer' => 'Yes. Dietavaria is multi-tenant by design, with per-company subscriptions and configurable user limits for groups running several clinics.'],
                ['question' => 'How accurate is the AI care plan?', 'answer' => "The assistant drafts a starting point from the dietitian's case description. Every plan is reviewed and confirmed by the treating dietitian before it's scheduled — it never books anything on its own."],
                ['question' => 'Do you offer a free trial?', 'answer' => "Yes. Book a demo and we'll set up a trial practice tailored to your workflow, no credit card required."],
                ['question' => 'What does onboarding look like?', 'answer' => 'Most practices are live within a week. We import your schedule, doctors, and services, then train your front-desk team.'],
                ['question' => 'Is there a mobile app?', 'answer' => 'Yes. Staff sign in via OTP-secured mobile access — there are no shared passwords.'],
            ],
            'final_cta' => [
                'headline' => 'Ready to give your team back their time?',
                'subtext' => 'Book a demo and see Dietavaria running with your own scheduling, follow-up programs, and billing in under a week.',
                'button_label' => 'Book a demo',
                'button_email' => 'hello@dietavaria.com',
                'note' => 'No credit card required.',
            ],
            'footer' => [
                'tagline' => 'The clinical operating system for modern dietitian & nutrition practices.',
                'contact_email' => 'hello@dietavaria.com',
                'copyright_name' => 'Dietavaria',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'headline' => "We'd love to hear from you",
                'subtext' => 'Questions about Dietavaria? Send us a message and our team will get back to you within one business day.',
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'message_label' => 'Message',
                'submit_label' => 'Send message',
                'success_message' => "Thanks — your message has been sent. We'll be in touch soon.",
            ],
            'quote' => [
                'eyebrow' => 'Get a quote',
                'headline' => 'Ready to bring Dietavaria to your practice?',
                'subtext' => "Tell us a bit about your practice and we'll put together a quote tailored to your size and needs.",
                'name_label' => 'Your name',
                'email_label' => 'Email address',
                'phone_label' => 'Phone number',
                'company_label' => 'Clinic / company name',
                'message_label' => 'Tell us about your practice',
                'submit_label' => 'Request a quote',
                'success_message' => 'Thanks — our team will reach out with a quote shortly.',
            ],
        ];
    }

    protected static function nutritionArDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط رعاية مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لعيادات التغذية وأخصائيي الحمية الحديثة.',
                'subheadline' => 'توحّد Dietavaria الجدولة وتتبع برامج المتابعة والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع العملاء.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'تتبع برامج المتابعة', 'body' => 'برامج متابعة منظمة وتتبع لتحليل تكوين الجسم يبقى متزامنًا عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة الرعاية بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة متابعة متعددة الجلسات ومنظمة — تتم مراجعتها وتأكيدها من أخصائي التغذية.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل عميل، لحظيًا.'],
                ['title' => 'المحاسبة والرواتب', 'body' => 'دفتر صندوق كامل للشركة، وتتبع للمصاريف ورأس المال، ورواتب تضيف تلقائيًا نسبة عمولة كل طبيب من دخله إلى راتبه.'],
                ['title' => 'تتبع المخبر والتحاليل', 'body' => 'سجّل وتابع كل نتيجة تحليل مخبري أو مسح لتكوين الجسم في ملف العميل، مرتبطة بالزيارة أو الموعد الذي طلبها.'],
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية مباشرة بملف العميل.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ ممارستك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز العملاء ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة متابعة منظمة متعددة الجلسات يمكن لأخصائي التغذية تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'بداية', 'description' => 'للطبيب المستقل في بداية الطريق.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "طبيب واحد\nمستخدم مساعد واحد (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط برامج المتابعة وتحليل تكوين الجسم\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'essentials', 'name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nحتى 2 من المستخدمين المساعدين (من غير الأطباء)\nجدولة مواعيد بلا تعارض\nتخطيط برامج المتابعة وتحليل تكوين الجسم\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['plan' => 'professional', 'name' => 'احترافي', 'description' => 'كل ما في أساسيات، للعيادات الراسخة ذات الفريق المتوسط.', 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => 'حتى 6 أطباء
حتى 3 مستخدمين مساعدين (من غير الأطباء)
كل ما في أساسيات
دعم ذو أولوية'],
                ['plan' => 'growth', 'name' => 'نمو', 'description' => 'كل ما في احترافي، مع مقاعد أكثر للفرق الأكبر.', 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 10 أطباء\nحتى 5 مستخدمين مساعدين (من غير الأطباء)\nكل ما في احترافي\nدعم ذو أولوية"],
                ['plan' => 'enterprise', 'name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
            ],
            'benefits' => [
                ['title' => 'الأداء', 'body' => 'مبني على Laravel بواجهة API خفيفة — سريع عند مكتب الاستقبال وفي الميدان.'],
                ['title' => 'الأمان', 'body' => 'جلسات قائمة على الرموز، تحقق برمز OTP، وعزل بيانات كل عيادة افتراضيًا.'],
                ['title' => 'قابلية التوسع', 'body' => 'متعدد المستأجرين منذ اليوم الأول — أضف عيادات وأطباء دون إعادة هيكلة أي شيء.'],
                ['title' => 'سهولة الاستخدام', 'body' => 'يصبح موظفو الاستقبال والأطباء منتجين من اليوم الأول، لا بعد أسبوع من التدريب.'],
            ],
            'testimonials' => [
                ['initials' => 'RH', 'name' => 'Rachel Huang, RD', 'role' => 'مالكة، Aurora Nutrition Clinic', 'quote' => 'برامج المتابعة في Dietavaria تجعل تتبع خطط العملاء طويلة المدى أمرًا سهلاً.'],
                ['initials' => 'DO', 'name' => 'د. Daniel Osei', 'role' => 'المدير الطبي، Meridian Wellness Group', 'quote' => 'أخيرًا نظام واحد للجدولة والفوترة وسجلات العملاء بدلاً من عدة أدوات منفصلة.'],
                ['initials' => 'SP', 'name' => 'Sofia Petrov', 'role' => 'مديرة العيادة، Willowbrook Dietetics', 'quote' => 'تسوية الفوترة كانت تستغرق أيامًا. الآن تتم تلقائيًا.'],
            ],
            'faq' => [
                ['question' => 'هل بيانات العملاء آمنة؟', 'answer' => 'نعم. تستخدم كل جلسة مصادقة قائمة على الرموز، وكل تسجيل دخول يتم التحقق منه برمز OTP، وبيانات كل عيادة معزولة تمامًا عن بقية العيادات على المنصة.'],
                ['question' => 'هل يمكن لـ Dietavaria التعامل مع عدة فروع؟', 'answer' => 'نعم. Dietavaria مصممة لتكون متعددة المستأجرين، مع اشتراكات لكل شركة وحدود مستخدمين قابلة للتخصيص للمجموعات التي تدير عدة عيادات.'],
                ['question' => 'ما مدى دقة خطة الرعاية بالذكاء الاصطناعي؟', 'answer' => 'يضع المساعد نقطة انطلاق من وصف أخصائي التغذية للحالة. تتم مراجعة كل خطة وتأكيدها من أخصائي التغذية المعالج قبل جدولتها — لا يقوم النظام بحجز أي شيء من تلقاء نفسه.'],
                ['question' => 'هل تقدمون فترة تجريبية مجانية؟', 'answer' => 'نعم. احجز عرضًا توضيحيًا وسنُعدّ لك ممارسة تجريبية مخصصة لسير عملك، دون الحاجة لبطاقة ائتمان.'],
                ['question' => 'كيف يبدو التأهيل؟', 'answer' => 'تصبح معظم الممارسات جاهزة خلال أسبوع. نستورد جدولك وأطباءك وخدماتك، ثم ندرّب فريق الاستقبال لديك.'],
                ['question' => 'هل يوجد تطبيق جوال؟', 'answer' => 'نعم. يسجّل الموظفون الدخول عبر وصول آمن بالجوال برمز تحقق — دون كلمات مرور مشتركة.'],
            ],
            'final_cta' => [
                'headline' => 'هل أنت مستعد لإعادة الوقت لفريقك؟',
                'subtext' => 'احجز عرضًا توضيحيًا وشاهد Dietavaria يعمل مع جدولتك وبرامج المتابعة والفوترة الخاصة بك خلال أقل من أسبوع.',
                'button_label' => 'احجز عرضًا توضيحيًا',
                'button_email' => 'hello@dietavaria.com',
                'note' => 'لا حاجة لبطاقة ائتمان.',
            ],
            'footer' => [
                'tagline' => 'نظام التشغيل السريري لعيادات التغذية وأخصائيي الحمية الحديثة.',
                'contact_email' => 'hello@dietavaria.com',
                'copyright_name' => 'Dietavaria',
            ],
            'contact' => [
                'eyebrow' => 'تواصل معنا',
                'headline' => 'يسعدنا التواصل معك',
                'subtext' => 'لديك أسئلة حول Dietavaria؟ أرسل لنا رسالة وسيتواصل معك فريقنا خلال يوم عمل واحد.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'message_label' => 'الرسالة',
                'submit_label' => 'إرسال الرسالة',
                'success_message' => 'شكرًا — تم إرسال رسالتك. سنتواصل معك قريبًا.',
            ],
            'quote' => [
                'eyebrow' => 'احصل على عرض سعر',
                'headline' => 'هل أنت مستعد لإحضار Dietavaria إلى ممارستك؟',
                'subtext' => 'أخبرنا قليلاً عن ممارستك وسنُعدّ لك عرض سعر يناسب حجمها واحتياجاتها.',
                'name_label' => 'اسمك',
                'email_label' => 'البريد الإلكتروني',
                'phone_label' => 'رقم الهاتف',
                'company_label' => 'اسم العيادة / الشركة',
                'message_label' => 'أخبرنا عن ممارستك',
                'submit_label' => 'طلب عرض سعر',
                'success_message' => 'شكرًا — سيتواصل معك فريقنا بعرض سعر قريبًا.',
            ],
        ];
    }

    protected static function nutritionTrDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli bakım planlamasıyla',
                'headline' => 'Modern diyetisyen ve beslenme pratiklerinin klinik işletim sistemi.',
                'subheadline' => 'Dietavaria; randevu planlama, takip programı takibi, faturalandırma ve yapay zeka destekli bakım planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, danışanlara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Takip programı takibi', 'body' => 'Her ziyaret ve her cihazda senkronize kalan yapılandırılmış takip programları ve vücut kompozisyonu takibi.'],
                ['title' => 'Yapay zeka bakım planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, diyetisyen tarafından incelenip onaylanan yapılandırılmış, çok seanslı bir takip planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her danışan için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
                ['title' => 'Muhasebe ve bordro', 'body' => 'Tam bir şirket kasa defteri, gider ve sermaye takibi ve her doktorun ciro payı komisyonunu maaşına otomatik ekleyen bordro.'],
                ['title' => 'Laboratuvar ve tahlil sonucu takibi', 'body' => 'Her laboratuvar sonucunu veya vücut kompozisyon taramasını danışan dosyasına kaydedin ve onu talep eden ziyaret veya randevuya bağlayın.'],
                ['title' => 'Açık API ve entegrasyonlar', 'body' => "Ayarlar'dan API token oluşturun ve harici cihazları doğrudan danışan dosyasına bağlayın."],
            ],
            'how_it_works' => [
                ['title' => 'Pratiğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Danışanlar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve diyetisyenin düzenleyip onaylayabileceği yapılandırılmış, çok seanslı bir takip planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['plan' => 'starter', 'name' => 'Başlangıç', 'description' => 'Yeni başlayan bireysel hekimler için.', 'price_monthly' => '$30', 'price_yearly' => '$24', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "1 doktor\n1 asistan kullanıcı (doktor olmayan personel)\nÇakışmasız randevu planlama\nTakip programı ve vücut kompozisyonu grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'essentials', 'name' => 'Temel', 'description' => 'Tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$75', 'price_yearly' => '$60', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\n2 asistan kullanıcıya kadar (doktor olmayan personel)\nÇakışmasız randevu planlama\nTakip programı ve vücut kompozisyonu grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['plan' => 'professional', 'name' => 'Profesyonel', 'description' => "Temel'deki her şey, orta ölçekli ekibe sahip yerleşik klinikler için.", 'price_monthly' => '$140', 'price_yearly' => '$112', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "6 doktora kadar
3 asistan kullanıcıya kadar (doktor olmayan personel)
Temel'deki her şey
Öncelikli destek"],
                ['plan' => 'growth', 'name' => 'Büyüme', 'description' => "Profesyonel'deki her şey, daha büyük ekipler için daha fazla kullanıcıyla.", 'price_monthly' => '$220', 'price_yearly' => '$176', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "10 doktora kadar\n5 asistan kullanıcıya kadar (doktor olmayan personel)\nProfesyonel'deki her şey\nÖncelikli destek"],
                ['plan' => 'enterprise', 'name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'RH', 'name' => 'Rachel Huang, RD', 'role' => 'Sahibi, Aurora Nutrition Clinic', 'quote' => "Dietavaria'nın takip programları, uzun vadeli danışan planlarını takip etmeyi zahmetsiz hale getiriyor."],
                ['initials' => 'DO', 'name' => 'Dr. Daniel Osei', 'role' => 'Tıbbi Direktör, Meridian Wellness Group', 'quote' => 'Sonunda randevu planlama, faturalandırma ve danışan kayıtları için tek bir sistem.'],
                ['initials' => 'SP', 'name' => 'Sofia Petrov', 'role' => 'Klinik Müdürü, Willowbrook Dietetics', 'quote' => 'Faturalandırma mutabakatı günler sürerdi. Şimdi otomatik.'],
            ],
            'faq' => [
                ['question' => 'Danışan verileri güvenli mi?', 'answer' => 'Evet. Her oturum token tabanlı kimlik doğrulama kullanır, her giriş OTP ile doğrulanır ve her kliniğin verisi platformdaki diğer tüm kliniklerden tamamen izole edilir.'],
                ['question' => 'Dietavaria birden fazla lokasyonu yönetebilir mi?', 'answer' => 'Evet. Dietavaria, birden fazla klinik işleten gruplar için şirket başına abonelikler ve yapılandırılabilir kullanıcı limitleriyle çok kiracılı olarak tasarlanmıştır.'],
                ['question' => 'Yapay zeka bakım planı ne kadar doğru?', 'answer' => 'Asistan, diyetisyenin vaka açıklamasından bir başlangıç noktası hazırlar. Her plan, programlanmadan önce ilgili diyetisyen tarafından incelenir ve onaylanır — sistem kendi başına hiçbir şey randevulamaz.'],
                ['question' => 'Ücretsiz deneme sunuyor musunuz?', 'answer' => 'Evet. Bir demo talep edin, iş akışınıza uygun bir deneme pratiği kuralım — kredi kartı gerekmez.'],
                ['question' => 'Kurulum süreci nasıl işliyor?', 'answer' => 'Çoğu pratik bir hafta içinde kullanıma hazır olur. Programınızı, doktorlarınızı ve hizmetlerinizi içe aktarır, ardından resepsiyon ekibinizi eğitiriz.'],
                ['question' => 'Mobil uygulama var mı?', 'answer' => 'Evet. Personel, OTP ile güvenli mobil erişim üzerinden giriş yapar — paylaşılan şifre yoktur.'],
            ],
            'final_cta' => [
                'headline' => 'Ekibinize zamanını geri vermeye hazır mısınız?',
                'subtext' => "Bir demo talep edin ve Dietavaria'nın kendi randevu planlamanız, takip programlarınız ve faturalandırmanızla bir haftadan kısa sürede nasıl çalıştığını görün.",
                'button_label' => 'Demo talep edin',
                'button_email' => 'hello@dietavaria.com',
                'note' => 'Kredi kartı gerekmez.',
            ],
            'footer' => [
                'tagline' => 'Modern diyetisyen ve beslenme pratiklerinin klinik işletim sistemi.',
                'contact_email' => 'hello@dietavaria.com',
                'copyright_name' => 'Dietavaria',
            ],
            'contact' => [
                'eyebrow' => 'Bize ulaşın',
                'headline' => 'Sizden haber almak isteriz',
                'subtext' => 'Dietavaria hakkında sorularınız mı var? Bize bir mesaj gönderin, ekibimiz bir iş günü içinde size dönsün.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'message_label' => 'Mesaj',
                'submit_label' => 'Mesaj gönder',
                'success_message' => 'Teşekkürler — mesajınız gönderildi. Yakında sizinle iletişime geçeceğiz.',
            ],
            'quote' => [
                'eyebrow' => 'Teklif alın',
                'headline' => "Dietavaria'yı pratiğinize taşımaya hazır mısınız?",
                'subtext' => 'Pratiğiniz hakkında bize biraz bilgi verin, büyüklüğünüze ve ihtiyaçlarınıza uygun bir teklif hazırlayalım.',
                'name_label' => 'Adınız',
                'email_label' => 'E-posta adresi',
                'phone_label' => 'Telefon numarası',
                'company_label' => 'Klinik / şirket adı',
                'message_label' => 'Pratiğiniz hakkında bize bilgi verin',
                'submit_label' => 'Teklif talep edin',
                'success_message' => 'Teşekkürler — ekibimiz kısa süre içinde bir teklifle sizinle iletişime geçecek.',
            ],
        ];
    }
}
