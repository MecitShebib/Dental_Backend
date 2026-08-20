@php
    $locale = $locale ?? 'en';
    $isRtl = $locale === 'ar';

    $ui = [
        'en' => [
            'nav_home' => 'Home', 'nav_login' => 'Log in',
            'other_doc_privacy' => 'Privacy policy', 'other_doc_terms' => 'Terms of service',
            'footer_rights' => 'All rights reserved.',
            'back_home' => 'Back to home',
        ],
        'ar' => [
            'nav_home' => 'الرئيسية', 'nav_login' => 'تسجيل الدخول',
            'other_doc_privacy' => 'سياسة الخصوصية', 'other_doc_terms' => 'شروط الخدمة',
            'footer_rights' => 'جميع الحقوق محفوظة.',
            'back_home' => 'العودة إلى الرئيسية',
        ],
        'tr' => [
            'nav_home' => 'Ana sayfa', 'nav_login' => 'Giriş yap',
            'other_doc_privacy' => 'Gizlilik politikası', 'other_doc_terms' => 'Kullanım şartları',
            'footer_rights' => 'Tüm hakları saklıdır.',
            'back_home' => 'Ana sayfaya dön',
        ],
    ][$locale];

    $languages = ['en' => 'EN', 'ar' => 'AR', 'tr' => 'TR'];
    $frontendLoginUrl = rtrim(config('app.frontend_url'), '/') . '/login';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $legal['title'] }} — Dentavaria</title>
    <link rel="icon" type="image/png" href="/favicon.png">
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
        [dir="rtl"] body { font-family: 'Cairo', ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }

        .theme-toggle-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.04);
            color: #cbd5e1; cursor: pointer; flex: 0 0 auto;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .theme-toggle-btn:hover { background: rgba(255, 255, 255, 0.08); }
        .theme-toggle-btn .icon-moon { display: none; }
        html[data-theme="light"] .theme-toggle-btn .icon-sun { display: none; }
        html[data-theme="light"] .theme-toggle-btn .icon-moon { display: block; }

        html[data-theme="light"] body { background-color: #f8fafc; }
        html[data-theme="light"] [class~="bg-[#05070a]"] { background-color: #f8fafc; }
        html[data-theme="light"] header[class~="bg-[#05070a]/70"] { background-color: rgba(248, 250, 252, 0.75); }
        html[data-theme="light"] [class~="text-white"] { color: #0f172a; }
        html[data-theme="light"] [class~="text-slate-200"] { color: #1e293b; }
        html[data-theme="light"] [class~="text-slate-300"] { color: #334155; }
        html[data-theme="light"] [class~="text-slate-400"] { color: #475569; }
        html[data-theme="light"] [class~="text-slate-500"] { color: #64748b; }
        html[data-theme="light"] [class~="text-slate-600"] { color: #94a3b8; }
        html[data-theme="light"] [class~="border-white/5"] { border-color: rgba(15, 23, 42, 0.07); }
        html[data-theme="light"] [class~="border-white/10"] { border-color: rgba(15, 23, 42, 0.10); }
        html[data-theme="light"] [class~="hover:text-white"]:hover { color: #0f172a; }
        html[data-theme="light"] [class~="hover:text-slate-300"]:hover { color: #334155; }
        html[data-theme="light"] .theme-toggle-btn {
            border-color: rgba(15, 23, 42, 0.12); background: rgba(15, 23, 42, 0.04); color: #334155;
        }
        html[data-theme="light"] .theme-toggle-btn:hover { background: rgba(15, 23, 42, 0.08); }
    </style>
</head>
<body class="bg-[#05070a] text-slate-200 font-sans antialiased selection:bg-emerald-500/30 selection:text-white">

    <header class="sticky top-0 z-50 border-b border-white/5 bg-[#05070a]/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
            <a href="{{ route('home', $locale) }}" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-teal-600 shadow-lg shadow-emerald-500/20">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="white"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight text-white">Dentavaria</span>
            </a>

            <div class="flex items-center gap-3">
                <button type="button" id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle light/dark theme">
                    <svg class="icon-sun h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15a5 5 0 100-10 5 5 0 000 10zM10 0a1 1 0 011 1v1a1 1 0 11-2 0V1a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM3.05 3.05a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zm11.78 11.78a1 1 0 011.415 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 01-1.414 0zM0 10a1 1 0 011-1h1a1 1 0 110 2H1a1 1 0 01-1-1zm17 0a1 1 0 011-1h1a1 1 0 110 2h-1a1 1 0 01-1-1zM3.05 16.95a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0zM14.83 5.17a1 1 0 010-1.414l.707-.707a1 1 0 111.414 1.414l-.707.707a1 1 0 01-1.414 0z"/></svg>
                    <svg class="icon-moon h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>
                <div class="flex items-center overflow-hidden rounded-full border border-white/10 text-xs font-semibold text-slate-400">
                    @foreach ($languages as $code => $label)
                        <a href="{{ $page === 'terms' ? route('terms', $code) : route('privacy', $code) }}" class="px-2.5 py-1.5 transition {{ $locale === $code ? 'bg-white/10 text-white' : 'hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <a href="{{ route('home', $locale) }}" class="hidden text-sm font-medium text-slate-300 transition hover:text-white sm:block">{{ $ui['nav_home'] }}</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-16">
        <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $legal['title'] }}</h1>
        <p class="mt-3 text-sm text-slate-500">{{ $legal['updated_label'] }}: {{ $legal['updated_date'] }}</p>

        <p class="mt-8 text-lg leading-relaxed text-slate-400">{{ $legal['intro'] }}</p>

        <div class="mt-14 space-y-12">
            @foreach ($legal['sections'] as $section)
                <section>
                    <h2 class="text-xl font-semibold text-white">{{ $section['heading'] }}</h2>
                    <div class="mt-3 space-y-4 text-base leading-relaxed text-slate-400">
                        @foreach ($section['body'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-16 flex flex-wrap items-center gap-4 border-t border-white/5 pt-8 text-sm">
            @if ($page === 'terms')
                <a href="{{ route('privacy', $locale) }}" class="font-medium text-emerald-400 transition hover:text-emerald-300">{{ $ui['other_doc_privacy'] }}</a>
            @else
                <a href="{{ route('terms', $locale) }}" class="font-medium text-emerald-400 transition hover:text-emerald-300">{{ $ui['other_doc_terms'] }}</a>
            @endif
            <span class="text-slate-700">·</span>
            <a href="{{ route('home', $locale) }}" class="font-medium text-slate-300 transition hover:text-white">{{ $ui['back_home'] }}</a>
        </div>
    </main>

    <footer class="border-t border-white/5 px-6 py-10">
        <div class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-4 text-xs text-slate-600 sm:flex-row">
            <p>&copy; {{ date('Y') }} Dentavaria. {{ $ui['footer_rights'] }}</p>
            <a href="{{ $frontendLoginUrl }}" class="transition hover:text-slate-400">{{ $ui['nav_login'] }}</a>
        </div>
    </footer>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('dentavaria-theme', next);
            });
        }
    </script>
</body>
</html>
