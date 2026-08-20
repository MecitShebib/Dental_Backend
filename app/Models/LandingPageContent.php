<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageContent extends Model
{
    public const LOCALES = ['en', 'ar', 'tr'];

    public const SPECIALTIES = ['dental', 'gynecology', 'internal_medicine', 'orthopedics', 'cosmetic'];

    /** URL slug (also the "product" brand identity) for each specialty key. */
    public const SPECIALTY_SLUGS = [
        'dental' => 'dentavaria',
        'gynecology' => 'gynevaria',
        'internal_medicine' => 'medivaria',
        'orthopedics' => 'orthovaria',
        'cosmetic' => 'estevaria',
    ];

    /** Brand accent color per specialty -- matches Dental_FrontEnd's Sidebar.jsx SPECIALTY_ACCENTS/DENTAL_ACCENT exactly. */
    public const SPECIALTY_ACCENTS = [
        'dental' => '#1f4e8c',
        'gynecology' => '#a6295e',
        'internal_medicine' => '#1f7a5c',
        'orthopedics' => '#b56a1f',
        'cosmetic' => '#7a4fb5',
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
            default => [],
        };
    }

    // ── Hub (product list) ──────────────────────────────────────────────

    protected static function hubEnDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'One platform, five clinical specialties',
                'headline' => 'The clinical operating system for modern healthcare practices.',
                'subtext' => 'Doctovaria is built specifically for how each specialty actually works. Pick yours to see the clinical workflow, features, and pricing built around it.',
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'Dental care', 'body' => 'Per-tooth odontogram charting, lab case tracking, and treatment plans built around how dental teams actually work.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'Gynecology & obstetrics', 'body' => "Prenatal care plans, milestone-based visit scheduling, and clinical records built for women's health practices."],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'Internal medicine', 'body' => 'Chronic care plans, recurring visit scheduling, and lab result tracking for long-term patient management.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'Orthopedics', 'body' => 'Rehab care plans, procedure checklists, and milestone scheduling for orthopedic and physical therapy practices.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'Cosmetic medicine', 'body' => 'Session-based treatment plans and procedure tracking built for aesthetic and cosmetic practices.'],
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
                'eyebrow' => 'منصة واحدة، خمسة تخصصات سريرية',
                'headline' => 'نظام التشغيل السريري لممارسات الرعاية الصحية الحديثة.',
                'subtext' => 'بُنيت Doctovaria خصيصًا لطريقة عمل كل تخصص فعليًا. اختر تخصصك لترى سير العمل السريري والمزايا والأسعار المصممة له تحديدًا.',
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'طب الأسنان', 'body' => 'تخطيط أسنان لكل سن، وتتبع حالات المخبر، وخطط علاج مبنية على طريقة عمل فرق طب الأسنان فعليًا.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'أمراض النساء والتوليد', 'body' => 'خطط رعاية ما قبل الولادة، وجدولة زيارات مبنية على مراحل، وسجلات سريرية مصممة لممارسات صحة المرأة.'],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'الطب الباطني', 'body' => 'خطط رعاية الأمراض المزمنة، وجدولة زيارات متكررة، وتتبع نتائج المخبر لإدارة المرضى على المدى الطويل.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'جراحة العظام', 'body' => 'خطط رعاية إعادة التأهيل، وقوائم إجراءات، وجدولة مبنية على مراحل لممارسات جراحة العظام والعلاج الطبيعي.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'الطب التجميلي', 'body' => 'خطط علاج مبنية على الجلسات وتتبع الإجراءات مصممة لممارسات الطب التجميلي والتجميل.'],
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
                'eyebrow' => 'Tek platform, beş klinik uzmanlık alanı',
                'headline' => 'Modern sağlık pratiklerinin klinik işletim sistemi.',
                'subtext' => "Doctovaria, her uzmanlık alanının gerçekte nasıl çalıştığına özel olarak inşa edilmiştir. Kendi uzmanlık alanınızı seçin ve onun için özel olarak kurulmuş klinik iş akışını, özellikleri ve fiyatlandırmayı görün.",
            ],
            'products' => [
                ['key' => 'dental', 'name' => 'Dentavaria', 'tagline' => 'Diş hekimliği', 'body' => 'Diş bazında odontogram grafiği, laboratuvar vaka takibi ve diş hekimliği ekiplerinin gerçekte nasıl çalıştığına göre kurulmuş tedavi planları.'],
                ['key' => 'gynecology', 'name' => 'Gynevaria', 'tagline' => 'Kadın hastalıkları ve doğum', 'body' => 'Doğum öncesi bakım planları, kilometre taşı bazlı randevu planlaması ve kadın sağlığı pratikleri için kurulmuş klinik kayıtlar.'],
                ['key' => 'internal_medicine', 'name' => 'Medivaria', 'tagline' => 'Dahiliye', 'body' => 'Kronik bakım planları, tekrarlayan randevu planlaması ve uzun vadeli hasta yönetimi için laboratuvar sonucu takibi.'],
                ['key' => 'orthopedics', 'name' => 'Orthovaria', 'tagline' => 'Ortopedi', 'body' => 'Rehabilitasyon bakım planları, prosedür kontrol listeleri ve ortopedi ile fizik tedavi pratikleri için kilometre taşı bazlı planlama.'],
                ['key' => 'cosmetic', 'name' => 'Estevaria', 'tagline' => 'Estetik tıp', 'body' => 'Estetik ve kozmetik pratikler için kurulmuş seans bazlı tedavi planları ve prosedür takibi.'],
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
                ['title' => 'Open API & integrations', 'body' => 'Generate API tokens from Settings and connect outside equipment — like an X-ray imaging system — straight into a patient\'s chart.'],
            ],
            'how_it_works' => [
                ['title' => 'Set up your clinic', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, multi-session treatment plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit, without AI.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nConflict-free appointment scheduling\nVisual odontogram treatment charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nDental lab case tracking & payment ledger\nX-ray image gallery\nAuto-generated invoices\nBusiness reports (patient & lab balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['name' => 'Growth', 'description' => 'Everything in Essentials, plus AI-assisted treatment planning for larger teams.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Up to 10 doctors\nEverything in Essentials\nAI treatment plan assistant (voice or text, doctor-reviewed)\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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
                ['title' => 'واجهة برمجية مفتوحة وتكاملات', 'body' => 'أنشئ رموز API من الإعدادات وصِل أجهزة خارجية — مثل جهاز تصوير الأشعة — مباشرة بملف المريض.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ عيادتك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة علاج منظمة متعددة الجلسات يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['name' => 'أساسيات', 'description' => 'للعيادات النامية التي تريد كل الأدوات، بدون الذكاء الاصطناعي.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nجدولة مواعيد بلا تعارض\nرسم تخطيطي بصري لحالة الأسنان (Odontogram)\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع طلبيات المخبر وسجل مدفوعاته\nمعرض صور الأشعة\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء والمخابر)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'كل ما في أساسيات، بالإضافة إلى التخطيط العلاجي المدعوم بالذكاء الاصطناعي لفرق أكبر.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "حتى 10 أطباء\nكل ما في أساسيات\nمساعد خطة العلاج بالذكاء الاصطناعي (صوت أو نص، بمراجعة الطبيب)\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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
                ['title' => 'Açık API ve entegrasyonlar', 'body' => 'Ayarlar\'dan API token oluşturun ve röntgen görüntüleme sistemi gibi harici cihazları doğrudan hasta dosyasına bağlayın.'],
            ],
            'how_it_works' => [
                ['title' => 'Kliniğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, çok seanslı bir tedavi planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['name' => 'Temel', 'description' => 'Yapay zeka olmadan tüm araç setini isteyen büyüyen klinikler için.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nÇakışmasız randevu planlama\nGörsel diş şeması (odontogram) ile tedavi kaydı\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar vaka takibi ve ödeme defteri\nRöntgen görüntü galerisi\nOtomatik fatura oluşturma\nİş raporları (danışan ve laboratuvar bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => "Temel'deki her şeye ek olarak, daha büyük ekipler için yapay zeka destekli tedavi planlaması.", 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "10 doktora kadar\nTemel'deki her şey\nYapay zeka tedavi planı asistanı (sesli veya yazılı, doktor onaylı)\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
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
                ['name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit, without AI.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nConflict-free appointment scheduling\nPrenatal & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['name' => 'Growth', 'description' => 'Everything in Essentials, plus AI-assisted care planning for larger teams.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Up to 10 doctors\nEverything in Essentials\nAI care plan assistant (voice or text, doctor-reviewed)\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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
                ['name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات، بدون الذكاء الاصطناعي.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية ما قبل الولادة والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المريضات مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المريضات)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'كل ما في أساسيات، بالإضافة إلى مساعد خطة الرعاية بالذكاء الاصطناعي لفرق أكبر.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "حتى 10 أطباء\nكل ما في أساسيات\nمساعد خطة الرعاية بالذكاء الاصطناعي (صوت أو نص، بمراجعة الطبيب)\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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
                ['name' => 'Temel', 'description' => 'Yapay zeka olmadan tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nÇakışmasız randevu planlama\nDoğum öncesi ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => "Temel'deki her şeye ek olarak, daha büyük ekipler için yapay zeka destekli bakım planlaması.", 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "10 doktora kadar\nTemel'deki her şey\nYapay zeka bakım planı asistanı (sesli veya yazılı, doktor onaylı)\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
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
                ['name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit, without AI.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nConflict-free appointment scheduling\nChronic care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['name' => 'Growth', 'description' => 'Everything in Essentials, plus AI-assisted care planning for larger teams.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Up to 10 doctors\nEverything in Essentials\nAI care plan assistant (voice or text, doctor-reviewed)\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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
                ['name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات، بدون الذكاء الاصطناعي.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nجدولة مواعيد بلا تعارض\nتخطيط خطط الرعاية المزمنة\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'كل ما في أساسيات، بالإضافة إلى مساعد خطة الرعاية بالذكاء الاصطناعي لفرق أكبر.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "حتى 10 أطباء\nكل ما في أساسيات\nمساعد خطة الرعاية بالذكاء الاصطناعي (صوت أو نص، بمراجعة الطبيب)\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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
                ['name' => 'Temel', 'description' => 'Yapay zeka olmadan tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nÇakışmasız randevu planlama\nKronik bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => "Temel'deki her şeye ek olarak, daha büyük ekipler için yapay zeka destekli bakım planlaması.", 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "10 doktora kadar\nTemel'deki her şey\nYapay zeka bakım planı asistanı (sesli veya yazılı, doktor onaylı)\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
            ],
            'benefits' => [
                ['title' => 'Performans', 'body' => 'Laravel üzerine yalın bir API yüzeyiyle kurulmuştur — resepsiyonda ve sahada hızlıdır.'],
                ['title' => 'Güvenlik', 'body' => 'Varsayılan olarak token tabanlı oturumlar, OTP doğrulama ve klinik başına veri izolasyonu.'],
                ['title' => 'Ölçeklenebilirlik', 'body' => 'İlk günden itibaren çok kiracılı — hiçbir şeyi yeniden yapılandırmadan klinik ve doktor ekleyin.'],
                ['title' => 'Kullanım kolaylığı', 'body' => 'Resepsiyon personeli ve doktorlar bir haftalık eğitimden sonra değil, ilk günden itibaren verimlidir.'],
            ],
            'testimonials' => [
                ['initials' => 'TN', 'name' => 'Dr. Tomasz Nowak', 'role' => 'Sahibi, Meridian Internal Medicine', 'quote' => "Medivaria, her kronik hastanın tekrarlayan ziyaretlerini otomatik olarak programda tutuyor."],
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
                ['name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit, without AI.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nConflict-free appointment scheduling\nRehab & milestone care plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (patient balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['name' => 'Growth', 'description' => 'Everything in Essentials, plus AI-assisted care planning for larger teams.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Up to 10 doctors\nEverything in Essentials\nAI care plan assistant (voice or text, doctor-reviewed)\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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
                ['name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات، بدون الذكاء الاصطناعي.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nجدولة مواعيد بلا تعارض\nتخطيط خطط رعاية إعادة التأهيل والمراحل\nسجل تسعير العلاجات والفوترة\nإدارة بيانات المرضى مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة المرضى)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'كل ما في أساسيات، بالإضافة إلى مساعد خطة الرعاية بالذكاء الاصطناعي لفرق أكبر.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "حتى 10 أطباء\nكل ما في أساسيات\nمساعد خطة الرعاية بالذكاء الاصطناعي (صوت أو نص، بمراجعة الطبيب)\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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
                ['name' => 'Temel', 'description' => 'Yapay zeka olmadan tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nÇakışmasız randevu planlama\nRehabilitasyon ve kilometre taşı bazlı bakım planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => "Temel'deki her şeye ek olarak, daha büyük ekipler için yapay zeka destekli bakım planlaması.", 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "10 doktora kadar\nTemel'deki her şey\nYapay zeka bakım planı asistanı (sesli veya yazılı, doktor onaylı)\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
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
                ['name' => 'Essentials', 'description' => 'For growing practices that want the full toolkit, without AI.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nConflict-free appointment scheduling\nSession-based treatment plan charting\nTreatment pricing & billing ledger\nClient management with financial summaries\nFund, expenses, capital & payroll accounting\nDoctor commission tracking\nLab & test result tracking\nAuto-generated invoices\nBusiness reports (client balances)\nAPI access with integration tokens\nSecure mobile app access (OTP login)\nMulti-branch support\nEmail support"],
                ['name' => 'Growth', 'description' => 'Everything in Essentials, plus AI-assisted treatment planning for larger teams.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Up to 10 doctors\nEverything in Essentials\nAI treatment plan assistant (voice or text, doctor-reviewed)\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance and scale needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Unlimited doctors\nEverything in Growth\nDedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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
                ['name' => 'أساسيات', 'description' => 'للممارسات النامية التي تريد كل الأدوات، بدون الذكاء الاصطناعي.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nجدولة مواعيد بلا تعارض\nتخطيط خطط العلاج المبنية على الجلسات\nسجل تسعير العلاجات والفوترة\nإدارة بيانات العملاء مع الملخصات المالية\nمحاسبة الصندوق والمصاريف ورأس المال والرواتب\nتتبع عمولات الأطباء\nتتبع المخبر والتحاليل\nإصدار فواتير تلقائي\nتقارير الأعمال (أرصدة العملاء)\nوصول عبر API برموز تكامل\nوصول آمن عبر تطبيق الجوال (تحقق OTP)\nدعم متعدد الفروع\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'كل ما في أساسيات، بالإضافة إلى التخطيط العلاجي المدعوم بالذكاء الاصطناعي لفرق أكبر.', 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "حتى 10 أطباء\nكل ما في أساسيات\nمساعد خطة العلاج بالذكاء الاصطناعي (صوت أو نص، بمراجعة الطبيب)\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال والحجم الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "أطباء بلا حدود\nكل ما في نمو\nتأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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
                ['name' => 'Temel', 'description' => 'Yapay zeka olmadan tüm araç setini isteyen büyüyen pratikler için.', 'price_monthly' => '$59', 'price_yearly' => '$47', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nÇakışmasız randevu planlama\nSeans bazlı tedavi planı grafiği\nTedavi fiyatlandırma ve faturalama defteri\nMali özetlerle danışan yönetimi\nKasa, giderler, sermaye ve bordro muhasebesi\nDoktor komisyon takibi\nLaboratuvar ve tahlil sonucu takibi\nOtomatik fatura oluşturma\nİş raporları (danışan bakiyeleri)\nEntegrasyon token'larıyla API erişimi\nGüvenli mobil uygulama erişimi (OTP girişi)\nÇoklu şube desteği\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => "Temel'deki her şeye ek olarak, daha büyük ekipler için yapay zeka destekli tedavi planlaması.", 'price_monthly' => '$199', 'price_yearly' => '$159', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "10 doktora kadar\nTemel'deki her şey\nYapay zeka tedavi planı asistanı (sesli veya yazılı, doktor onaylı)\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk ve ölçek ihtiyaçları olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Sınırsız doktor\nBüyüme'deki her şey\nÖzel katılım (onboarding)\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
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
}
