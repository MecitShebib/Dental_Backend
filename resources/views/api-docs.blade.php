<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Public REST API reference for Dentavaria — endpoints, request fields, response shapes, and how to authenticate.">
    <title>API Documentation — Dentavaria</title>
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
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        code, .font-mono-docs { font-family: ui-monospace, "SF Mono", "Cascadia Code", "Roboto Mono", Consolas, monospace; }
        .method-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 62px; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; }
        .method-GET { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
        .method-POST { background: rgba(52, 211, 153, 0.15); color: #6ee7b7; }
        .method-PUT { background: rgba(251, 191, 36, 0.15); color: #fcd34d; }
        .method-DELETE { background: rgba(248, 113, 113, 0.15); color: #fca5a5; }
    </style>
</head>
<body class="bg-[#05070a] text-slate-200 antialiased selection:bg-emerald-500/30 selection:text-white">

<header class="sticky top-0 z-30 border-b border-white/5 bg-[#05070a]/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-teal-600">
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="#05070a"/></svg>
            </span>
            <span class="text-base font-semibold tracking-tight text-white">Dentavaria</span>
        </a>
        <div class="flex items-center gap-5 text-sm">
            <span class="hidden text-slate-500 sm:inline">API Documentation</span>
            <a href="{{ route('home') }}" class="rounded-full border border-white/15 px-4 py-2 font-semibold text-white transition hover:border-white/30 hover:bg-white/5">Back to site</a>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-10 px-6 py-12">
    <aside class="hidden w-56 shrink-0 lg:block">
        <nav class="sticky top-24 flex flex-col gap-1 text-sm">
            <a href="#getting-started" class="rounded-lg px-3 py-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white">Getting started</a>
            @foreach ($groups as $group)
                <a href="#{{ $group['id'] }}" class="rounded-lg px-3 py-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white">{{ $group['title'] }}</a>
            @endforeach
            <a href="#enums" class="rounded-lg px-3 py-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white">Enum reference</a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Reference</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white">Dentavaria API</h1>
            <p class="mt-4 text-slate-400">Every endpoint that powers the Dentavaria app is available to call directly — whether you're building your own integration or connecting outside equipment (an X-ray imaging system, a lab scanner) straight into a patient's chart.</p>
        </div>

        <section id="getting-started" class="mt-14 border-t border-white/5 pt-10">
            <h2 class="text-2xl font-semibold text-white">Getting started</h2>

            <div class="mt-6 rounded-xl border border-white/10 bg-white/[0.02] p-5">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Base URL</p>
                <code class="mt-2 block text-sm text-emerald-300">{{ $baseUrl }}</code>
            </div>

            <div class="mt-6 space-y-4 text-sm leading-relaxed text-slate-400">
                <p>Every request (aside from the auth endpoints themselves) must include an <code class="text-slate-300">Authorization: Bearer &lt;token&gt;</code> header. There are two ways to get a token:</p>
                <ol class="list-decimal space-y-2 pl-5">
                    <li><strong class="text-slate-200">User login.</strong> Complete the two-step OTP flow (<code class="text-slate-300">POST /auth/login</code> then <code class="text-slate-300">POST /auth/login/verify-otp</code>) to get a token tied to a staff account's own session.</li>
                    <li><strong class="text-slate-200">API token (recommended for integrations).</strong> Log into the app once, open <strong class="text-slate-200">Settings → API Token</strong>, and create a named token. This is the right choice for a script or an outside machine that isn't a person logging in — it doesn't expire on its own and can be revoked independently at any time.</li>
                </ol>
                <p>All responses share one envelope: <code class="text-slate-300">{"{"}"message": string, "data": ...{"}"}</code>. Validation errors return HTTP 422 with an additional <code class="text-slate-300">errors</code> object keyed by field name.</p>
            </div>

            <div class="mt-6 overflow-x-auto rounded-xl border border-white/10 bg-black/40 p-5">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Example</p>
                <pre class="mt-3 whitespace-pre-wrap break-all text-[13px] leading-relaxed text-slate-300 font-mono-docs">curl {{ $baseUrl }}/clients \
  -H "Authorization: Bearer &lt;your-api-token&gt;" \
  -H "Accept: application/json"</pre>
            </div>
        </section>

        @foreach ($groups as $group)
            <section id="{{ $group['id'] }}" class="mt-14 border-t border-white/5 pt-10">
                <h2 class="text-2xl font-semibold text-white">{{ $group['title'] }}</h2>
                @if (!empty($group['intro']))
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">{{ $group['intro'] }}</p>
                @endif

                <div class="mt-8 space-y-6">
                    @foreach ($group['endpoints'] as $endpoint)
                        <article class="rounded-xl border border-white/10 bg-white/[0.02] p-5">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="method-badge method-{{ $endpoint['method'] }}">{{ $endpoint['method'] }}</span>
                                <code class="text-sm text-white">{{ $endpoint['path'] }}</code>
                                <span class="ms-auto rounded-full border border-white/10 px-3 py-1 text-[11px] font-medium text-slate-400">{{ $endpoint['auth'] }}</span>
                                @if (collect($endpoint['response'] ?? [])->contains(fn ($field) => ($field['status'] ?? null) === 201))
                                    <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-[11px] font-medium text-emerald-300">201 Created</span>
                                @endif
                            </div>
                            <p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $endpoint['summary'] }}</p>

                            @if (!empty($endpoint['request']))
                                <div class="mt-4">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Request</p>
                                    <div class="mt-2 overflow-x-auto rounded-lg border border-white/10">
                                        <table class="w-full text-left text-[13px]">
                                            <thead>
                                                <tr class="border-b border-white/10 text-slate-500">
                                                    <th class="px-3 py-2 font-medium">Field</th>
                                                    <th class="px-3 py-2 font-medium">Type</th>
                                                    <th class="px-3 py-2 font-medium">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($endpoint['request'] as $field)
                                                    <tr class="border-b border-white/5 last:border-0">
                                                        <td class="px-3 py-2 font-mono-docs text-slate-200">{{ $field['name'] }}</td>
                                                        <td class="px-3 py-2 text-slate-400">{{ $field['type'] ?? '' }}</td>
                                                        <td class="px-3 py-2 text-slate-500">
                                                            @if (($field['required'] ?? null) === true)
                                                                <span class="text-emerald-400">required</span>
                                                            @elseif (($field['required'] ?? null) === false)
                                                                <span class="text-slate-600">optional</span>
                                                            @endif
                                                            @if (!empty($field['enum']))
                                                                — {{ is_string($field['enum']) && isset($enums[$field['enum']]) ? implode(', ', $enums[$field['enum']]) : $field['enum'] }}
                                                            @endif
                                                            @if (!empty($field['notes']))
                                                                {{ (($field['required'] ?? null) !== null || !empty($field['enum'])) ? '— ' : '' }}{{ $field['notes'] }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($endpoint['response']))
                                <div class="mt-4">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Response <code class="normal-case text-slate-600">data</code></p>
                                    <ul class="mt-2 space-y-1.5 text-[13px]">
                                        @foreach ($endpoint['response'] as $field)
                                            <li class="flex flex-wrap gap-x-2 gap-y-0.5">
                                                <code class="text-slate-200">{{ $field['name'] }}</code>
                                                @if (!empty($field['type']))<span class="text-slate-500">{{ $field['type'] }}</span>@endif
                                                @if (!empty($field['enum']))<span class="text-slate-600">({{ is_string($field['enum']) && isset($enums[$field['enum']]) ? implode(', ', $enums[$field['enum']]) : $field['enum'] }})</span>@endif
                                                @if (!empty($field['notes']))<span class="text-slate-600">— {{ $field['notes'] }}</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if (!empty($group['object']))
                    <div class="mt-6 rounded-xl border border-white/10 bg-black/30 p-5">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ $group['object']['name'] }} fields</p>
                        <ul class="mt-3 grid gap-1.5 text-[13px] text-slate-400 sm:grid-cols-2">
                            @foreach ($group['object']['fields'] as $field)
                                <li class="font-mono-docs">{{ $field }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endforeach

        <section id="enums" class="mt-14 border-t border-white/5 pt-10">
            <h2 class="text-2xl font-semibold text-white">Enum reference</h2>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">Exact valid string values for every enum-backed field referenced above.</p>
            <div class="mt-6 overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full text-left text-[13px]">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-500">
                            <th class="px-4 py-2.5 font-medium">Enum</th>
                            <th class="px-4 py-2.5 font-medium">Valid values</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enums as $name => $values)
                            <tr class="border-b border-white/5 last:border-0">
                                <td class="px-4 py-2.5 font-mono-docs text-slate-200">{{ $name }}</td>
                                <td class="px-4 py-2.5 text-slate-400">{{ implode(', ', $values) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="mt-16 border-t border-white/5 pt-8 pb-4 text-xs text-slate-600">
            Dentavaria — the clinical operating system for modern dental practices.
        </footer>
    </main>
</div>

</body>
</html>
