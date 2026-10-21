<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial · ProcureMS</title>
    @include('partials.favicon')
    <style>
        :root { color-scheme: light; --navy: #103967; --green: #2a7f64; --ink: #0f172a; --muted: #536273; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; background: radial-gradient(1100px 360px at 50% -80px, #d3e2f2 0%, rgba(211, 226, 242, 0) 72%), #e6ebf1; font: 15px/1.5 Inter, "Segoe UI", system-ui, Arial, sans-serif; color: var(--ink); }
        main { width: min(34rem, 100%); background: #fff; border-radius: 1rem; padding: 2rem; box-shadow: 0 24px 60px -20px rgba(16, 57, 103, .35); border-top: 4px solid var(--green); }
        .tag { display: inline-block; font-size: .7rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--green); }
        h1 { margin: .4rem 0 .75rem; font-size: 1.5rem; line-height: 1.25; letter-spacing: -.01em; color: var(--navy); }
        p { margin: 0 0 1rem; color: var(--muted); }
        .price { display: flex; align-items: baseline; gap: .5rem; margin: 1.25rem 0; padding: 1rem 1.25rem; border-radius: .75rem; background: #eef4fa; }
        .price strong { font-size: 2rem; color: var(--navy); font-variant-numeric: tabular-nums; }
        ol { margin: 0 0 1.5rem; padding-left: 1.25rem; color: var(--muted); }
        li { margin-bottom: .35rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        a, button { font: inherit; font-weight: 700; border-radius: .6rem; padding: .7rem 1.2rem; cursor: pointer; text-decoration: none; }
        .primary { background: var(--navy); color: #fff; border: 0; }
        .ghost { background: #fff; color: var(--navy); border: 1px solid #cbd7e1; }
        :focus-visible { outline: 3px solid #8fd5bb; outline-offset: 2px; }
    </style>
</head>
<body>
<main role="alert">
    <span class="tag">Trial</span>
    <h1>{{ $title }}</h1>
    <p>Your school is on the trial. You can try the whole system, but nothing can be printed or saved as a PDF until the school has a paid subscription.</p>
    <div class="price"><strong>₱{{ number_format($price, 2) }}</strong><span>a year for each school</span></div>
    <ol>
        <li>Open the Subscription page and send the payment to the account shown there.</li>
        <li>Upload your proof of payment to your own Google Drive from that page.</li>
        <li>The administrator checks it and activates your school. Then everything prints.</li>
    </ol>
    <div class="actions">
        <a class="primary" href="{{ url('/subscription') }}">Go to Subscription</a>
        <button type="button" class="ghost" onclick="window.close(); setTimeout(function () { if (! window.closed) history.back(); }, 250);">Close</button>
    </div>
</main>
</body>
</html>
