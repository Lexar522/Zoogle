{{-- Без шаблону магазину: помилка може статися ще до запуску сесії й підключення до бази. --}}
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') — ZOOGLE</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <style>
        :root { color-scheme: light dark; --bg: #f5f5f7; --card: #fff; --text: #1d1d1f; --muted: #6e6e73; }
        @media (prefers-color-scheme: dark) { :root { --bg: #000; --card: #1c1c1e; --text: #f5f5f7; --muted: #98989d; } }
        * { box-sizing: border-box; }
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100dvh; margin: 0; padding: 16px;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Inter, sans-serif;
            color: var(--text); background: var(--bg); -webkit-font-smoothing: antialiased;
        }
        main { width: min(100%, 440px); padding: 32px 24px; border-radius: 20px; text-align: center; background: var(--card); box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.05); }
        img { display: block; width: 150px; max-width: 60%; height: auto; margin: 0 auto 20px; }
        .code { margin: 0; font-size: 13px; font-weight: 600; color: var(--muted); }
        h1 { margin: 6px 0 8px; font-size: 26px; line-height: 1.2; letter-spacing: -0.025em; }
        p.text { margin: 0 0 20px; font-size: 15px; line-height: 1.45; color: var(--muted); }
        nav { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        a.btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 20px; border-radius: 980px; font-size: 15px; font-weight: 600; text-decoration: none; color: #fff; background: #007aff; }
        a.btn.secondary { color: var(--text); background: rgba(120,120,128,.14); }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/zoogle-logo-new.png') }}" alt="ZOOGLE" width="150" height="66">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="text">@yield('message')</p>
        <nav>
            <a class="btn" href="{{ url('/') }}">На головну</a>
            <a class="btn secondary" href="{{ url('/catalog') }}">До каталогу</a>
        </nav>
    </main>
</body>
</html>
