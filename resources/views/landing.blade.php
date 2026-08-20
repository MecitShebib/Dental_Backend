@php
    $locale = $locale ?? 'en';
    $isRtl = $locale === 'ar';

    // Real designed wordmark logo (replaces the earlier hand-built SVG approximation).
    $wordmarkSrc = '/brand/doctovaria_logo.png';

    $ui = [
        'en' => [
            'meta_title' => 'Doctovaria — The clinical operating system for modern healthcare practices',
            'meta_description' => 'Doctovaria is the clinical operating system for modern healthcare practices — dental, gynecology, internal medicine, orthopedics, and cosmetic. Pick your specialty to see its own workflow, features, and pricing.',
            'nav_login' => 'Log in', 'nav_cta' => 'Book a demo', 'nav_admin' => 'Go to admin panel', 'nav_dashboard' => 'Go to dashboard',
            'nav_api_docs' => 'API Documentation',
            'view_product' => 'View',
            'footer_rights' => 'All rights reserved.',
        ],
        'ar' => [
            'meta_title' => 'Doctovaria — نظام التشغيل السريري لممارسات الرعاية الصحية الحديثة',
            'meta_description' => 'Doctovaria هو نظام التشغيل السريري لممارسات الرعاية الصحية الحديثة — طب الأسنان وأمراض النساء والطب الباطني وجراحة العظام والطب التجميلي. اختر تخصصك لترى سير عمله ومزاياه وأسعاره الخاصة.',
            'nav_login' => 'تسجيل الدخول', 'nav_cta' => 'احجز عرضًا توضيحيًا', 'nav_admin' => 'الذهاب إلى لوحة التحكم', 'nav_dashboard' => 'الذهاب إلى لوحة القيادة',
            'nav_api_docs' => 'وثائق API',
            'view_product' => 'عرض',
            'footer_rights' => 'جميع الحقوق محفوظة.',
        ],
        'tr' => [
            'meta_title' => 'Doctovaria — Modern sağlık pratiklerinin klinik işletim sistemi',
            'meta_description' => "Doctovaria, modern sağlık pratiklerinin klinik işletim sistemidir — diş hekimliği, kadın hastalıkları, dahiliye, ortopedi ve estetik tıp. Kendi uzmanlık alanınızın iş akışını, özelliklerini ve fiyatlandırmasını görmek için seçin.",
            'nav_login' => 'Giriş yap', 'nav_cta' => 'Demo talep edin', 'nav_admin' => 'Yönetim paneline git', 'nav_dashboard' => 'Panele git',
            'nav_api_docs' => 'API Dokümantasyonu',
            'view_product' => 'Görüntüle',
            'footer_rights' => 'Tüm hakları saklıdır.',
        ],
    ][$locale];

    $languages = ['en' => 'EN', 'ar' => 'AR', 'tr' => 'TR'];
    $frontendLoginUrl = rtrim(config('app.frontend_url'), '/') . '/login';
    $isAdminLoggedIn = auth()->check() && auth()->user()->isProjectAdmin() && auth()->user()->isActive();

    $productAccents = \App\Models\LandingPageContent::SPECIALTY_ACCENTS;
    $productSlugs = \App\Models\LandingPageContent::SPECIALTY_SLUGS;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $ui['meta_description'] }}">
    <title>{{ $ui['meta_title'] }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script>
        (function () {
            var stored = localStorage.getItem('doctovaria-theme');
            if (stored === 'light' || stored === 'dark') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|cairo:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        [data-reveal] {
            opacity: 0;
            transform: translateY(1.5rem);
            transition: opacity .7s ease, transform .7s ease;
        }
        [data-reveal].is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        [dir="rtl"] body { font-family: 'Cairo', ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }

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
           exact class-token match, so the markup itself never has to be touched.
           White body -> white floating product cards with a real border+shadow,
           since (unlike the specialty pages) this hub has no separate section-wash
           sections that would conflict with that same Tailwind class. */
        html[data-theme="light"] body { background-color: #ffffff; }
        html[data-theme="light"] .dot-grid { background-image: radial-gradient(circle at 1px 1px, rgba(15, 23, 42, 0.07) 1px, transparent 0); }

        html[data-theme="light"] [class~="bg-[#0a0f1a]"] { background-color: #ffffff; }
        html[data-theme="light"] header[class~="bg-[#0a0f1a]/70"] {
            background-color: rgba(255, 255, 255, 0.82);
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.06), 0 8px 24px -12px rgba(15, 23, 42, 0.15);
        }
        html[data-theme="light"] header[class~="border-white/5"] { border-bottom-color: rgba(37, 99, 235, 0.22); }

        html[data-theme="light"] [class~="text-white"] { color: #0f172a; }
        html[data-theme="light"] [class~="text-slate-200"] { color: #1e293b; }
        html[data-theme="light"] [class~="text-slate-300"] { color: #334155; }
        html[data-theme="light"] [class~="text-slate-400"] { color: #475569; }
        html[data-theme="light"] [class~="text-slate-500"] { color: #64748b; }

        /* This page's own fixed Doctovaria-blue chrome (not per-product $accent):
           text-blue-300 is lightened toward white for the dark body, which is
           low-contrast once the body is white — restore full brand-blue strength. */
        html[data-theme="light"] [class~="text-blue-300"] { color: #2563eb; }
        html[data-theme="light"] [class~="bg-blue-600/10"] { background-color: rgba(37, 99, 235, 0.26); }

        /* A second, lower glow in the same brand blue so the color carries past
           the hero fold and doesn't just live in the top corner. */
        html[data-theme="light"] body::after {
            content: "";
            position: fixed;
            inset-inline-start: -8rem;
            bottom: -10rem;
            width: 24rem;
            height: 24rem;
            border-radius: 9999px;
            background: rgba(37, 99, 235, 0.14);
            filter: blur(90px);
            pointer-events: none;
            z-index: -1;
        }

        html[data-theme="light"] [class~="border-white/5"] { border-color: rgba(15, 23, 42, 0.08); }
        html[data-theme="light"] [class~="border-white/10"] { border-color: rgba(15, 23, 42, 0.12); }
        html[data-theme="light"] [class~="border-white/15"] { border-color: rgba(15, 23, 42, 0.16); }

        /* Each product card sets its own --accent inline (see the loop below) --
           tint the card itself with it so the grid reads as 5 distinct colors,
           not 5 identical white tiles with a colored top bar. */
        html[data-theme="light"] [class~="bg-white/[0.02]"] {
            background-color: color-mix(in srgb, var(--accent, #2563eb) 6%, white);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px -12px rgba(15, 23, 42, 0.12);
        }
        html[data-theme="light"] [class~="bg-white/[0.04]"] { background-color: color-mix(in srgb, var(--accent, #2563eb) 6%, white); }
        html[data-theme="light"] [class~="hover:bg-white/[0.04]"]:hover {
            background-color: color-mix(in srgb, var(--accent, #2563eb) 10%, white);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05), 0 20px 36px -14px rgba(15, 23, 42, 0.16);
        }
        html[data-theme="light"] [class~="hover:text-white"]:hover { color: #0f172a; }
        html[data-theme="light"] [class~="hover:bg-blue-50"]:hover { background-color: #eff6ff; }

        html[data-theme="light"] .theme-toggle-btn {
            border-color: rgba(15, 23, 42, 0.12);
            background: rgba(15, 23, 42, 0.04);
            color: #334155;
        }
        html[data-theme="light"] .theme-toggle-btn:hover { background: rgba(15, 23, 42, 0.08); }
    </style>
</head>
<body id="top" class="bg-[#0a0f1a] text-slate-200 font-sans antialiased">

    {{-- Ambient background: one restrained glow, no product accent bias --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 h-[32rem] w-[32rem] rounded-full bg-blue-600/10 blur-3xl"></div>
        <div class="dot-grid absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.05)_1px,transparent_0)] [background-size:32px_32px] opacity-30"></div>
    </div>

    {{-- Nav --}}
    <header class="sticky top-0 z-50 border-b border-white/5 bg-[#0a0f1a]/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="#top" class="flex h-8 items-center">
                <img src="{{ $wordmarkSrc }}" alt="Doctovaria" class="block h-12 w-auto">
            </a>

            <div class="flex items-center gap-3">
                <button type="button" class="theme-toggle-btn" data-theme-toggle aria-label="Toggle light/dark theme">
                    <svg class="icon-sun h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15a5 5 0 100-10 5 5 0 000 10zM10 0a1 1 0 011 1v1a1 1 0 11-2 0V1a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.05 3.05a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zm11.78 11.78a1 1 0 011.415 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 01-1.414 0zM0 10a1 1 0 011-1h1a1 1 0 110 2H1a1 1 0 01-1-1zm17 0a1 1 0 011-1h1a1 1 0 110 2h-1a1 1 0 01-1-1zM3.05 16.95a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0zM14.83 5.17a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0z"/></svg>
                    <svg class="icon-moon h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>
                <div class="flex items-center overflow-hidden rounded-full border border-white/10 text-xs font-semibold text-slate-400">
                    @foreach ($languages as $code => $label)
                        <a href="{{ route('home', $code) }}" class="px-2.5 py-1.5 transition {{ $locale === $code ? 'bg-white/10 text-white' : 'hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
                @if ($isAdminLoggedIn)
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-blue-50">
                        {{ $ui['nav_admin'] }}
                    </a>
                @else
                    <a href="{{ $frontendLoginUrl }}" data-auth-cta="guest" class="hidden text-sm font-medium text-slate-300 transition hover:text-white sm:block">{{ $ui['nav_login'] }}</a>
                    <a href="{{ rtrim(config('app.frontend_url'), '/') }}" data-auth-cta="app-user" class="hidden rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-blue-50">
                        {{ $ui['nav_dashboard'] }}
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="relative">
        {{-- Hero --}}
        <section class="relative overflow-hidden px-6 pt-20 pb-16">
            <div class="mx-auto max-w-3xl text-center" data-reveal>
                <div class="inline-flex items-center gap-2 rounded-full border border-blue-400/20 bg-blue-400/10 px-4 py-1.5 text-xs font-medium text-blue-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                    {{ $content['hero']['eyebrow'] }}
                </div>
                <h1 class="mt-6 text-4xl font-semibold leading-[1.1] tracking-tight text-white sm:text-5xl">
                    {{ $content['hero']['headline'] }}
                </h1>
                <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-400">
                    {{ $content['hero']['subtext'] }}
                </p>
            </div>
        </section>

        {{-- Products --}}
        <section class="px-6 pb-28">
            <div class="mx-auto grid max-w-6xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($content['products'] as $product)
                    @php
                        $accent = $productAccents[$product['key']] ?? '#1f4e8c';
                        $slug = $productSlugs[$product['key']] ?? '';
                        $href = $locale === 'en' ? route('specialty.home', $slug) : route('specialty', [$locale, $slug]);

                        // Same real logo used in that product's own nav bar (see landing-specialty.blade.php).
                        $productLogoSrc = '/brand/' . match ($product['key']) {
                            'dental' => 'dentavaria_logo.png',
                            'gynecology' => 'gynevaria_logo.png',
                            'internal_medicine' => 'medivaria_logo.png',
                            'orthopedics' => 'orthovaria_logo.png',
                            'cosmetic' => 'estevaria_logo.png',
                            default => 'doctovaria_logo.png',
                        };
                    @endphp
                    <a href="{{ $href }}" class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02] p-7 transition hover:-translate-y-1 hover:bg-white/[0.04]" style="--accent: {{ $accent }};" data-reveal>
                        <div class="absolute inset-x-0 top-0 h-1" style="background: var(--accent);"></div>
                        <img src="{{ $productLogoSrc }}" alt="{{ $product['name'] }}" class="h-12 w-auto">
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide" style="color: var(--accent);">{{ $product['tagline'] }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $product['body'] }}</p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-white">
                            {{ $ui['view_product'] }} {{ $product['name'] }}
                            <svg class="h-4 w-4 transition group-hover:translate-x-1 {{ $isRtl ? 'rotate-180 group-hover:-translate-x-1' : '' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.3 3.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L13.58 10H4a1 1 0 110-2h9.58l-3.3-3.3a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/5 px-6 py-10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 text-xs text-slate-600 sm:flex-row">
            <p>&copy; {{ date('Y') }} {{ $content['footer']['copyright_name'] }}. {{ $ui['footer_rights'] }}</p>
            <div class="flex items-center gap-5">
                <a href="{{ route('api-docs') }}" class="transition hover:text-slate-400">{{ $ui['nav_api_docs'] }}</a>
                <a href="{{ route('privacy', $locale === 'en' ? 'en' : $locale) }}" class="transition hover:text-slate-400">{{ $locale === 'ar' ? 'سياسة الخصوصية' : ($locale === 'tr' ? 'Gizlilik politikası' : 'Privacy policy') }}</a>
                <a href="{{ route('terms', $locale === 'en' ? 'en' : $locale) }}" class="transition hover:text-slate-400">{{ $locale === 'ar' ? 'شروط الخدمة' : ($locale === 'tr' ? 'Kullanım şartları' : 'Terms of service') }}</a>
            </div>
        </div>
    </footer>

    <script>
        document.querySelectorAll('[data-theme-toggle]').forEach((themeToggle) => {
            themeToggle.addEventListener('click', () => {
                const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('doctovaria-theme', next);
            });
        });

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

        // If a clinic user is already signed in to the app (bearer token in
        // localStorage, set by the SPA at /app), swap the Login CTA for a
        // direct link to their dashboard. The server can't see this token
        // (it's not a cookie), so this has to run client-side.
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
                        document.querySelectorAll('[data-auth-cta="guest"]').forEach(function (el) { el.style.display = 'none'; });
                        document.querySelectorAll('[data-auth-cta="app-user"]').forEach(function (el) { el.classList.remove('hidden'); });
                    })
                    .catch(function () {});
            })();
        @endunless
    </script>
</body>
</html>
