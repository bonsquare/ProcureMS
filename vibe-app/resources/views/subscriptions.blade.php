<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#e6edf4','surface-low':'#dbe5ee','surface-container':'#cfdbe6','surface-high':'#c0cfdd',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#374a5d',secondary:'#369878','outline-variant':'#7f95a9',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>html{font-size:14px}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    <aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#103967] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
        @php $navigation=[['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports'],['icon'=>'group','label'=>'User Management','route'=>'user-management'],['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions','active'=>true],['icon'=>'settings','label'=>'School Settings']]; $navigation = array_values(array_filter($navigation, fn ($item) => match ($item['label'] ?? '') { 'User Management' => (bool) auth()->user()?->isAnyMaster(), 'Subscriptions' => (bool) auth()->user()?->hasAccess('subscriptions'), default => true })); if (auth()->user()?->hasAccess('schools')) { array_splice($navigation, count($navigation) - 1, 0, [['icon'=>'domain','label'=>'School Management','route'=>'school-management']]); } if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); } @endphp
        @foreach($navigation as $item)<a href="{{ isset($item['route']) ? route($item['route']) : ($item['label'] === 'School Settings' ? route('school-settings') : '#') }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if(($item['label'] ?? '') === 'Dashboard')<details class="group"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
    </nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

    <div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Subscription Administration</p><p class="truncate text-sm font-semibold text-on-surface">Subscriptions</p></div></div><div class="flex items-center gap-4"><button class="relative rounded p-1 text-on-surface-variant" aria-label="Notifications"><span class="material-symbols-outlined text-[21px]">notifications</span><span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-error"></span></button><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></div></header>

    <main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">card_membership</span><span class="font-semibold text-primary">Plans &amp; Billing</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Subscriptions</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Manage school plans, renewals, billing status, and organization subscriptions.</p></div><button class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">add</span>Create Subscription</button></div>

        @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-medium text-secondary">{{ session('success') }}</div>@endif
        <div id="subscriptions-live" data-url="{{ route('subscriptions.live') }}" data-hash="{{ $liveHash }}">
            <p class="mb-3 flex items-center justify-end gap-2 text-xs text-on-surface-variant" aria-live="polite"><span class="inline-block h-2 w-2 rounded-full bg-secondary" data-live-dot></span><span data-live-status>Live</span></p>
            <div data-live-body>@include('partials.subscriptions-live')</div>
        </div>
        <script>
            // The page follows the data: it asks for what changed every few seconds while it is open and shown, and redraws only when something changed.
            (() => {
                const box = document.getElementById('subscriptions-live');
                if (! box) return;
                const body = box.querySelector('[data-live-body]');
                const status = box.querySelector('[data-live-status]');
                const dot = box.querySelector('[data-live-dot]');
                let hash = box.dataset.hash;
                let dirty = false;
                body.addEventListener('input', () => { dirty = true; });
                body.addEventListener('change', () => { dirty = true; });
                const busy = () => dirty || body.contains(document.activeElement) && /^(input|select|textarea)$/i.test(document.activeElement.tagName) || document.querySelector('dialog[open]');
                const clock = () => new Date().toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit' });
                const tick = async () => {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(box.dataset.url + '?h=' + encodeURIComponent(hash), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (response.status === 204) { status.textContent = 'Live · checked ' + clock(); dot.className = 'inline-block h-2 w-2 rounded-full bg-secondary'; return; }
                        if (! response.ok) throw new Error('live failed');
                        const data = await response.json();
                        if (busy()) { status.textContent = 'Live · new data waits until you finish typing'; return; }
                        body.innerHTML = data.html; hash = data.hash; dirty = false;
                        status.textContent = 'Live · updated ' + clock(); dot.className = 'inline-block h-2 w-2 rounded-full bg-secondary';
                    } catch (error) { status.textContent = 'Live updates paused · reconnecting'; dot.className = 'inline-block h-2 w-2 rounded-full bg-amber-500'; }
                };
                setInterval(tick, 8000);
                document.addEventListener('visibilitychange', () => { if (! document.hidden) tick(); });
            })();
        </script>
    </div></main></div>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
