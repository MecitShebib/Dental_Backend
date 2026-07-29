<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageContent extends Model
{
    public const LOCALES = ['en', 'ar', 'tr'];

    protected $fillable = ['content'];

    protected $casts = [
        'content' => 'array',
    ];

    /**
     * One locale's content, merged over that locale's defaults so missing or
     * partially-filled fields always fall back to real launch copy instead
     * of rendering empty.
     */
    public static function current(string $locale = 'en'): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';
        $row = static::query()->first();
        $saved = $row?->content[$locale] ?? [];

        return static::mergeLocaleDefaults($saved, static::defaultsFor($locale));
    }

    /**
     * All three locales at once, each merged over its own defaults — what
     * the admin edit form needs to populate its three language tabs.
     */
    public static function currentAll(): array
    {
        $row = static::query()->first();
        $saved = $row?->content ?? [];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $result[$locale] = static::mergeLocaleDefaults($saved[$locale] ?? [], static::defaultsFor($locale));
        }

        return $result;
    }

    public static function defaults(): array
    {
        $result = [];
        foreach (self::LOCALES as $locale) {
            $result[$locale] = static::defaultsFor($locale);
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

    protected static function defaultsFor(string $locale): array
    {
        return match ($locale) {
            'ar' => static::arDefaults(),
            'tr' => static::trDefaults(),
            default => static::enDefaults(),
        };
    }

    protected static function enDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Now with AI-assisted treatment planning',
                'headline' => 'The clinical operating system for modern dental practices.',
                'subheadline' => 'Dentavaria unifies scheduling, treatment records, billing, and AI-assisted treatment planning in one secure platform — so your team spends less time on admin and more time with patients.',
                'primary_cta_label' => 'Book a demo',
                'secondary_cta_label' => 'See how it works',
            ],
            'trusted_by' => [
                'eyebrow' => 'Trusted by growing clinics',
                'names' => "Aurora Dental\nNorthshore Clinics\nBright Smile Group\nMeridian Health\nWillowbrook Dental",
            ],
            'about' => [
                'eyebrow' => 'Why Dentavaria exists',
                'headline' => "Built by people who've sat in the dentist's chair and the ops meeting.",
                'paragraphs' => "Most dental clinics run on a patchwork of paper charts, spreadsheets for billing, and scheduling tools that were never built for clinical workflows. Every handoff — from check-in to charting to collections — becomes a place for things to fall through the cracks.\n\nDentavaria brings it into one system designed specifically for how dental teams actually work: conflict-free scheduling, per-tooth treatment records, OTP-secured mobile access for staff, and an AI assistant that turns a doctor's case notes into a reviewable treatment plan in seconds.",
                'pull_quote' => 'Our mission is simple: give clinical teams back the hours they lose to admin, without asking doctors to trust a black box.',
            ],
            'features' => [
                ['title' => 'Smart scheduling', 'body' => 'Conflict-free booking across doctors and locations, with a real-time availability grid that respects every schedule.'],
                ['title' => 'Digital treatment records', 'body' => 'Per-tooth charting and odontogram history that stays in sync across every visit and every device.'],
                ['title' => 'AI treatment plan assistant', 'body' => 'Turn a spoken or typed case description into a structured, multi-session treatment plan — reviewed and confirmed by the doctor.'],
                ['title' => 'Multi-clinic management', 'body' => 'A multi-tenant architecture built for groups running multiple locations, each with its own subscription and limits.'],
                ['title' => 'Secure mobile access', 'body' => 'OTP-verified sign-in for every device and token-based sessions — no shared passwords, ever.'],
                ['title' => 'Financial clarity', 'body' => 'Charges, payments, and outstanding balances are tracked automatically for every patient, in real time.'],
            ],
            'how_it_works' => [
                ['title' => 'Set up your clinic', 'body' => 'Add doctors, working hours, and services in minutes — no implementation team required.'],
                ['title' => 'Patients book & check in', 'body' => 'Appointments become visits automatically. Double bookings are rejected before they happen.'],
                ['title' => 'AI drafts the plan', 'body' => 'Describe a case and get a structured, multi-session treatment plan the doctor can edit and confirm.'],
                ['title' => 'Track the outcome', 'body' => 'Payments, balances, and visit history stay in sync — no end-of-month reconciliation.'],
            ],
            'pricing' => [
                ['name' => 'Starter', 'description' => 'For solo practices getting off spreadsheets.', 'price_monthly' => '$49', 'price_yearly' => '$39', 'cta_label' => 'Get started', 'highlighted' => false, 'features' => "Up to 3 doctors\nScheduling & treatment records\nClient financial summaries\nEmail support"],
                ['name' => 'Growth', 'description' => 'For clinics ready to bring in AI-assisted planning.', 'price_monthly' => '$129', 'price_yearly' => '$103', 'cta_label' => 'Get started', 'highlighted' => true, 'features' => "Unlimited doctors\nAI treatment plan assistant\nMulti-location support\nPriority support"],
                ['name' => 'Enterprise', 'description' => 'For groups with custom compliance needs.', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'cta_label' => 'Talk to us', 'highlighted' => false, 'features' => "Dedicated onboarding\nCustom integrations\nSLA & compliance review\nVolume pricing"],
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

    protected static function arDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'الآن مع تخطيط علاجي مدعوم بالذكاء الاصطناعي',
                'headline' => 'نظام التشغيل السريري لعيادات الأسنان الحديثة.',
                'subheadline' => 'توحّد Dentavaria الجدولة وسجلات العلاج والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة — ليقضي فريقك وقتًا أقل في الأعمال الإدارية ووقتًا أكبر مع المرضى.',
                'primary_cta_label' => 'احجز عرضًا توضيحيًا',
                'secondary_cta_label' => 'شاهد كيف يعمل',
            ],
            'trusted_by' => [
                'eyebrow' => 'موثوق به من قبل عيادات متنامية',
                'names' => "Aurora Dental\nNorthshore Clinics\nBright Smile Group\nMeridian Health\nWillowbrook Dental",
            ],
            'about' => [
                'eyebrow' => 'لماذا توجد Dentavaria',
                'headline' => 'بناها أشخاص جلسوا على كرسي طبيب الأسنان وفي اجتماعات الإدارة أيضًا.',
                'paragraphs' => "تعمل معظم عيادات الأسنان بمزيج من الملفات الورقية وجداول بيانات للفوترة وأدوات جدولة لم تُصمم أصلاً لسير العمل السريري. كل تسليم بين المهام — من تسجيل الدخول إلى توثيق الحالة وصولاً إلى التحصيل — يصبح نقطة يمكن أن تضيع فيها الأمور.\n\nتجمع Dentavaria كل ذلك في نظام واحد مصمم خصيصًا لطريقة عمل فرق طب الأسنان: جدولة بلا تعارض، سجلات علاج لكل سن، وصول آمن للموظفين عبر رمز تحقق، ومساعد ذكاء اصطناعي يحوّل ملاحظات الطبيب إلى خطة علاج قابلة للمراجعة خلال ثوانٍ.",
                'pull_quote' => 'مهمتنا بسيطة: نُعيد لفرق العمل السريري الساعات التي تضيع في الأعمال الإدارية، دون أن نطلب من الأطباء الثقة بصندوق أسود.',
            ],
            'features' => [
                ['title' => 'جدولة ذكية', 'body' => 'حجز بلا تعارض بين الأطباء والفروع، مع شبكة توافر لحظية تحترم كل جدول عمل.'],
                ['title' => 'سجلات علاج رقمية', 'body' => 'رسم بياني للأسنان وسجل تخطيط الأسنان يبقى متزامنًا عبر كل زيارة وكل جهاز.'],
                ['title' => 'مساعد خطة العلاج بالذكاء الاصطناعي', 'body' => 'حوّل وصف الحالة المكتوب أو المنطوق إلى خطة علاج متعددة الجلسات ومنظمة — تتم مراجعتها وتأكيدها من الطبيب.'],
                ['title' => 'إدارة متعددة العيادات', 'body' => 'بنية متعددة المستأجرين مصممة للمجموعات التي تدير عدة فروع، لكل منها اشتراكه وحدوده الخاصة.'],
                ['title' => 'وصول آمن عبر الجوال', 'body' => 'تسجيل دخول موثّق برمز تحقق لكل جهاز، وجلسات قائمة على الرموز — بلا كلمات مرور مشتركة أبدًا.'],
                ['title' => 'وضوح مالي', 'body' => 'تُتابع الرسوم والمدفوعات والأرصدة المستحقة تلقائيًا لكل مريض، لحظيًا.'],
            ],
            'how_it_works' => [
                ['title' => 'أعدّ عيادتك', 'body' => 'أضف الأطباء وساعات العمل والخدمات خلال دقائق — دون الحاجة لفريق تنفيذ.'],
                ['title' => 'يحجز المرضى ويسجّلون الدخول', 'body' => 'تتحول المواعيد إلى زيارات تلقائيًا. تُرفض الحجوزات المتعارضة قبل حدوثها.'],
                ['title' => 'يصيغ الذكاء الاصطناعي الخطة', 'body' => 'صف الحالة واحصل على خطة علاج منظمة متعددة الجلسات يمكن للطبيب تعديلها وتأكيدها.'],
                ['title' => 'تابع النتيجة', 'body' => 'تبقى المدفوعات والأرصدة وسجل الزيارات متزامنة — دون تسوية في نهاية الشهر.'],
            ],
            'pricing' => [
                ['name' => 'أساسي', 'description' => 'للعيادات الفردية التي تريد التخلص من جداول البيانات.', 'price_monthly' => '$49', 'price_yearly' => '$39', 'cta_label' => 'ابدأ الآن', 'highlighted' => false, 'features' => "حتى 3 أطباء\nالجدولة وسجلات العلاج\nملخصات مالية للعملاء\nدعم عبر البريد الإلكتروني"],
                ['name' => 'نمو', 'description' => 'للعيادات المستعدة لاعتماد التخطيط المدعوم بالذكاء الاصطناعي.', 'price_monthly' => '$129', 'price_yearly' => '$103', 'cta_label' => 'ابدأ الآن', 'highlighted' => true, 'features' => "أطباء بلا حدود\nمساعد خطة العلاج بالذكاء الاصطناعي\nدعم متعدد الفروع\nدعم ذو أولوية"],
                ['name' => 'مؤسسات', 'description' => 'للمجموعات ذات متطلبات الامتثال الخاصة.', 'price_monthly' => 'مخصص', 'price_yearly' => 'مخصص', 'cta_label' => 'تواصل معنا', 'highlighted' => false, 'features' => "تأهيل مخصص\nتكاملات مخصصة\nمراجعة اتفاقية مستوى الخدمة والامتثال\nتسعير بالجملة"],
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

    protected static function trDefaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Artık yapay zeka destekli tedavi planlamasıyla',
                'headline' => 'Modern diş kliniklerinin klinik işletim sistemi.',
                'subheadline' => 'Dentavaria; randevu planlama, tedavi kayıtları, faturalandırma ve yapay zeka destekli tedavi planlamasını tek bir güvenli platformda birleştirir — ekibiniz idari işlere daha az, hastalara daha çok zaman ayırsın.',
                'primary_cta_label' => 'Demo talep edin',
                'secondary_cta_label' => 'Nasıl çalıştığını görün',
            ],
            'trusted_by' => [
                'eyebrow' => 'Büyüyen kliniklerin tercihi',
                'names' => "Aurora Dental\nNorthshore Clinics\nBright Smile Group\nMeridian Health\nWillowbrook Dental",
            ],
            'about' => [
                'eyebrow' => 'Dentavaria neden var',
                'headline' => 'Hem diş koltuğunda hem de operasyon toplantısında oturmuş kişiler tarafından kuruldu.',
                'paragraphs' => "Çoğu diş kliniği, klinik iş akışları için hiç tasarlanmamış kağıt dosyalar, faturalandırma için hesap tabloları ve randevu araçlarının bir karışımıyla çalışır. Kayıttan tedavi kaydına, tahsilata kadar her devir, işlerin gözden kaçabileceği bir nokta haline gelir.\n\nDentavaria bunların hepsini, diş hekimliği ekiplerinin gerçekte nasıl çalıştığına özel olarak tasarlanmış tek bir sistemde birleştirir: çakışmasız randevu planlama, diş bazında tedavi kayıtları, personel için OTP ile güvenli mobil erişim ve bir hekimin vaka notlarını saniyeler içinde gözden geçirilebilir bir tedavi planına dönüştüren bir yapay zeka asistanı.",
                'pull_quote' => 'Misyonumuz basit: doktorlardan bir kara kutuya güvenmelerini istemeden, klinik ekiplere idari işlerde kaybettikleri saatleri geri vermek.',
            ],
            'features' => [
                ['title' => 'Akıllı randevu planlama', 'body' => 'Her programa saygı gösteren gerçek zamanlı müsaitlik ızgarasıyla, doktorlar ve lokasyonlar arasında çakışmasız randevu.'],
                ['title' => 'Dijital tedavi kayıtları', 'body' => 'Her ziyaret ve her cihazda senkronize kalan diş bazlı grafik ve odontogram geçmişi.'],
                ['title' => 'Yapay zeka tedavi planı asistanı', 'body' => 'Sözlü veya yazılı bir vaka açıklamasını, hekim tarafından incelenip onaylanan yapılandırılmış, çok seanslı bir tedavi planına dönüştürün.'],
                ['title' => 'Çoklu klinik yönetimi', 'body' => 'Birden fazla lokasyonu işleten gruplar için tasarlanmış, her birinin kendi aboneliği ve limitleri olan çok kiracılı bir mimari.'],
                ['title' => 'Güvenli mobil erişim', 'body' => 'Her cihaz için OTP ile doğrulanmış giriş ve token tabanlı oturumlar — asla paylaşılan şifre yok.'],
                ['title' => 'Finansal netlik', 'body' => 'Her hasta için ücretler, ödemeler ve bakiyeler gerçek zamanlı olarak otomatik takip edilir.'],
            ],
            'how_it_works' => [
                ['title' => 'Kliniğinizi kurun', 'body' => 'Doktorları, çalışma saatlerini ve hizmetleri dakikalar içinde ekleyin — kurulum ekibi gerekmez.'],
                ['title' => 'Hastalar randevu alır ve giriş yapar', 'body' => 'Randevular otomatik olarak ziyarete dönüşür. Çakışan randevular gerçekleşmeden reddedilir.'],
                ['title' => 'Yapay zeka planı hazırlar', 'body' => 'Bir vakayı tarif edin ve hekimin düzenleyip onaylayabileceği yapılandırılmış, çok seanslı bir tedavi planı alın.'],
                ['title' => 'Sonucu takip edin', 'body' => 'Ödemeler, bakiyeler ve ziyaret geçmişi senkronize kalır — ay sonu mutabakatı gerekmez.'],
            ],
            'pricing' => [
                ['name' => 'Başlangıç', 'description' => 'Hesap tablolarından kurtulmak isteyen bireysel klinikler için.', 'price_monthly' => '$49', 'price_yearly' => '$39', 'cta_label' => 'Başlayın', 'highlighted' => false, 'features' => "3 doktora kadar\nRandevu planlama ve tedavi kayıtları\nHasta finansal özetleri\nE-posta desteği"],
                ['name' => 'Büyüme', 'description' => 'Yapay zeka destekli planlamayı devreye almaya hazır klinikler için.', 'price_monthly' => '$129', 'price_yearly' => '$103', 'cta_label' => 'Başlayın', 'highlighted' => true, 'features' => "Sınırsız doktor\nYapay zeka tedavi planı asistanı\nÇoklu lokasyon desteği\nÖncelikli destek"],
                ['name' => 'Kurumsal', 'description' => 'Özel uyumluluk gereksinimleri olan gruplar için.', 'price_monthly' => 'Özel', 'price_yearly' => 'Özel', 'cta_label' => 'Bize ulaşın', 'highlighted' => false, 'features' => "Özel kurulum desteği\nÖzel entegrasyonlar\nSLA ve uyumluluk incelemesi\nToplu fiyatlandırma"],
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
}
