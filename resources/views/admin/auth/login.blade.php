<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · Dentavaria</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 15% -10%, rgba(16, 185, 129, 0.14), transparent 42%),
                radial-gradient(circle at 88% 10%, rgba(13, 148, 136, 0.10), transparent 38%),
                #f8fafc;
            color: #0f172a;
            padding: 1.5rem;
        }
        .box {
            width: min(420px, 100%);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: 24px;
            padding: 2.25rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
        }
        .mark {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            background: linear-gradient(135deg, #10b981, #0d9488);
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.3);
        }
        h1 { margin: 0 0 .4rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.01em; }
        p.sub { margin: 0 0 1.5rem; color: #64748b; font-size: .92rem; line-height: 1.5; }
        input {
            width: 100%;
            padding: .8rem .95rem;
            margin-bottom: .9rem;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: rgba(15, 23, 42, 0.03);
            color: #0f172a;
            font: inherit;
            font-size: .92rem;
        }
        input::placeholder { color: #94a3b8; }
        input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
        }
        button {
            width: 100%;
            padding: .9rem 1rem;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #0d9488);
            color: #04140f;
            font: inherit;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.22);
        }
        button:hover { filter: brightness(1.08); }
        .errors {
            margin-bottom: 1rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            background: rgba(220, 38, 38, 0.10);
            border: 1px solid rgba(220, 38, 38, 0.28);
            color: #b91c1c;
            font-size: .88rem;
        }
    </style>
</head>
<body>
    <form class="box" method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <span class="mark">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3c-3 0-5 2-5 5 0 2.5 1 4 1 7 0 2 .8 3.5 2 3.5s1.5-2 2-4c.3-1.2.7-1.2 1 0 .5 2 .8 4 2 4s2-1.5 2-3.5c0-3 1-4.5 1-7 0-3-2-5-5-5-.7 0-1 .3-1.5.6-.5-.3-.8-.6-1.5-.6Z" fill="white"/></svg>
        </span>
        <h1>Admin sign in</h1>
        <p class="sub">Sign in with an active admin account and active subscription.</p>
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <input type="tel" name="phone" placeholder="Phone number" value="{{ old('phone') }}" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>
