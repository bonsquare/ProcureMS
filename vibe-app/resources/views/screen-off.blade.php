<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>System Under Maintenance</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fff; color: #172538; font-family: Inter, system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        main { width: 100%; padding: 16px; box-sizing: border-box; }
        img { display: block; width: 100%; max-width: 960px; height: auto; margin: 0 auto; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    </style>
</head>
<body>
    <main>
        <h1 class="sr-only">This System is Under Maintenance</h1>
        <img src="{{ asset('images/maintenance-system.webp') }}" alt="This System is Under Maintenance. We are performing system maintenance to improve performance, security, and your overall experience. Please check back again later.">
    </main>
</body>
</html>
