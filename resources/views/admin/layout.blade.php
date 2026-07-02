<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Panel' }}</title>
    <style>
        :root {
            --bg: #f3efe6;
            --paper: #fffdfa;
            --ink: #1b1f18;
            --muted: #62705b;
            --line: #d9d2c1;
            --accent: #0e6b5c;
            --accent-soft: #d8efe8;
            --warn: #c5532c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top right, #efe2c2 0, transparent 24rem),
                linear-gradient(180deg, #f7f2e8 0%, var(--bg) 100%);
            color: var(--ink);
        }
        .shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar {
            background: #1f312c;
            color: #f6efe2;
            padding: 2rem 1.25rem;
            border-right: 1px solid rgba(255,255,255,.08);
        }
        .brand { font-size: 1.35rem; margin: 0 0 1.5rem; letter-spacing: .04em; }
        .nav a, .nav button {
            display: block;
            width: 100%;
            text-align: left;
            padding: .8rem .95rem;
            margin-bottom: .65rem;
            color: inherit;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 14px;
            background: rgba(255,255,255,.03);
            cursor: pointer;
        }
        .main { padding: 2rem; }
        .hero {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 1.4rem 1.6rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 12px 35px rgba(76, 60, 29, .08);
        }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .company-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .card, .panel {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1rem;
            box-shadow: 0 10px 24px rgba(76, 60, 29, .06);
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .toolbar form {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: center;
        }
        .panel { margin-bottom: 1rem; overflow: auto; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { padding: .8rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { text-align: left; color: var(--muted); font-size: .92rem; }
        input, select, textarea {
            width: 100%;
            padding: .7rem .8rem;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            margin-bottom: .75rem;
        }
        textarea { min-height: 90px; resize: vertical; }
        .btn {
            padding: .7rem 1rem;
            border: 0;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
        }
        .btn-danger { background: var(--warn); }
        .btn-soft { background: var(--accent-soft); color: #133c34; }
        .btn-link {
            display: inline-block;
            padding: .7rem 1rem;
            border-radius: 12px;
            background: #1f312c;
            color: #fff;
            text-decoration: none;
        }
        .btn-muted {
            padding: .7rem 1rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
        }
        .inline { display: inline-block; }
        .status {
            display: inline-block;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .85rem;
            border: 1px solid var(--line);
            background: #faf5ec;
        }
        .flash { padding: .9rem 1rem; margin-bottom: 1rem; background: #e9f6f1; border: 1px solid #bfe0d5; border-radius: 14px; }
        .errors { padding: .9rem 1rem; margin-bottom: 1rem; background: #fff0ea; border: 1px solid #efc3b2; border-radius: 14px; }
        .muted { color: var(--muted); }
        dialog.modal {
            width: min(720px, calc(100% - 2rem));
            border: 0;
            border-radius: 22px;
            padding: 0;
            background: transparent;
        }
        dialog.modal::backdrop { background: rgba(19, 18, 14, .55); }
        .modal-card {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 1.25rem;
            box-shadow: 0 20px 50px rgba(0,0,0,.16);
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .close-btn {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 999px;
            width: 38px;
            height: 38px;
            cursor: pointer;
        }
        .actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }
        .table-actions form { display: inline-block; }
        .table-actions .btn,
        .table-actions .btn-soft,
        .table-actions .btn-danger,
        .table-actions .btn-muted,
        .table-actions .btn-link { margin-bottom: .35rem; }
        @media (max-width: 980px) {
            .shell, .grid-2 { grid-template-columns: 1fr; }
            .sidebar { padding-bottom: 1rem; }
            .main { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <h1 class="brand">Dental Admin</h1>
            <div class="nav">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.companies.index') }}">Companies</a>
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
