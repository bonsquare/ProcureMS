<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#536273',secondary:'#369878','outline-variant':'#cbd7e1',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="min-h-screen bg-surface font-inter text-on-surface">
<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,4fr)]">
    {{-- Brand panel: shown from large screens up --}}
    <aside class="relative hidden overflow-hidden bg-gradient-to-br from-[#0b2a52] via-primary to-[#174b82] px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-white/5"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-24 h-[28rem] w-[28rem] rounded-full bg-secondary/15"></div>
        <div class="relative flex items-center gap-4">
            <img src="{{ asset('images/procurems-emblem.svg') }}" alt="" class="h-14 w-14 shrink-0">
            <div><p class="text-2xl font-bold leading-tight tracking-tight">Procure<span class="text-[#6fd3b0]">MS</span></p><p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/60">School Procurement &amp; Liquidation</p></div>
        </div>
        <div class="relative max-w-xl">
            <h2 class="text-[40px] font-semibold leading-[1.15] tracking-tight">One workspace, from the plan to the payment.</h2>
            <p class="mt-4 text-base leading-relaxed text-white/75">Planning, budget, procurement, accounting, cash and liquidation of the school, linked in one record that is traceable from the first plan to the last receipt.</p>
            <ul class="mt-9 grid gap-4 sm:grid-cols-2">
                @foreach([['account_tree', 'Plan', 'SIP, AIP, SOB and APP'], ['shopping_cart', 'Procure', 'PR, RFQ, abstract, PO and delivery'], ['receipt_long', 'Account', 'ORS, DV and journal entry'], ['payments', 'Pay and liquidate', 'Cash payment and liquidation report']] as [$icon, $title, $text])
                    <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3.5 backdrop-blur-sm"><span class="material-symbols-outlined mt-0.5 text-[22px] text-[#6fd3b0]" aria-hidden="true">{{ $icon }}</span><span><span class="block text-sm font-semibold">{{ $title }}</span><span class="block text-xs leading-relaxed text-white/65">{{ $text }}</span></span></li>
                @endforeach
            </ul>
        </div>
        <p class="relative text-xs text-white/50">Multi-School Procurement System · Secure operational workspace</p>
    </aside>

    {{-- Sign in --}}
    <main class="flex items-center justify-center bg-gradient-to-b from-surface to-[#e4ebf1] px-4 py-10 sm:px-8">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center justify-center gap-3 lg:hidden"><img src="{{ asset('images/procurems-emblem.svg') }}" alt="" class="h-12 w-12 shrink-0"><div><p class="text-xl font-bold leading-tight tracking-tight text-primary">Procure<span class="text-secondary">MS</span></p><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-on-surface-variant">School Procurement &amp; Liquidation</p></div></div>
            <div class="rounded-2xl border border-outline-variant/40 bg-white p-7 shadow-xl shadow-primary/5 sm:p-9">
                <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Welcome back</h1>
                <p class="mt-1.5 text-sm text-on-surface-variant">Sign in to your procurement, accounting, budget, and cash workspace.</p>
                @if(session('success'))<div class="mt-5 rounded-lg border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-medium text-secondary">{{ session('success') }}</div>@endif
                <form id="login-form" method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">@csrf
            <div><label for="email" class="text-xs font-semibold text-on-surface-variant">Email or username</label><input id="email" name="email" type="text" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Email address or username" value="{{ old('email') }}" required autofocus class="mt-2 w-full rounded-lg border border-outline-variant/50 bg-surface px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">@error('email')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="text-xs font-semibold text-on-surface-variant">Password</label><div class="relative mt-2"><input id="password" name="password" type="password" required class="w-full rounded-lg border border-outline-variant/50 bg-surface py-2.5 pl-3 pr-11 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"><button type="button" id="toggle-password" aria-label="Show password" aria-pressed="false" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r text-on-surface-variant hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span></button></div>@error('password')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
            @include('auth.partials-turnstile', ['action' => 'login'])
                    <label class="flex items-center gap-2 text-xs text-on-surface-variant"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-primary">Remember me</label>
                    <button id="sign-in-button" type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"><span class="button-label">Sign in</span><span class="button-spinner hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
                </form>
                <div class="my-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant/70"><span class="h-px flex-1 bg-outline-variant/50"></span>New here<span class="h-px flex-1 bg-outline-variant/50"></span></div>
                <a href="{{ route('register') }}" class="flex w-full items-center justify-center gap-2 rounded-lg border border-primary px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">app_registration</span>Pre-register school</a>
                <p class="mt-2 text-center text-xs text-on-surface-variant">New school or office? Start with a pre-registration.</p>
                @if(app()->environment('local'))
                <p class="mt-6 border-t border-outline-variant/20 pt-5 text-xs text-on-surface-variant">Demo Master Admin: <span class="font-semibold">admin@procurems.test</span> / <span class="font-semibold">password</span></p>
                @endif
                @if(config('app.demo'))
                {{-- A client sees only the school administrator; the master and Sub-master demo accounts are not advertised. --}}
                <p class="mt-6 border-t border-outline-variant/20 pt-5 text-xs text-on-surface-variant">Demo School Admin: <span class="font-semibold">demo@gmail.com</span> / <span class="font-semibold">password</span></p>
                @endif
            </div>
            <p class="mt-6 text-center text-[11px] text-on-surface-variant/80">Secure sign-in · Your session is protected</p>
        </div>
    </main>
</div>
<script>
    document.getElementById('login-form').addEventListener('submit', () => {
        const button = document.getElementById('sign-in-button');
        button.disabled = true;
        button.classList.add('cursor-wait', 'opacity-80');
        button.querySelector('.button-label').textContent = 'Signing in';
        button.querySelector('.button-spinner').classList.remove('hidden');
    });
    (() => {
        const input = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');
        if (!input || !toggle) return;
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.firstElementChild.textContent = show ? 'visibility_off' : 'visibility';
        });
    })();
</script>
</body>
</html>
