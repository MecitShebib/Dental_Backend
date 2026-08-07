<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Panel' }} · Dentavaria</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        :root {
            --bg: #f8fafc;
            --surface-0: #ffffff;
            --surface-1: rgba(15, 23, 42, 0.035);
            --surface-2: rgba(15, 23, 42, 0.05);
            --surface-3: rgba(15, 23, 42, 0.08);
            --border: rgba(15, 23, 42, 0.10);
            --border-strong: rgba(15, 23, 42, 0.16);
            --text: #0f172a;
            --text-muted: #475569;
            --text-faint: #64748b;
            --accent: #10b981;
            --accent-2: #0d9488;
            --accent-soft: rgba(16, 185, 129, 0.14);
            --danger: #dc2626;
            --danger-soft: rgba(220, 38, 38, 0.10);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 12% -10%, rgba(16, 185, 129, 0.12), transparent 42%),
                radial-gradient(circle at 88% 8%, rgba(13, 148, 136, 0.10), transparent 38%),
                var(--bg);
            color: var(--text);
        }
        .shell { display: grid; grid-template-columns: 264px 1fr; min-height: 100vh; }
        .sidebar {
            background: rgba(248, 250, 252, 0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            padding: 1.75rem 1.25rem;
            display: flex;
            flex-direction: column;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: .65rem;
            margin: 0 0 2rem;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text);
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);
            flex: 0 0 auto;
        }
        .nav { display: flex; flex-direction: column; gap: .35rem; flex: 1; }
        .nav a, .nav button {
            display: flex;
            align-items: center;
            width: 100%;
            text-align: left;
            padding: .7rem .9rem;
            border: 1px solid transparent;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font: inherit;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            background: transparent;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }
        .nav a:hover, .nav button:hover {
            color: var(--text);
            background: var(--surface-2);
        }
        .nav a.active {
            color: #04140f;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-color: transparent;
        }
        .nav form { margin-top: auto; }
        .main { padding: 2rem 2.5rem; min-width: 0; }
        .hero {
            background: var(--surface-1);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07), 0 1px 4px rgba(15, 23, 42, 0.05);
        }
        .hero h2 { margin: 0 0 .5rem; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.01em; }
        .hero p { margin: 0; color: var(--text-muted); line-height: 1.6; max-width: 60ch; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .card, .panel {
            background: var(--surface-1);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07), 0 1px 4px rgba(15, 23, 42, 0.05);
        }
        .card strong { display: block; color: var(--text-muted); font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .5rem; }
        .card > div { font-size: 1.8rem; font-weight: 700; }
        .panel { margin-bottom: 1.25rem; overflow: auto; }
        .panel h3 { margin: 0 0 1rem; font-size: 1.05rem; font-weight: 700; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .toolbar form { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { padding: .85rem .75rem; border-bottom: 1px solid var(--border); vertical-align: top; text-align: left; }
        th { color: var(--text-muted); font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        td strong { color: var(--text); }
        td small { color: var(--text-faint); }
        input, select, textarea {
            width: 100%;
            padding: .65rem .85rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text);
            font: inherit;
            font-size: .88rem;
            margin-bottom: .65rem;
        }
        input::placeholder, textarea::placeholder { color: var(--text-faint); }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        textarea { min-height: 90px; resize: vertical; }
        label.field-label { display: block; font-size: .78rem; font-weight: 600; color: var(--text-muted); margin-bottom: .35rem; }
        .btn, .btn-soft, .btn-muted, .btn-danger, .btn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .65rem 1.1rem;
            border: 1px solid transparent;
            border-radius: 999px;
            font: inherit;
            font-size: .85rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: filter .15s ease, background .15s ease, border-color .15s ease;
        }
        .btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #04140f;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.22);
        }
        .btn:hover { filter: brightness(1.08); }
        .btn-soft { background: var(--accent-soft); color: #047857; border-color: rgba(16, 185, 129, 0.3); }
        .btn-soft:hover { background: rgba(16, 185, 129, 0.22); }
        .btn-muted { background: var(--surface-2); color: var(--text); border-color: var(--border); }
        .btn-muted:hover { background: var(--surface-3); }
        .btn-link { background: var(--surface-3); color: var(--text); }
        .btn-link:hover { background: var(--surface-2); filter: brightness(1.15); }
        .btn-danger { background: var(--danger-soft); color: #b91c1c; border-color: rgba(220, 38, 38, 0.28); }
        .btn-danger:hover { background: rgba(220, 38, 38, 0.16); }
        .inline { display: inline-block; }
        .status {
            display: inline-block;
            padding: .3rem .7rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text-muted);
            text-transform: capitalize;
        }
        .flash { padding: .9rem 1.1rem; margin-bottom: 1.25rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; color: #047857; }
        .errors { padding: .9rem 1.1rem; margin-bottom: 1.25rem; background: var(--danger-soft); border: 1px solid rgba(220, 38, 38, 0.28); border-radius: 14px; color: #b91c1c; }
        .muted { color: var(--text-muted); }
        dialog.modal {
            width: min(720px, calc(100% - 2rem));
            border: 0;
            border-radius: 22px;
            padding: 0;
            background: transparent;
            color: var(--text);
        }
        dialog.modal::backdrop { background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); }
        .modal-card {
            background: var(--surface-0);
            border: 1px solid var(--border-strong);
            border-radius: 22px;
            padding: 1.5rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        .modal-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .modal-head h3 { margin: 0; font-size: 1.15rem; }
        .close-btn {
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text);
            border-radius: 999px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
        }
        .close-btn:hover { background: var(--surface-3); }
        .actions-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .table-actions form { display: inline-block; }
        .table-actions .btn, .table-actions .btn-soft, .table-actions .btn-danger, .table-actions .btn-muted, .table-actions .btn-link { margin-bottom: .35rem; }
        @media (max-width: 980px) {
            .shell, .grid-2 { grid-template-columns: 1fr; }
            .sidebar { padding: 1.25rem; }
            .main { padding: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="white"/></svg>
                </span>
                Dentavaria Admin
            </div>
            <div class="nav">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.companies.index') }}" class="{{ request()->routeIs('admin.companies.*') ? 'active' : '' }}">Companies</a>
                <a href="{{ route('admin.landing-page.edit') }}" class="{{ request()->routeIs('admin.landing-page.*') ? 'active' : '' }}">Landing Page</a>
                <a href="{{ route('admin.inquiries.index') }}" class="{{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}">Inquiries</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </div>
        </aside>
        <main class="main">
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <script>
        document.addEventListener('click', function (event) {
            const openButton = event.target.closest('[data-open-modal]');
            const closeButton = event.target.closest('[data-close-modal]');

            if (openButton) {
                const dialog = document.getElementById(openButton.dataset.openModal);
                if (dialog) dialog.showModal();
            }

            if (closeButton) {
                const dialog = closeButton.closest('dialog');
                if (dialog) dialog.close();
            }
        });
    </script>
    @stack('modals')
</body>
</html>
