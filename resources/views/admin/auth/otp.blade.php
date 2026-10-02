<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code · Dentavaria</title>
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
            font-size: 1.1rem;
            letter-spacing: .3em;
            text-align: center;
        }
        input::placeholder { color: #94a3b8; letter-spacing: normal; }
        input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }
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
        .resend-form { margin-top: .9rem; }
        .resend-form button {
            background: none;
            box-shadow: none;
            color: #2563eb;
            font-weight: 600;
            font-size: .88rem;
            padding: .4rem;
        }
        .resend-form button:hover { text-decoration: underline; filter: none; }
        .back-link { display: block; margin-top: .6rem; text-align: center; color: #64748b; font-size: .85rem; text-decoration: none; }
        .back-link:hover { color: #334155; }
        .errors {
            margin-bottom: 1rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            background: rgba(220, 38, 38, 0.10);
            border: 1px solid rgba(220, 38, 38, 0.28);
            color: #b91c1c;
            font-size: .88rem;
        }
        .status {
            margin-bottom: 1rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.10);
            border: 1px solid rgba(37, 99, 235, 0.25);
            color: #1d4ed8;
            font-size: .88rem;
        }
    </style>
</head>
<body>
    <form class="box" method="POST" action="{{ route('admin.login.otp.verify') }}">
        @csrf
        <img src="/brand/doctovaria_logo.png" alt="Doctovaria" class="mark">
        <h1>Enter verification code</h1>
        <p class="sub">A verification code has been sent. Enter it below to finish signing in.</p>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" autofocus required>
        <button type="submit">Verify &amp; sign in</button>
    </form>
    <form class="resend-form" method="POST" action="{{ route('admin.login.otp.resend') }}" style="width: min(420px, 100%);">
        @csrf
        <button type="submit">Resend code</button>
    </form>
    <a class="back-link" href="{{ route('admin.login') }}">Back to sign in</a>
</body>
</html>
