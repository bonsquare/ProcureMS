<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $section }} Under Maintenance</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fff; color: #172538; font-family: Inter, system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        main { width: 100%; padding: 16px; box-sizing: border-box; text-align: center; }
        img { display: block; width: 100%; max-width: 960px; height: auto; margin: 0 auto; }
        .tab { margin: 8px 0 0; font-size: 0.95rem; font-weight: 600; color: #103967; }
        .back { display: inline-block; margin-top: 12px; padding: 10px 22px; border-radius: 10px; background: #103967; color: #fff; font-size: 0.875rem; font-weight: 700; text-decoration: none; }
        .back:hover { opacity: .9; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    </style>
</head>
<body>
    <main>
        <h1 class="sr-only">{{ $section }} is Under Maintenance</h1>
        <img src="{{ asset('images/maintenance-site.webp') }}" alt="This Site is Under Maintenance. We are performing maintenance to improve your experience, performance, and security. Please check back again later.">
        <p class="tab">{{ $section }} is temporarily unavailable.</p>
        <a class="back" href="{{ auth()->check() ? route('home') : url('/') }}">Back to Dashboard</a>
    </main>
</body>
</html>
