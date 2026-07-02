<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top left, rgba(210,180,116,.35), transparent 22rem),
                linear-gradient(180deg, #1d2927, #10201d 60%, #0c1715);
            font-family: Georgia, "Times New Roman", serif;
            color: #f7f2e8;
        }
        .box {
            width: min(420px, calc(100% - 2rem));
            background: rgba(255,252,246,.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 24px;
            padding: 2rem;
        }
        input {
            width: 100%;
            padding: .85rem .95rem;
            margin-bottom: .9rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.08);
            color: #fff;
        }
        button {
            width: 100%;
            padding: .9rem 1rem;
            border: 0;
            border-radius: 12px;
            background: #d59d3b;
            color: #1d1609;
            font-weight: 700;
            cursor: pointer;
        }
        .errors {
            margin-bottom: 1rem;
            padding: .85rem;
            border-radius: 12px;
            background: rgba(194,79,47,.22);
        }
    </style>
</head>
<body>
    <form class="box" method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <h1>Admin Panel Login</h1>
        <p>Sign in with an active admin account and active subscription.</p>
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>
