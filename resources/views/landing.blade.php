@php
    $locale = $locale ?? 'en';
    $isRtl = $locale === 'ar';

    $ui = [
        'en' => [
            'nav_home' => 'Home', 'nav_features' => 'Features', 'nav_how' => 'How it works', 'nav_pricing' => 'Pricing', 'nav_faq' => 'FAQ', 'nav_contact' => 'Contact',
            'nav_cta' => 'Book a demo',
            'hero_badge_1' => 'No credit card required', 'hero_badge_2' => 'Live in under a week',
            'features_eyebrow' => 'Features', 'features_headline' => "Everything a modern practice needs, nothing it doesn't.",
            'how_eyebrow' => 'How it works', 'how_headline' => 'From setup to a confirmed plan, in four steps.',
            'pricing_eyebrow' => 'Pricing', 'pricing_headline' => 'Simple pricing that scales with your clinic.',
            'billing_monthly' => 'Monthly', 'billing_yearly' => 'Yearly', 'billing_save' => '— save 20%', 'price_suffix' => '/ mo per clinic', 'most_popular' => 'Most popular',
            'testimonials_eyebrow' => 'Testimonials', 'testimonials_headline' => 'Clinics that switched, and never looked back.',
            'faq_eyebrow' => 'FAQ', 'faq_headline' => 'Questions, answered.',
            'footer_product' => 'Product', 'footer_company' => 'Company', 'footer_legal' => 'Legal',
            'footer_about' => 'About', 'footer_contact' => 'Contact',
            'footer_privacy' => 'Privacy policy', 'footer_terms' => 'Terms of service', 'footer_rights' => 'All rights reserved.',
            'meta_description' => 'Dentavaria is the clinical operating system for modern dental practices — scheduling, treatment records, billing, and AI-assisted treatment planning in one secure platform.',
            'meta_title' => 'Dentavaria — The clinical operating system for modern dental practices',
            'form_name' => 'Your name', 'form_email' => 'Email address',
            'field_required' => 'This field is required.', 'nav_login' => 'Log in', 'close' => 'Close',
            'nav_api_docs' => 'API Documentation', 'nav_admin' => 'Go to admin panel', 'nav_dashboard' => 'Go to dashboard', 'nav_options' => 'Options',
        ],
        'ar' => [
            'nav_home' => 'الرئيسية', 'nav_features' => 'المزايا', 'nav_how' => 'كيف يعمل', 'nav_pricing' => 'الأسعار', 'nav_faq' => 'الأسئلة الشائعة', 'nav_contact' => 'تواصل معنا',
            'nav_cta' => 'احجز عرضًا توضيحيًا',
            'hero_badge_1' => 'لا حاجة لبطاقة ائتمان', 'hero_badge_2' => 'جاهز خلال أقل من أسبوع',
            'features_eyebrow' => 'المزايا', 'features_headline' => 'كل ما تحتاجه عيادة حديثة، ولا شيء أكثر من ذلك.',
            'how_eyebrow' => 'كيف يعمل', 'how_headline' => 'من الإعداد إلى خطة مؤكدة، في أربع خطوات.',
            'pricing_eyebrow' => 'الأسعار', 'pricing_headline' => 'تسعير بسيط يتناسب مع نمو عيادتك.',
            'billing_monthly' => 'شهري', 'billing_yearly' => 'سنوي', 'billing_save' => '— وفّر 20%', 'price_suffix' => '/ شهريًا لكل عيادة', 'most_popular' => 'الأكثر شيوعًا',
            'testimonials_eyebrow' => 'آراء العملاء', 'testimonials_headline' => 'عيادات انتقلت إلينا ولم تندم أبدًا.',
            'faq_eyebrow' => 'الأسئلة الشائعة', 'faq_headline' => 'أسئلة، وأجوبتها.',
            'footer_product' => 'المنتج', 'footer_company' => 'الشركة', 'footer_legal' => 'قانوني',
            'footer_about' => 'من نحن', 'footer_contact' => 'تواصل معنا',
            'footer_privacy' => 'سياسة الخصوصية', 'footer_terms' => 'شروط الخدمة', 'footer_rights' => 'جميع الحقوق محفوظة.',
            'meta_description' => 'Dentavaria هو نظام التشغيل السريري لعيادات الأسنان الحديثة — الجدولة وسجلات العلاج والفوترة والتخطيط العلاجي المدعوم بالذكاء الاصطناعي في منصة آمنة واحدة.',
            'meta_title' => 'Dentavaria — نظام التشغيل السريري لعيادات الأسنان الحديثة',
            'form_name' => 'اسمك', 'form_email' => 'البريد الإلكتروني',
            'field_required' => 'هذا الحقل مطلوب.', 'nav_login' => 'تسجيل الدخول', 'close' => 'إغلاق',
            'nav_api_docs' => 'وثائق API', 'nav_admin' => 'الذهاب إلى لوحة التحكم', 'nav_dashboard' => 'الذهاب إلى لوحة القيادة', 'nav_options' => 'خيارات',
        ],
        'tr' => [
            'nav_home' => 'Ana sayfa', 'nav_features' => 'Özellikler', 'nav_how' => 'Nasıl çalışır', 'nav_pricing' => 'Fiyatlandırma', 'nav_faq' => 'SSS', 'nav_contact' => 'İletişim',
            'nav_cta' => 'Demo talep edin',
            'hero_badge_1' => 'Kredi kartı gerekmez', 'hero_badge_2' => 'Bir haftadan kısa sürede hazır',
            'features_eyebrow' => 'Özellikler', 'features_headline' => 'Modern bir kliniğin ihtiyaç duyduğu her şey, fazlası değil.',
            'how_eyebrow' => 'Nasıl çalışır', 'how_headline' => 'Kurulumdan onaylı bir plana, dört adımda.',
            'pricing_eyebrow' => 'Fiyatlandırma', 'pricing_headline' => 'Kliniğinizle birlikte ölçeklenen basit fiyatlandırma.',
            'billing_monthly' => 'Aylık', 'billing_yearly' => 'Yıllık', 'billing_save' => '— %20 tasarruf', 'price_suffix' => '/ ay, klinik başına', 'most_popular' => 'En popüler',
            'testimonials_eyebrow' => 'Referanslar', 'testimonials_headline' => 'Geçiş yapan ve asla geri dönmeyen klinikler.',
            'faq_eyebrow' => 'SSS', 'faq_headline' => 'Sorular, yanıtlandı.',
            'footer_product' => 'Ürün', 'footer_company' => 'Şirket', 'footer_legal' => 'Yasal',
            'footer_about' => 'Hakkımızda', 'footer_contact' => 'İletişim',
            'footer_privacy' => 'Gizlilik politikası', 'footer_terms' => 'Kullanım şartları', 'footer_rights' => 'Tüm hakları saklıdır.',
            'meta_description' => 'Dentavaria, modern diş kliniklerinin klinik işletim sistemidir — randevu planlama, tedavi kayıtları, faturalandırma ve yapay zeka destekli tedavi planlaması tek bir güvenli platformda.',
            'meta_title' => 'Dentavaria — Modern diş kliniklerinin klinik işletim sistemi',
            'form_name' => 'Adınız', 'form_email' => 'E-posta adresi',
            'field_required' => 'Bu alan zorunludur.', 'nav_login' => 'Giriş yap', 'close' => 'Kapat',
            'nav_api_docs' => 'API Dokümantasyonu', 'nav_admin' => 'Yönetim paneline git', 'nav_dashboard' => 'Panele git', 'nav_options' => 'Seçenekler',
        ],
    ][$locale];

    $languages = ['en' => 'EN', 'ar' => 'AR', 'tr' => 'TR'];
    $frontendLoginUrl = rtrim(config('app.frontend_url'), '/') . '/login';
    $isAdminLoggedIn = auth()->check() && auth()->user()->isProjectAdmin() && auth()->user()->isActive();

    $autoOpenModal = null;
    if (session('inquiry_success') === 'contact') {
        $autoOpenModal = 'contact-success-modal';
    } elseif (session('inquiry_success') === 'quote') {
        $autoOpenModal = 'quote-success-modal';
    } elseif ($errors->any() && old('_form') === 'contact') {
        $autoOpenModal = 'contact-modal';
    } elseif ($errors->any() && old('_form') === 'quote') {
        $autoOpenModal = 'quote-modal';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $ui['meta_description'] }}">
    <title>{{ $ui['meta_title'] }}</title>
    <script>
        (function () {
            var stored = localStorage.getItem('dentavaria-theme');
            if (stored === 'light' || stored === 'dark') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|cairo:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(3%, -4%) scale(1.08); }
            66% { transform: translate(-3%, 3%) scale(0.95); }
        }
        .animate-blob { animation: blob 16s ease-in-out infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }

        [data-reveal] {
            opacity: 0;
            transform: translateY(1.5rem);
            transition: opacity .7s ease, transform .7s ease;
        }
        [data-reveal].is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .faq-panel {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows .3s ease;
        }
        details[open] .faq-panel { grid-template-rows: 1fr; }
        .faq-panel > div { overflow: hidden; }
        details .chevron { transition: transform .3s ease; }
        details[open] .chevron { transform: rotate(180deg); }

        #billing-toggle:checked ~ .track { background-color: #10b981; }
        #billing-toggle:checked ~ .dot { transform: translateX(1.25rem); }
        [dir="rtl"] #billing-toggle:checked ~ .dot { transform: translateX(-1.25rem); }
        .price-yearly { display: none; }
        .plans.is-yearly .price-monthly { display: none; }
        .plans.is-yearly .price-yearly { display: inline; }

        [dir="rtl"] body { font-family: 'Cairo', ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }

        dialog.modal {
            position: fixed;
            inset: 0;
            margin: auto;
            width: min(560px, calc(100% - 2rem));
            max-height: calc(100vh - 2rem);
            border: 0;
            border-radius: 22px;
            padding: 0;
            background: transparent;
            color: #f1f5f9;
        }
        dialog.modal::backdrop { background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); }
        .modal-card {
            background: #0a0f0d;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 22px;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-close-btn {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #f1f5f9;
            border-radius: 999px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            flex: 0 0 auto;
        }
        .modal-close-btn:hover { background: rgba(255, 255, 255, 0.1); }
        .field-error { color: #f87171; font-size: .75rem; margin-top: .35rem; }

        /* Theme toggle button */
        .theme-toggle-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #cbd5e1;
            cursor: pointer;
            flex: 0 0 auto;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .theme-toggle-btn:hover { background: rgba(255, 255, 255, 0.08); }
        .theme-toggle-btn .icon-moon { display: none; }
        html[data-theme="light"] .theme-toggle-btn .icon-sun { display: none; }
        html[data-theme="light"] .theme-toggle-btn .icon-moon { display: block; }

        /* Light theme — overrides Tailwind's dark-mode-hardcoded utility classes by
           exact class-token match, so the markup itself never has to be touched. */
        html[data-theme="light"] body { background-color: #f8fafc; }
        html[data-theme="light"] .dot-grid { background-image: radial-gradient(circle at 1px 1px, rgba(15, 23, 42, 0.06) 1px, transparent 0); }

        html[data-theme="light"] [class~="bg-[#05070a]"] { background-color: #f8fafc; }
        html[data-theme="light"] header[class~="bg-[#05070a]/70"] { background-color: rgba(248, 250, 252, 0.75); }
        html[data-theme="light"] [class~="bg-[#0a0f0d]"] { background-color: #ffffff; }
        html[data-theme="light"] [class~="bg-[#0a0f0d]/95"] { background-color: rgba(255, 255, 255, 0.97); }

        html[data-theme="light"] [class~="text-white"] { color: #0f172a; }
        html[data-theme="light"] [class~="text-slate-200"] { color: #1e293b; }
        html[data-theme="light"] [class~="text-slate-300"] { color: #334155; }
        html[data-theme="light"] [class~="text-slate-400"] { color: #475569; }
        html[data-theme="light"] [class~="text-slate-500"] { color: #64748b; }
        html[data-theme="light"] [class~="text-slate-600"] { color: #94a3b8; }
        html[data-theme="light"] [class~="placeholder:text-slate-600"]::placeholder { color: #94a3b8; }

        html[data-theme="light"] [class~="border-white/5"] { border-color: rgba(15, 23, 42, 0.07); }
        html[data-theme="light"] [class~="border-white/10"] { border-color: rgba(15, 23, 42, 0.10); }
        html[data-theme="light"] [class~="border-white/15"] { border-color: rgba(15, 23, 42, 0.14); }
        html[data-theme="light"] [class~="border-white/30"] { border-color: rgba(15, 23, 42, 0.26); }

        html[data-theme="light"] [class~="bg-white/[0.02]"] { background-color: rgba(15, 23, 42, 0.03); }
        html[data-theme="light"] [class~="bg-white/[0.03]"] { background-color: rgba(15, 23, 42, 0.035); }
        html[data-theme="light"] [class~="bg-white/[0.04]"] { background-color: rgba(15, 23, 42, 0.045); }
        html[data-theme="light"] [class~="bg-white/5"] { background-color: rgba(15, 23, 42, 0.05); }
        html[data-theme="light"] [class~="bg-white/10"] { background-color: rgba(15, 23, 42, 0.08); }
        html[data-theme="light"] [class~="bg-white/15"] { background-color: rgba(15, 23, 42, 0.12); }

        html[data-theme="light"] [class~="hover:text-white"]:hover { color: #0f172a; }
        html[data-theme="light"] [class~="hover:text-slate-300"]:hover { color: #334155; }
        html[data-theme="light"] [class~="hover:border-white/30"]:hover { border-color: rgba(15, 23, 42, 0.26); }
        html[data-theme="light"] [class~="hover:bg-white/5"]:hover { background-color: rgba(15, 23, 42, 0.05); }
        html[data-theme="light"] [class~="hover:bg-white/[0.04]"]:hover { background-color: rgba(15, 23, 42, 0.05); }

        html[data-theme="light"] .modal-card {
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.12);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        html[data-theme="light"] dialog.modal { color: #0f172a; }
        html[data-theme="light"] dialog.modal::backdrop { background: rgba(15, 23, 42, 0.35); }
        html[data-theme="light"] .modal-close-btn {
            border-color: rgba(15, 23, 42, 0.12);
            background: rgba(15, 23, 42, 0.04);
            color: #0f172a;
        }
        html[data-theme="light"] .modal-close-btn:hover { background: rgba(15, 23, 42, 0.08); }

        html[data-theme="light"] .theme-toggle-btn {
            border-color: rgba(15, 23, 42, 0.12);
            background: rgba(15, 23, 42, 0.04);
            color: #334155;
        }
        html[data-theme="light"] .theme-toggle-btn:hover { background: rgba(15, 23, 42, 0.08); }
    </style>
</head>
<body id="top" class="bg-[#05070a] text-slate-200 font-sans antialiased selection:bg-emerald-500/30 selection:text-white">

    {{-- Ambient background --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-40 -left-32 h-96 w-96 rounded-full bg-emerald-600/20 blur-3xl animate-blob"></div>
        <div class="absolute top-1/3 -right-32 h-96 w-96 rounded-full bg-teal-500/15 blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-1/4 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl animate-blob animation-delay-4000"></div>
        <div class="dot-grid absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)] [background-size:32px_32px] opacity-40"></div>
    </div>

    {{-- Nav --}}
    <header class="sticky top-0 z-50 border-b border-white/5 bg-[#05070a]/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="#top" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-teal-600 shadow-lg shadow-emerald-500/20">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="white"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight text-white">Dentavaria</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm text-slate-300 md:flex">
                <a href="#top" class="transition hover:text-white">{{ $ui['nav_home'] }}</a>
                <a href="#features" class="transition hover:text-white">{{ $ui['nav_features'] }}</a>
                <a href="#how-it-works" class="transition hover:text-white">{{ $ui['nav_how'] }}</a>
                <a href="#pricing" class="transition hover:text-white">{{ $ui['nav_pricing'] }}</a>
                <a href="#faq" class="transition hover:text-white">{{ $ui['nav_faq'] }}</a>
                <button type="button" data-open-modal="contact-modal" class="bg-transparent border-0 p-0 cursor-pointer transition hover:text-white">{{ $ui['nav_contact'] }}</button>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <button type="button" class="theme-toggle-btn" data-theme-toggle aria-label="Toggle light/dark theme">
                    <svg class="icon-sun h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15a5 5 0 100-10 5 5 0 000 10zM10 0a1 1 0 011 1v1a1 1 0 11-2 0V1a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.05 3.05a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zm11.78 11.78a1 1 0 011.415 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zM0 10a1 1 0 011-1h1a1 1 0 110 2H1a1 1 0 01-1-1zm17 0a1 1 0 011-1h1a1 1 0 110 2h-1a1 1 0 01-1-1zM3.05 16.95a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0zM14.83 5.17a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0z"/></svg>
                    <svg class="icon-moon h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>
                <div class="flex items-center overflow-hidden rounded-full border border-white/10 text-xs font-semibold text-slate-400">
                    @foreach ($languages as $code => $label)
                        <a href="{{ route('home', $code) }}" class="px-2.5 py-1.5 transition {{ $locale === $code ? 'bg-white/10 text-white' : 'hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
                @if ($isAdminLoggedIn)
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_admin'] }}
                    </a>
                @else
                    <a href="{{ $frontendLoginUrl }}" data-auth-cta="guest" class="hidden text-sm font-medium text-slate-300 transition hover:text-white sm:block">{{ $ui['nav_login'] }}</a>
                    <button type="button" data-open-modal="contact-modal" data-auth-cta="guest" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_cta'] }}
                    </button>
                    <a href="{{ rtrim(config('app.frontend_url'), '/') }}" data-auth-cta="app-user" class="hidden rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_dashboard'] }}
                    </a>
                @endif
            </div>

            <button type="button" id="mobile-menu-toggle" class="flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white md:hidden" aria-expanded="false" aria-controls="mobile-menu-panel">
                {{ $ui['nav_options'] }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>

        <div id="mobile-menu-panel" class="hidden border-t border-white/5 px-6 py-6 md:hidden">
            <nav class="flex flex-col gap-4 text-base text-slate-300">
                <a href="#top" class="transition hover:text-white">{{ $ui['nav_home'] }}</a>
                <a href="#features" class="transition hover:text-white">{{ $ui['nav_features'] }}</a>
                <a href="#how-it-works" class="transition hover:text-white">{{ $ui['nav_how'] }}</a>
                <a href="#pricing" class="transition hover:text-white">{{ $ui['nav_pricing'] }}</a>
                <a href="#faq" class="transition hover:text-white">{{ $ui['nav_faq'] }}</a>
                <button type="button" data-open-modal="contact-modal" class="bg-transparent border-0 p-0 text-start cursor-pointer transition hover:text-white">{{ $ui['nav_contact'] }}</button>
            </nav>

            <div class="mt-6 flex items-center justify-between border-t border-white/5 pt-6">
                <button type="button" class="theme-toggle-btn" data-theme-toggle aria-label="Toggle light/dark theme">
                    <svg class="icon-sun h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15a5 5 0 100-10 5 5 0 000 10zM10 0a1 1 0 011 1v1a1 1 0 11-2 0V1a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.05 3.05a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zm11.78 11.78a1 1 0 011.415 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 01-1.414 0zM0 10a1 1 0 011-1h1a1 1 0 110 2H1a1 1 0 01-1-1zm17 0a1 1 0 011-1h1a1 1 0 110 2h-1a1 1 0 01-1-1zM3.05 16.95a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0zM14.83 5.17a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0z"/></svg>
                    <svg class="icon-moon h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>
                <div class="flex items-center overflow-hidden rounded-full border border-white/10 text-xs font-semibold text-slate-400">
                    @foreach ($languages as $code => $label)
                        <a href="{{ route('home', $code) }}" class="px-2.5 py-1.5 transition {{ $locale === $code ? 'bg-white/10 text-white' : 'hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3">
                @if ($isAdminLoggedIn)
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_admin'] }}
                    </a>
                @else
                    <a href="{{ $frontendLoginUrl }}" data-auth-cta="guest" class="rounded-full border border-white/15 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:border-white/30">
                        {{ $ui['nav_login'] }}
                    </a>
                    <button type="button" data-open-modal="contact-modal" data-auth-cta="guest" class="rounded-full bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_cta'] }}
                    </button>
                    <a href="{{ rtrim(config('app.frontend_url'), '/') }}" data-auth-cta="app-user" class="hidden rounded-full bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">
                        {{ $ui['nav_dashboard'] }}
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="relative">

        {{-- Hero --}}
        <section class="relative overflow-hidden px-6 pt-20 pb-28">
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <div data-reveal>
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-4 py-1.5 text-xs font-medium text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            {{ $content['hero']['eyebrow'] }}
                        </div>

                        <h1 class="mt-6 text-5xl font-semibold leading-[1.05] tracking-tight text-white sm:text-6xl">
                            {{ $content['hero']['headline'] }}
                        </h1>

                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-400">
                            {{ $content['hero']['subheadline'] }}
                        </p>

                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <button type="button" data-open-modal="contact-modal" class="rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 px-6 py-3.5 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:shadow-emerald-500/40 hover:brightness-110">
                                {{ $content['hero']['primary_cta_label'] }}
                            </button>
                            <a href="#how-it-works" class="rounded-full border border-white/15 px-6 py-3.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">
                                {{ $content['hero']['secondary_cta_label'] }}
                            </a>
                        </div>

                        <div class="mt-10 flex flex-wrap items-center gap-6 text-sm text-slate-500">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                {{ $ui['hero_badge_1'] }}
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                {{ $ui['hero_badge_2'] }}
                            </div>
                        </div>
                    </div>

                    {{-- Product mockup --}}
                    <div class="relative" data-reveal>
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-2 shadow-2xl shadow-black/40 backdrop-blur">
                            <div class="rounded-xl border border-white/5 bg-[#0a0f0d] p-5">
                                <div class="flex items-center justify-between border-b border-white/5 pb-4">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full bg-red-400/70"></span>
                                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400/70"></span>
                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70"></span>
                                    </div>
                                    <span class="text-xs text-slate-500">Today · Dr. Aylin Kaya</span>
                                </div>

                                <div class="mt-4 space-y-2.5">
                                    <div class="flex items-center justify-between rounded-lg bg-white/[0.04] px-3 py-2.5">
                                        <div>
                                            <p class="text-sm font-medium text-white">09:00 — Elif Demir</p>
                                            <p class="text-xs text-slate-500">Root canal · Session 2 of 3</p>
                                        </div>
                                        <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-[11px] font-medium text-emerald-300">Checked in</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg bg-white/[0.04] px-3 py-2.5">
                                        <div>
                                            <p class="text-sm font-medium text-white">10:15 — Mert Aydın</p>
                                            <p class="text-xs text-slate-500">Cleaning · New patient</p>
                                        </div>
                                        <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-slate-300">Scheduled</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg bg-white/[0.04] px-3 py-2.5 opacity-60">
                                        <div>
                                            <p class="text-sm font-medium text-white">11:30 — Zeynep Kara</p>
                                            <p class="text-xs text-slate-500">Consultation</p>
                                        </div>
                                        <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-slate-300">Scheduled</span>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-3 gap-2.5">
                                    <div class="rounded-lg border border-white/5 bg-white/[0.02] p-3">
                                        <p class="text-[11px] text-slate-500">Today's visits</p>
                                        <p class="mt-1 text-lg font-semibold text-white">12</p>
                                    </div>
                                    <div class="rounded-lg border border-white/5 bg-white/[0.02] p-3">
                                        <p class="text-[11px] text-slate-500">Collected</p>
                                        <p class="mt-1 text-lg font-semibold text-white">₺18.4k</p>
                                    </div>
                                    <div class="rounded-lg border border-white/5 bg-white/[0.02] p-3">
                                        <p class="text-[11px] text-slate-500">Open balance</p>
                                        <p class="mt-1 text-lg font-semibold text-white">₺2.1k</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Floating AI plan card --}}
                        <div class="absolute -bottom-8 {{ $isRtl ? '-right-8' : '-left-8' }} hidden w-64 rounded-xl border border-white/10 bg-[#0a0f0d]/95 p-4 shadow-2xl shadow-black/40 backdrop-blur sm:block">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-gradient-to-br from-emerald-400 to-teal-600 text-[10px] font-bold text-slate-950">AI</span>
                                <p class="text-xs font-semibold text-white">Treatment plan drafted</p>
                            </div>
                            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                                3 sessions proposed for upper-left molar restoration. Ready for doctor review.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Trusted by --}}
        <section class="border-y border-white/5 bg-white/[0.02] px-6 py-12" data-reveal>
            <div class="mx-auto max-w-7xl">
                <p class="text-center text-xs font-medium uppercase tracking-widest text-slate-500">{{ $content['trusted_by']['eyebrow'] }}</p>
                <div class="mt-8 grid grid-cols-2 gap-8 text-center sm:grid-cols-3 lg:grid-cols-5">
                    @foreach (preg_split('/\r?\n/', trim($content['trusted_by']['names'])) as $name)
                        @if (trim($name) !== '')
                            <div class="text-lg font-semibold tracking-tight text-slate-600 transition hover:text-slate-300">{{ trim($name) }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        {{-- About --}}
        <section class="px-6 py-28">
            <div class="mx-auto grid max-w-7xl gap-16 lg:grid-cols-2">
                <div data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $content['about']['eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ $content['about']['headline'] }}
                    </h2>
                </div>
                <div class="space-y-5 text-lg leading-relaxed text-slate-400" data-reveal>
                    @foreach (preg_split('/\r?\n\r?\n/', trim($content['about']['paragraphs'])) as $paragraph)
                        @if (trim($paragraph) !== '')
                            <p>{{ trim($paragraph) }}</p>
                        @endif
                    @endforeach
                    <p class="{{ $isRtl ? 'border-r-2 pr-5' : 'border-l-2 pl-5' }} border-emerald-400/40 text-white">
                        {{ $content['about']['pull_quote'] }}
                    </p>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section id="features" class="border-t border-white/5 px-6 py-28">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $ui['features_eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $ui['features_headline'] }}</h2>
                </div>

                <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        // Icons are fixed per slot (not admin-editable); title/body come from $content.
                        $featureIcons = [
                            'M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                            'M13 10V3L4 14h7v7l9-11h-7z',
                            'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2M5 21H3m16 0h-5v-4a2 2 0 00-2-2h0a2 2 0 00-2 2v4H5',
                            'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                            'M9 8h6m-5 4h4m-7 8h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z',
                            'M9 7h6m-6 4h6m-6 4h4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
                            'M9 3v4a1 1 0 001 1h4M6 21h12a2 2 0 002-2V8l-5-5H6a2 2 0 00-2 2v14a2 2 0 002 2zm3-8s1 2 3 2 3-2 3-2m-6 4s1 2 3 2 3-2 3-2',
                            'M8 9l-4 3 4 3m8-6l4 3-4 3m-6-9l-2 12',
                        ];
                    @endphp

                    @foreach ($content['features'] as $i => $feature)
                        <div class="group rounded-2xl border border-white/10 bg-white/[0.02] p-6 transition hover:-translate-y-1 hover:border-emerald-400/30 hover:bg-white/[0.04]" data-reveal>
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400/20 to-teal-600/20 text-emerald-300 transition group-hover:from-emerald-400/30 group-hover:to-teal-600/30">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $featureIcons[$i] ?? $featureIcons[0] }}"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-white">{{ $feature['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $feature['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section id="how-it-works" class="border-t border-white/5 bg-white/[0.02] px-6 py-28">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $ui['how_eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $ui['how_headline'] }}</h2>
                </div>

                <div class="mt-16 grid gap-10 lg:grid-cols-4">
                    @foreach ($content['how_it_works'] as $i => $step)
                        <div class="relative" data-reveal>
                            @if (!$loop->last)
                                <div class="absolute {{ $isRtl ? 'right-0' : 'left-0' }} top-6 hidden h-px w-full bg-gradient-to-r from-emerald-400/30 to-transparent lg:block"></div>
                            @endif
                            <span class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full border border-emerald-400/30 bg-[#05070a] text-sm font-semibold text-emerald-300">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="mt-5 text-lg font-semibold text-white">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $step['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Pricing --}}
        <section id="pricing" class="border-t border-white/5 px-6 py-28">
            <div class="mx-auto max-w-7xl">
                <div class="mx-auto max-w-2xl text-center" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $ui['pricing_eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $ui['pricing_headline'] }}</h2>

                    <label class="mt-8 inline-flex cursor-pointer items-center gap-3">
                        <span class="text-sm text-slate-400">{{ $ui['billing_monthly'] }}</span>
                        <span class="relative inline-block">
                            <input type="checkbox" id="billing-toggle" class="peer sr-only">
                            <span class="track block h-6 w-11 rounded-full bg-white/15 transition-colors"></span>
                            <span class="dot absolute start-1 top-1 h-4 w-4 rounded-full bg-white transition-transform"></span>
                        </span>
                        <span class="text-sm text-slate-400">{{ $ui['billing_yearly'] }} <span class="text-emerald-400">{{ $ui['billing_save'] }}</span></span>
                    </label>
                </div>

                <div class="plans mt-14 grid gap-6 lg:grid-cols-3">
                    @foreach ($content['pricing'] as $tier)
                        @if ($tier['highlighted'])
                            <div class="relative rounded-2xl border border-emerald-400/40 bg-gradient-to-b from-emerald-400/[0.07] to-transparent p-8 shadow-2xl shadow-emerald-500/10" data-reveal>
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 px-3 py-1 text-xs font-semibold text-slate-950">{{ $ui['most_popular'] }}</span>
                                <h3 class="text-lg font-semibold text-white">{{ $tier['name'] }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ $tier['description'] }}</p>
                                <p class="mt-6">
                                    <span class="price-monthly text-4xl font-semibold text-white">{{ $tier['price_monthly'] }}</span>
                                    <span class="price-yearly text-4xl font-semibold text-white">{{ $tier['price_yearly'] }}</span>
                                    <span class="text-sm text-slate-500">{{ $ui['price_suffix'] }}</span>
                                </p>
                                <a href="#cta" class="mt-8 block rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 py-3 text-center text-sm font-semibold text-slate-950 transition hover:brightness-110">{{ $tier['cta_label'] }}</a>
                                <ul class="mt-8 space-y-3 text-sm text-slate-300">
                                    @foreach (preg_split('/\r?\n/', trim($tier['features'])) as $line)
                                        @if (trim($line) !== '')
                                            <li>{{ trim($line) }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-8" data-reveal>
                                <h3 class="text-lg font-semibold text-white">{{ $tier['name'] }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ $tier['description'] }}</p>
                                <p class="mt-6">
                                    <span class="price-monthly text-4xl font-semibold text-white">{{ $tier['price_monthly'] }}</span>
                                    <span class="price-yearly text-4xl font-semibold text-white">{{ $tier['price_yearly'] }}</span>
                                    <span class="text-sm text-slate-500">{{ $ui['price_suffix'] }}</span>
                                </p>
                                <a href="#cta" class="mt-8 block rounded-full border border-white/15 py-3 text-center text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">{{ $tier['cta_label'] }}</a>
                                <ul class="mt-8 space-y-3 text-sm text-slate-400">
                                    @foreach (preg_split('/\r?\n/', trim($tier['features'])) as $line)
                                        @if (trim($line) !== '')
                                            <li>{{ trim($line) }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Benefits --}}
        <section class="border-t border-white/5 bg-white/[0.02] px-6 py-28">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($content['benefits'] as $benefit)
                        <div data-reveal>
                            <div class="h-1 w-10 rounded-full bg-gradient-to-r from-emerald-400 to-teal-600"></div>
                            <h3 class="mt-5 text-lg font-semibold text-white">{{ $benefit['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $benefit['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Testimonials --}}
        <section class="border-t border-white/5 px-6 py-28">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $ui['testimonials_eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $ui['testimonials_headline'] }}</h2>
                </div>

                <div class="mt-14 grid gap-6 lg:grid-cols-3">
                    @foreach ($content['testimonials'] as $t)
                        <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-7" data-reveal>
                            <div class="flex gap-1 text-amber-400">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.6 6.1.6-4.6 4.1 1.3 6-5.4-3.1-5.4 3.1 1.3-6-4.6-4.1 6.1-.6z"/></svg>
                                @endfor
                            </div>
                            <p class="mt-4 text-sm leading-relaxed text-slate-300">&ldquo;{{ $t['quote'] }}&rdquo;</p>
                            <div class="mt-6 flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-600/20 text-xs font-semibold text-emerald-300">{{ $t['initials'] }}</span>
                                <div>
                                    <p class="text-sm font-medium text-white">{{ $t['name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $t['role'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section id="faq" class="border-t border-white/5 bg-white/[0.02] px-6 py-28">
            <div class="mx-auto max-w-3xl">
                <div class="text-center" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $ui['faq_eyebrow'] }}</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $ui['faq_headline'] }}</h2>
                </div>

                <div class="mt-14 space-y-3">
                    @foreach ($content['faq'] as $faq)
                        <details class="group rounded-xl border border-white/10 bg-white/[0.02] px-6 py-2 open:border-emerald-400/20" data-reveal>
                            <summary class="flex cursor-pointer list-none items-center justify-between py-3 text-sm font-medium text-white">
                                {{ $faq['question'] }}
                                <svg class="chevron h-4 w-4 flex-shrink-0 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.2 7.2a1 1 0 011.4 0L10 10.6l3.4-3.4a1 1 0 111.4 1.4l-4.1 4.1a1 1 0 01-1.4 0L5.2 8.6a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                            </summary>
                            <div class="faq-panel">
                                <div>
                                    <p class="pb-4 text-sm leading-relaxed text-slate-400">{{ $faq['answer'] }}</p>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section id="cta" class="border-t border-white/5 px-6 py-28">
            <div class="mx-auto max-w-4xl rounded-3xl border border-white/10 bg-gradient-to-b from-emerald-400/[0.08] to-transparent p-14 text-center" data-reveal>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $content['final_cta']['headline'] }}</h2>
                <p class="mx-auto mt-4 max-w-xl text-slate-400">{{ $content['final_cta']['subtext'] }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <button type="button" data-open-modal="contact-modal" class="rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 px-7 py-3.5 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:shadow-emerald-500/40 hover:brightness-110">
                        {{ $content['final_cta']['button_label'] }}
                    </button>
                    <button type="button" data-open-modal="quote-modal" class="rounded-full border border-white/15 px-7 py-3.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">
                        {{ $content['quote']['submit_label'] }}
                    </button>
                    <a href="{{ $frontendLoginUrl }}" class="rounded-full border border-white/15 px-7 py-3.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">
                        {{ $ui['nav_login'] }}
                    </a>
                </div>
                <p class="mt-6 text-xs text-slate-500">{{ $content['final_cta']['note'] }}</p>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/5 px-6 py-14">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-teal-600">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="white"/></svg>
                        </span>
                        <span class="text-base font-semibold text-white">Dentavaria</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-500">{{ $content['footer']['tagline'] }}</p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">{{ $ui['footer_product'] }}</p>
                    <ul class="mt-4 space-y-3 text-sm text-slate-500">
                        <li><a href="#features" class="transition hover:text-slate-300">{{ $ui['nav_features'] }}</a></li>
                        <li><a href="#pricing" class="transition hover:text-slate-300">{{ $ui['nav_pricing'] }}</a></li>
                        <li><a href="#faq" class="transition hover:text-slate-300">{{ $ui['nav_faq'] }}</a></li>
                        <li><a href="{{ route('api-docs') }}" class="transition hover:text-slate-300">{{ $ui['nav_api_docs'] }}</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">{{ $ui['footer_company'] }}</p>
                    <ul class="mt-4 space-y-3 text-sm text-slate-500">
                        <li><a href="#top" class="transition hover:text-slate-300">{{ $ui['footer_about'] }}</a></li>
                        <li><button type="button" data-open-modal="contact-modal" class="bg-transparent border-0 p-0 cursor-pointer transition hover:text-slate-300">{{ $ui['footer_contact'] }}</button></li>
                        @if ($isAdminLoggedIn)
                            <li><a href="{{ route('admin.dashboard') }}" class="transition hover:text-slate-300">{{ $ui['nav_admin'] }}</a></li>
                        @else
                            <li data-auth-cta="guest"><a href="{{ $frontendLoginUrl }}" class="transition hover:text-slate-300">{{ $ui['nav_login'] }}</a></li>
                            <li data-auth-cta="app-user" class="hidden"><a href="{{ rtrim(config('app.frontend_url'), '/') }}" class="transition hover:text-slate-300">{{ $ui['nav_dashboard'] }}</a></li>
                        @endif
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">{{ $ui['footer_legal'] }}</p>
                    <ul class="mt-4 space-y-3 text-sm text-slate-500">
                        <li><a href="{{ route('privacy', $locale) }}" class="transition hover:text-slate-300">{{ $ui['footer_privacy'] }}</a></li>
                        <li><a href="{{ route('terms', $locale) }}" class="transition hover:text-slate-300">{{ $ui['footer_terms'] }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-14 flex flex-col items-center justify-between gap-4 border-t border-white/5 pt-8 text-xs text-slate-600 sm:flex-row">
                <p>&copy; {{ date('Y') }} {{ $content['footer']['copyright_name'] }}. {{ $ui['footer_rights'] }}</p>
                <div class="flex items-center gap-5">
                    <a href="#" class="transition hover:text-slate-400" aria-label="X / Twitter">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-7.6 8.7L23 22h-6.9l-5.4-6.9L4.4 22H1.2l8.2-9.3L1 2h7.1l4.9 6.3L18.9 2zm-1.2 18h1.9L7.4 4H5.3l12.4 16z"/></svg>
                    </a>
                    <a href="#" class="transition hover:text-slate-400" aria-label="LinkedIn">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 8.98h4v12H3v-12zm7 0h3.8v1.64h.05c.53-1 1.83-2.06 3.77-2.06 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.34-.02-3.06-1.87-3.06-1.87 0-2.16 1.46-2.16 2.96V21h-4v-12z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Contact modal --}}
    <dialog id="contact-modal" class="modal">
        <div class="modal-card">
            <div class="mb-2 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $content['contact']['eyebrow'] }}</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-white">{{ $content['contact']['headline'] }}</h2>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="{{ $ui['close'] }}">&times;</button>
            </div>
            <p class="mb-6 text-sm text-slate-400">{{ $content['contact']['subtext'] }}</p>
            <form method="POST" action="{{ route('landing.contact.store') }}" class="space-y-4" novalidate data-validate>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="_form" value="contact">
                <div>
                    <input type="text" name="name" required placeholder="{{ $content['contact']['name_label'] }}" value="{{ old('_form') === 'contact' ? old('name') : '' }}" class="w-full rounded-xl border {{ old('_form') === 'contact' && $errors->has('name') ? 'border-red-400/60' : 'border-white/10' }} bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                    <p class="field-error {{ old('_form') === 'contact' && $errors->has('name') ? '' : 'hidden' }}" data-error-for="name" data-required-text="{{ $ui['field_required'] }}">{{ old('_form') === 'contact' && $errors->has('name') ? $errors->first('name') : $ui['field_required'] }}</p>
                </div>
                <div>
                    <input type="email" name="email" required placeholder="{{ $content['contact']['email_label'] }}" value="{{ old('_form') === 'contact' ? old('email') : '' }}" class="w-full rounded-xl border {{ old('_form') === 'contact' && $errors->has('email') ? 'border-red-400/60' : 'border-white/10' }} bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                    <p class="field-error {{ old('_form') === 'contact' && $errors->has('email') ? '' : 'hidden' }}" data-error-for="email" data-required-text="{{ $ui['field_required'] }}">{{ old('_form') === 'contact' && $errors->has('email') ? $errors->first('email') : $ui['field_required'] }}</p>
                </div>
                <div>
                    <textarea name="message" rows="4" required placeholder="{{ $content['contact']['message_label'] }}" class="w-full rounded-xl border {{ old('_form') === 'contact' && $errors->has('message') ? 'border-red-400/60' : 'border-white/10' }} bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">{{ old('_form') === 'contact' ? old('message') : '' }}</textarea>
                    <p class="field-error {{ old('_form') === 'contact' && $errors->has('message') ? '' : 'hidden' }}" data-error-for="message" data-required-text="{{ $ui['field_required'] }}">{{ old('_form') === 'contact' && $errors->has('message') ? $errors->first('message') : $ui['field_required'] }}</p>
                </div>
                <button type="submit" class="w-full rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 py-3.5 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:shadow-emerald-500/40 hover:brightness-110">
                    {{ $content['contact']['submit_label'] }}
                </button>
            </form>
        </div>
    </dialog>

    {{-- Quote modal --}}
    <dialog id="quote-modal" class="modal">
        <div class="modal-card">
            <div class="mb-2 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">{{ $content['quote']['eyebrow'] }}</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-white">{{ $content['quote']['headline'] }}</h2>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="{{ $ui['close'] }}">&times;</button>
            </div>
            <p class="mb-6 text-sm text-slate-400">{{ $content['quote']['subtext'] }}</p>
            <form method="POST" action="{{ route('landing.quote.store') }}" class="space-y-4" novalidate data-validate>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="_form" value="quote">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <input type="text" name="name" required placeholder="{{ $content['quote']['name_label'] }}" value="{{ old('_form') === 'quote' ? old('name') : '' }}" class="w-full rounded-xl border {{ old('_form') === 'quote' && $errors->has('name') ? 'border-red-400/60' : 'border-white/10' }} bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                        <p class="field-error {{ old('_form') === 'quote' && $errors->has('name') ? '' : 'hidden' }}" data-error-for="name" data-required-text="{{ $ui['field_required'] }}">{{ old('_form') === 'quote' && $errors->has('name') ? $errors->first('name') : $ui['field_required'] }}</p>
                    </div>
                    <div>
                        <input type="email" name="email" required placeholder="{{ $content['quote']['email_label'] }}" value="{{ old('_form') === 'quote' ? old('email') : '' }}" class="w-full rounded-xl border {{ old('_form') === 'quote' && $errors->has('email') ? 'border-red-400/60' : 'border-white/10' }} bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                        <p class="field-error {{ old('_form') === 'quote' && $errors->has('email') ? '' : 'hidden' }}" data-error-for="email" data-required-text="{{ $ui['field_required'] }}">{{ old('_form') === 'quote' && $errors->has('email') ? $errors->first('email') : $ui['field_required'] }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <input type="text" name="phone" placeholder="{{ $content['quote']['phone_label'] }}" value="{{ old('_form') === 'quote' ? old('phone') : '' }}" class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                    </div>
                    <div>
                        <input type="text" name="company" placeholder="{{ $content['quote']['company_label'] }}" value="{{ old('_form') === 'quote' ? old('company') : '' }}" class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">
                    </div>
                </div>
                <div>
                    <textarea name="message" rows="4" placeholder="{{ $content['quote']['message_label'] }}" class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/20">{{ old('_form') === 'quote' ? old('message') : '' }}</textarea>
                </div>
                <button type="submit" class="w-full rounded-full border border-white/15 py-3.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">
                    {{ $content['quote']['submit_label'] }}
                </button>
            </form>
        </div>
    </dialog>

    {{-- Success modals --}}
    <dialog id="contact-success-modal" class="modal">
        <div class="modal-card text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </div>
            <p class="text-sm leading-relaxed text-slate-300">{{ $content['contact']['success_message'] }}</p>
            <button type="button" class="mt-6 rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50" data-close-modal>{{ $ui['close'] }}</button>
        </div>
    </dialog>

    <dialog id="quote-success-modal" class="modal">
        <div class="modal-card text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </div>
            <p class="text-sm leading-relaxed text-slate-300">{{ $content['quote']['success_message'] }}</p>
            <button type="button" class="mt-6 rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50" data-close-modal>{{ $ui['close'] }}</button>
        </div>
    </dialog>

    <script>
        // Light/dark theme toggle (a desktop instance and a mobile-menu instance both exist)
        document.querySelectorAll('[data-theme-toggle]').forEach((themeToggle) => {
            themeToggle.addEventListener('click', () => {
                const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('dentavaria-theme', next);
            });
        });

        // Mobile "Options" menu: toggle open/closed, and auto-close when a
        // link or action inside it is used (but not the theme toggle, since
        // that's a preference flip the visitor may want to keep the menu
        // open for).
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenuPanel = document.getElementById('mobile-menu-panel');
        if (mobileMenuToggle && mobileMenuPanel) {
            mobileMenuToggle.addEventListener('click', () => {
                const willOpen = mobileMenuPanel.classList.contains('hidden');
                mobileMenuPanel.classList.toggle('hidden');
                mobileMenuToggle.setAttribute('aria-expanded', String(willOpen));
            });

            mobileMenuPanel.querySelectorAll('a, button:not([data-theme-toggle])').forEach((el) => {
                el.addEventListener('click', () => {
                    mobileMenuPanel.classList.add('hidden');
                    mobileMenuToggle.setAttribute('aria-expanded', 'false');
                });
            });
        }

        const revealEls = document.querySelectorAll('[data-reveal]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealEls.forEach((el) => observer.observe(el));

        // Billing toggle: swap displayed price without relying on CSS sibling combinators
        // that can't reach across the label's own DOM subtree into the .plans grid.
        const billingToggle = document.getElementById('billing-toggle');
        const plans = document.querySelector('.plans');
        if (billingToggle && plans) {
            billingToggle.addEventListener('change', () => {
                plans.classList.toggle('is-yearly', billingToggle.checked);
            });
        }

        // Modal open/close (same data-attribute convention as the admin panel).
        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-open-modal]');
            const closeButton = event.target.closest('[data-close-modal]');

            if (openButton) {
                document.getElementById(openButton.dataset.openModal)?.showModal();
            }

            if (closeButton) {
                closeButton.closest('dialog')?.close();
            }
        });

        document.querySelectorAll('dialog.modal').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        });

        // Required-field validation: show red inline text instead of the native browser bubble.
        document.querySelectorAll('form[data-validate]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                let hasError = false;

                form.querySelectorAll('[required]').forEach((input) => {
                    const wrapper = input.closest('div');
                    const errorEl = wrapper?.querySelector('[data-error-for]');
                    const isEmpty = !input.value.trim();

                    input.classList.toggle('border-red-400/60', isEmpty);
                    input.classList.toggle('border-white/10', !isEmpty);

                    if (errorEl) {
                        if (isEmpty) {
                            errorEl.textContent = errorEl.dataset.requiredText;
                            errorEl.classList.remove('hidden');
                        } else {
                            errorEl.classList.add('hidden');
                        }
                    }

                    if (isEmpty) hasError = true;
                });

                if (hasError) event.preventDefault();
            });
        });

        @if ($autoOpenModal)
            document.getElementById('{{ $autoOpenModal }}')?.showModal();
        @endif

        // If a clinic user is already signed in to the app (bearer token in
        // localStorage, set by the SPA at /app), swap the Login/Book-a-demo
        // CTAs for a direct link to their dashboard. The server can't see
        // this token (it's not a cookie), so this has to run client-side.
        @unless ($isAdminLoggedIn)
            (function () {
                function normalizeToken(value) {
                    if (typeof value !== 'string') return '';
                    var trimmed = value.trim();
                    if (!trimmed || trimmed === 'undefined' || trimmed === 'null') return '';
                    return trimmed;
                }

                var token = normalizeToken(window.localStorage.getItem('dental_api_token'));
                if (!token) return;

                fetch('/api/auth/me', { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } })
                    .then(function (response) {
                        if (!response.ok) return;
                        // Inline style, not the `hidden` class: the guest login link also
                        // carries `sm:block` (desktop-only), whose media-query rule cascades
                        // after `.hidden` and would otherwise win at desktop widths.
                        document.querySelectorAll('[data-auth-cta="guest"]').forEach(function (el) { el.style.display = 'none'; });
                        document.querySelectorAll('[data-auth-cta="app-user"]').forEach(function (el) { el.classList.remove('hidden'); });
                    })
                    .catch(function () {});
            })();
        @endunless
    </script>
</body>
</html>
