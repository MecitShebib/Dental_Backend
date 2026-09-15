<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · Dentavaria</title>
    <link rel="icon" type="image/png" href="/favicon.png">
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
                radial-gradient(circle at 15% -10%, rgba(37, 99, 235, 0.14), transparent 42%),
                radial-gradient(circle at 88% 10%, rgba(29, 78, 216, 0.10), transparent 38%),
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
            display: block;
            height: 36px;
            width: auto;
            margin-bottom: 1.25rem;
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
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }
        .password-field { position: relative; margin-bottom: .9rem; }
        .password-field input { padding-right: 2.6rem; margin-bottom: 0; }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: .5rem;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            padding: 0;
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
        }
        .password-toggle:hover { color: #334155; background: rgba(15, 23, 42, 0.06); }
        .password-toggle svg { width: 18px; height: 18px; }
        .password-toggle .eye-off { display: none; }
        .password-toggle.is-visible .eye-on { display: none; }
        .password-toggle.is-visible .eye-off { display: block; }
        button {
            width: 100%;
            padding: .9rem 1rem;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
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
        <img src="/brand/doctovaria_logo.png" alt="Doctovaria" class="mark">
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
        <div class="password-field">
            <input type="password" name="password" placeholder="Password" required>
            <button type="button" class="password-toggle" onclick="window.togglePasswordField(this)" aria-label="Show password">
                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.4 21.4 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
            </button>
        </div>
        <button type="submit">Sign in</button>
    </form>
    <script>
        window.togglePasswordField = function (button) {
            const input = button.previousElementSibling;
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            button.classList.toggle('is-visible', !isVisible);
            button.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        };
    </script>
</body>
</html>
