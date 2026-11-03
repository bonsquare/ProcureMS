<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>/* The page scales a little with the height of the window so more of it is in view, but never below 87.5% of the reader's own text size, so browser zoom and font settings still work. */html{font-size:max(87.5%,min(100%,1.62vh))}</style>
    <title>Sign in · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb',primary:'#14818f','primary-container':'#1a98a8','on-surface':'#12303a','on-surface-variant':'#536273',secondary:'#2aa86b','outline-variant':'#cbd7e1',error:'#e0414f'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
@include('partials.input-fixes')
@include('partials.favicon')
</head>
{{-- Full-page photo: put the picture at public/images/login-bg.svg (a school campus at sunrise); replace it with a photo if you have one. --}}
<body class="relative min-h-screen overflow-x-hidden bg-[#0a3a47] bg-cover bg-center bg-fixed font-inter text-on-surface" style="background-image:linear-gradient(135deg,rgba(10,58,71,.55),rgba(20,129,143,.3) 55%,rgba(10,58,71,.25)),url('{{ asset('images/login-bg.svg') }}'),linear-gradient(135deg,#0a3a47,#14818f 55%,#1a98a8)">
{{-- Full-page background: soft coloured light behind the glass sign-in box --}}
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-primary focus:shadow-lg">Skip to the sign-in form</a>
<div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
    <div class="absolute -left-24 -top-24 h-[26rem] w-[26rem] rounded-full bg-[#f2b134]/20 blur-3xl"></div>
    <div class="absolute -bottom-32 left-1/4 h-[30rem] w-[30rem] rounded-full bg-[#2aa86b]/15 blur-3xl"></div>
    <div class="absolute -right-24 top-10 h-[28rem] w-[28rem] rounded-full bg-[#7ed3e0]/20 blur-3xl"></div>
    <div class="absolute bottom-10 right-1/4 h-72 w-72 rounded-full bg-[#b9a7f0]/15 blur-3xl"></div>
</div>
<div class="relative grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,4fr)]">
    {{-- Brand panel: shown from large screens up --}}
    <aside class="relative hidden overflow-hidden px-10 py-10 text-white lg:flex xl:px-12 lg:flex-col lg:justify-between">
        <div class="relative flex items-center gap-4">
            <img src="{{ asset('images/procurems-logo-light.png') }}" alt="ProcMS - Plan. Procure. Finance. Report." class="h-32 w-auto">
        </div>
        <div class="relative max-w-xl">
            <p class="text-[2.125rem] font-semibold leading-[1.15] tracking-tight xl:text-[2.5rem]">One workspace, from the plan to the payment.</p>
            <p class="mt-4 text-base leading-relaxed text-white [text-shadow:0_1px_3px_rgba(0,0,0,.4)]">Planning, budget, procurement, accounting, cash and liquidation of the school, linked in one record that is traceable from the first plan to the last receipt.</p>
            <div class="mt-7 grid grid-cols-3 gap-3" aria-label="Planning, finance and procurement">
                <div class="rounded-2xl border border-white/40 bg-white/15 p-3 backdrop-blur-md"><svg viewBox="0 0 120 70" class="h-14 w-full" aria-hidden="true"><g fill="#fff" fill-opacity=".25"><rect x="6" y="6" width="22" height="16" rx="4"/><rect x="34" y="6" width="22" height="16" rx="4"/><rect x="62" y="6" width="22" height="16" rx="4"/><rect x="90" y="6" width="22" height="16" rx="4"/><rect x="6" y="28" width="22" height="16" rx="4"/><rect x="90" y="28" width="22" height="16" rx="4"/></g><rect x="34" y="28" width="22" height="16" rx="4" fill="#f2b134"/><rect x="62" y="28" width="22" height="16" rx="4" fill="#7ed3e0"/><circle cx="14" cy="60" r="6" fill="#2aa86b"/><path d="M11 60l2.4 2.4 4.6-5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/><rect x="26" y="57" width="60" height="6" rx="3" fill="#fff" fill-opacity=".7"/></svg><p class="mt-1 text-xs font-bold uppercase tracking-[.14em]">Planning</p></div>
                <div class="rounded-2xl border border-white/40 bg-white/15 p-3 backdrop-blur-md"><svg viewBox="0 0 120 70" class="h-14 w-full" aria-hidden="true"><rect x="8" y="40" width="14" height="26" rx="3" fill="#7ed3e0"/><rect x="28" y="28" width="14" height="38" rx="3" fill="#7ed3e0"/><rect x="48" y="34" width="14" height="32" rx="3" fill="#f2b134"/><rect x="68" y="16" width="14" height="50" rx="3" fill="#2aa86b"/><polyline points="15,32 35,20 55,26 75,8 98,14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><circle cx="102" cy="48" r="13" fill="#f2b134"/><text x="102" y="55" text-anchor="middle" font-family="Arial,sans-serif" font-size="19" font-weight="700" fill="#fff">₱</text></svg><p class="mt-1 text-xs font-bold uppercase tracking-[.14em]">Finance</p></div>
                <div class="rounded-2xl border border-white/40 bg-white/15 p-3 backdrop-blur-md"><svg viewBox="0 0 120 70" class="h-14 w-full" aria-hidden="true"><path d="M6 8h16l10 40h46l10-30H26" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="38" cy="60" r="5" fill="#f2b134"/><circle cx="72" cy="60" r="5" fill="#f2b134"/><rect x="34" y="24" width="14" height="14" rx="3" fill="#7ed3e0"/><rect x="52" y="18" width="14" height="20" rx="3" fill="#2aa86b"/><rect x="92" y="14" width="22" height="40" rx="4" fill="#fff" fill-opacity=".85"/><rect x="97" y="21" width="12" height="3" rx="1.5" fill="#14818f"/><rect x="97" y="29" width="12" height="3" rx="1.5" fill="#9cc"/><circle cx="103" cy="44" r="5" fill="#2aa86b"/></svg><p class="mt-1 text-xs font-bold uppercase tracking-[.14em]">Procurement</p></div>
            </div>
            <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([['account_tree', 'Plan', 'SIP, AIP, SOB and APP', '#e6f3fc', '#1b6fa8'], ['shopping_cart', 'Procure', 'PR, RFQ, abstract, PO and delivery', '#e4f6ee', '#1d8566'], ['receipt_long', 'Account', 'ORS, DV and journal entry', '#fdf5d9', '#a47a0a'], ['payments', 'Pay and liquidate', 'Cash payment and liquidation report', '#eeeafb', '#6a55b8']] as [$icon, $title, $text, $tileBg, $tileInk])
                    <li class="flex items-start gap-3 rounded-xl px-4 py-3.5 shadow-lg shadow-black/10" style="background:{{ $tileBg }};color:{{ $tileInk }}"><span class="material-symbols-outlined mt-0.5 text-[1.375rem]" aria-hidden="true">{{ $icon }}</span><span><span class="block text-sm font-bold">{{ $title }}</span><span class="block text-xs leading-relaxed text-on-surface-variant">{{ $text }}</span></span></li>
                @endforeach
            </ul>
        </div>
        <p class="relative text-xs text-white [text-shadow:0_1px_3px_rgba(0,0,0,.4)]">Multi-School Procurement System · Secure operational workspace</p>
    </aside>

    {{-- Sign in --}}
    <main id="main" tabindex="-1" class="flex items-center justify-center px-4 py-8 focus:outline-none sm:px-8">
        <div class="w-full max-w-md">
            <div class="mb-6 flex justify-center lg:hidden"><img src="{{ asset('images/procurems-logo-light.png') }}" alt="ProcMS - Plan. Procure. Finance. Report." class="h-20 w-auto"></div>
            <div class="rounded-3xl border border-white/60 border-t-4 border-t-[#f2b134] bg-white/65 p-7 shadow-2xl shadow-[#0a3a47]/30 backdrop-blur-xl sm:p-9" style="-webkit-backdrop-filter:blur(24px) saturate(1.4);backdrop-filter:blur(24px) saturate(1.4)">
                <h1 class="text-[1.75rem] font-semibold leading-9 tracking-tight">Welcome back</h1>
                <p class="mt-1.5 text-sm text-on-surface-variant">Sign in to your procurement, accounting, budget, and cash workspace.</p>
                @if(session('success'))<div class="mt-5 rounded-lg border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-medium text-secondary">{{ session('success') }}</div>@endif
                @if($errors->any())
                <div id="error-summary" role="alert" tabindex="-1" class="mt-5 rounded-lg border-2 border-[#b3202f] bg-white p-3 text-sm text-[#8f1a26] focus:outline-none">
                    <p class="flex items-center gap-2 font-bold"><span class="material-symbols-outlined text-[1.25rem]" aria-hidden="true">error</span>Please fix the following:</p>
                    <ul class="mt-1 list-disc pl-6">@error('email')<li><a href="#email" class="font-semibold underline">{{ $message }}</a></li>@enderror @error('password')<li><a href="#password" class="font-semibold underline">{{ $message }}</a></li>@enderror</ul>
                </div>
                @endif
                <form id="login-form" method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">@csrf
            <div><label for="email" class="text-xs font-semibold text-on-surface-variant">Email or username</label><input id="email" name="email" type="text" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Email address or username" value="{{ old('email') }}" required @unless($errors->any()) autofocus @endunless @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="mt-2 w-full rounded-lg border border-white/70 bg-white/80 px-3 py-2.5 text-sm outline-none placeholder:text-slate-600 focus:border-[#0b5f6b] focus:ring-2 focus:ring-[#0b5f6b]">@error('email')<p id="email-error" class="mt-1 flex items-center gap-1 text-xs font-semibold text-[#8f1a26]"><span class="material-symbols-outlined text-[1rem]" aria-hidden="true">error</span>{{ $message }}</p>@enderror</div>
            <div><label for="password" class="text-xs font-semibold text-on-surface-variant">Password</label><div class="relative mt-2"><input id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror class="w-full rounded-lg border border-white/70 bg-white/80 py-2.5 pl-3 pr-11 text-sm outline-none focus:border-[#0b5f6b] focus:ring-2 focus:ring-[#0b5f6b]"><button type="button" id="toggle-password" aria-label="Show password" aria-pressed="false" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r text-on-surface-variant hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5f6b]"><span class="material-symbols-outlined text-[1.25rem]" aria-hidden="true">visibility</span></button></div>@error('password')<p id="password-error" class="mt-1 flex items-center gap-1 text-xs font-semibold text-[#8f1a26]"><span class="material-symbols-outlined text-[1rem]" aria-hidden="true">error</span>{{ $message }}</p>@enderror</div>
            @include('auth.partials-turnstile', ['action' => 'login'])
                    <label class="flex items-center gap-2 text-xs text-on-surface-variant"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-primary">Remember me</label>
                    <button id="sign-in-button" type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"><span class="button-label">Sign in</span><span id="signin-status" class="sr-only" role="status"></span><span class="button-spinner hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
                </form>
                <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant/70"><span class="h-px flex-1 bg-outline-variant/50"></span>New here<span class="h-px flex-1 bg-outline-variant/50"></span></div>
                <a href="{{ route('register') }}" class="flex w-full items-center justify-center gap-2 rounded-lg border border-[#0b5f6b] px-4 py-2.5 text-sm font-semibold text-[#0b5f6b] transition hover:bg-[#0b5f6b] hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5f6b] focus-visible:ring-offset-2"><span class="material-symbols-outlined text-[1.125rem]" aria-hidden="true">app_registration</span>Pre-register school</a>
                <p class="mt-2 text-center text-xs text-on-surface-variant">New school or office? Start with a pre-registration.</p>
                @if(app()->environment('local'))
                <p class="mt-6 border-t border-outline-variant/20 pt-5 text-xs text-on-surface-variant">Demo Master Admin: <span class="font-semibold">admin@procurems.test</span> / <span class="font-semibold">password</span></p>
                @endif
                @if(config('app.demo'))
                {{-- A client sees only the school administrator; the master and Sub-master demo accounts are not advertised. --}}
                <p class="mt-6 border-t border-outline-variant/20 pt-5 text-xs text-on-surface-variant">Demo School Admin: <span class="font-semibold">demo@gmail.com</span> / <span class="font-semibold">password</span></p>
                @endif
            </div>
            <p class="mt-6 text-center text-xs text-white [text-shadow:0_1px_3px_rgba(0,0,0,.4)]">Secure sign-in · Your session is protected</p>
            <div class="mt-4 rounded-xl border border-white/30 bg-black/40 px-4 py-3 text-center text-xs leading-relaxed text-white backdrop-blur-sm">
                <p class="text-xs font-bold text-white">© 2026 ProcMS - Celsys</p>
                <p class="mt-1">Designed, Developed &amp; Coded by <strong class="font-bold text-white">Bonifacio Jr. Orong &amp; Rizza Mae Orong</strong></p>
                <p class="mt-1 font-bold text-white">Proprietary Software — All Rights Reserved.</p>
                <p class="mt-1 text-white">Unauthorized copying, reproduction, modification, distribution, or commercial use of this software or any portion thereof is prohibited without prior written authorization from the developer.</p>
            </div>
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
        document.getElementById('signin-status').textContent = 'Signing in, please wait';
    });
    document.getElementById('error-summary')?.focus();
    (() => {
        const input = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');
        if (!input || !toggle) return;
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.firstElementChild.textContent = show ? 'visibility_off' : 'visibility';
        });
    })();
</script>
</body>
</html>
