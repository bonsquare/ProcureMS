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
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">card_membership</span><span class="font-semibold text-primary">Plans &amp; Billing</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Subscriptions</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Manage school plans, renewals, billing status, and organization subscriptions.</p></div><button type="button" data-sub-open="create" class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">add</span>Create Subscription</button></div>

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
                        const saved = window.subFilters?.save(); body.innerHTML = data.html; hash = data.hash; dirty = false; window.subFilters?.restore(saved ?? { search: '', status: '' });
                        status.textContent = 'Live · updated ' + clock(); dot.className = 'inline-block h-2 w-2 rounded-full bg-secondary';
                    } catch (error) { status.textContent = 'Live updates paused · reconnecting'; dot.className = 'inline-block h-2 w-2 rounded-full bg-amber-500'; }
                };
                setInterval(tick, 8000);
                document.addEventListener('visibilitychange', () => { if (! document.hidden) tick(); });
            })();
        </script>

    <dialog data-sub-dialog="create" style="width:min(32rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="{{ route('subscriptions.store') }}" data-guard="Create the subscription" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Create Subscription</h2>
        <p class="text-xs text-on-surface-variant">Start a 3-day trial for a school, or record its Annual payment (₱{{ number_format(\App\Services\SubscriptionPricing::LIST_PRICE, 2) }} a year, or the price you set for it).</p>
        <label class="block text-xs font-semibold text-on-surface-variant">School<select name="school_id" required class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"><option value="">Choose a school</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }} ({{ $school->code }})</option>@endforeach</select></label>
        <label class="block text-xs font-semibold text-on-surface-variant">Plan<select name="mode" required data-sub-mode class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"><option value="trial">Trial (3 days)</option><option value="annual">Annual (record the payment)</option></select></label>
        <div data-sub-annual class="hidden space-y-3"><label class="block text-xs font-semibold text-on-surface-variant">Reference no. of the payment<input name="reference" maxlength="100" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">Date paid<input type="date" name="paid_at" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label></div>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Create</button></div>
    </form></dialog>
    <dialog data-sub-dialog="activate" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.activate', ['school' => 0]) }}" data-guard-base="Record the payment of" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Activate or renew <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">Records the payment of <strong data-sub-amount></strong> (the amount this school owes) and makes the school Annual for a year more.</p>
        <label class="block text-xs font-semibold text-on-surface-variant">Reference no. of the payment<input name="reference" required maxlength="100" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label>
        <label class="block text-xs font-semibold text-on-surface-variant">Date paid<input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Record payment</button></div>
    </form></dialog>
    <dialog data-sub-dialog="extend" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.extend', ['school' => 0]) }}" data-guard-base="Extend" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Extend <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">Adds days to a trial, a Free plan or an Annual that is running or in its grace; an expired school gets the days as grace.</p>
        <label class="block text-xs font-semibold text-on-surface-variant">Days (1 to 365)<input type="number" name="days" required min="1" max="365" value="7" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary text-right"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Extend</button></div>
    </form></dialog>
    <dialog data-sub-dialog="suspend" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.suspend', ['school' => 0]) }}" data-guard-base="Suspend" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Suspend <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">The school becomes read-only until you reactivate it.</p>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Suspend</button></div>
    </form></dialog>
    <dialog data-sub-dialog="reactivate" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.reactivate', ['school' => 0]) }}" data-guard-base="Reactivate" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Reactivate <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">The school goes back to the state it had before it was suspended.</p>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Reactivate</button></div>
    </form></dialog>
    <dialog data-sub-dialog="free" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.free.grant', ['school' => 0]) }}" data-guard-base="Free plan for" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Free plan <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">The whole system, with nothing limited, until the date you set. When the date comes the school becomes read-only.</p>
        <label class="block text-xs font-semibold text-on-surface-variant">Free until<input type="date" name="ends_at" required min="{{ now()->toDateString() }}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Save</button></div>
    </form></dialog>
    <dialog data-sub-dialog="free-remove" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.free.remove', ['school' => 0]) }}" data-guard-base="Take the Free plan back from" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Take the Free plan back <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">The school goes back to the state it had before the Free plan (read-only when it had none).</p>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Take it back</button></div>
    </form></dialog>
    <dialog data-sub-dialog="price" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.price', ['school' => 0]) }}" data-guard-base="Set the price of" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">What this school pays <span class="font-normal text-on-surface-variant">· <span data-sub-name></span></span></h2>
        <p class="text-xs text-on-surface-variant">The school sees the list price, its discount and this amount to pay. Leave the amount empty to go back to the list price (₱{{ number_format(\App\Services\SubscriptionPricing::LIST_PRICE, 2) }}).</p>
        <label class="block text-xs font-semibold text-on-surface-variant">Amount to pay (₱1.00 to ₱1,000.00)<input type="number" name="amount" min="1" max="1000" step="0.01" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary text-right"></label>
        <label class="block text-xs font-semibold text-on-surface-variant">Until (optional)<input type="date" name="ends_at" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Save</button></div>
    </form></dialog>
    <dialog data-sub-dialog="view" style="width:min(34rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><div class="max-h-[85vh] overflow-y-auto p-6">
        <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold" data-sub-view-title>Subscription</h2><button type="button" data-sub-close aria-label="Close" class="rounded p-1 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
        <div data-sub-view-body class="mt-3 space-y-3 text-sm"></div>
    </div></dialog>
    <script>
        // The buttons of the table open their window (the form of the action, or the details); each action then asks for a reason and the password.
        (() => {
            const dialogOf = (name) => document.querySelector('[data-sub-dialog="' + name + '"]');
            const text = (value) => document.createTextNode(value ?? '');
            document.addEventListener('click', async (event) => {
                const close = event.target.closest('[data-sub-close]');
                if (close) { close.closest('dialog')?.close(); return; }
                const button = event.target.closest('[data-sub-open]');
                if (!button) return;
                const dialog = dialogOf(button.dataset.subOpen);
                if (!dialog) return;
                const form = dialog.querySelector('form');
                if (form) {
                    form.action = form.dataset.action ? form.dataset.action.replace('/0/', '/' + button.dataset.id + '/') : form.action;
                    if (form.dataset.guardBase) form.dataset.guard = form.dataset.guardBase + ' ' + button.dataset.name;
                    form.reset();
                    delete form.dataset.guarded;
                    form.querySelectorAll('[data-sub-name]').forEach((span) => { span.textContent = button.dataset.name ?? ''; });
                    form.querySelectorAll('[data-sub-amount]').forEach((span) => { span.textContent = '₱' + (button.dataset.amount ?? ''); });
                    const ends = form.querySelector('input[name="ends_at"]');
                    if (ends && button.dataset.ends) ends.value = button.dataset.ends;
                    const price = form.querySelector('input[name="amount"]');
                    if (price && button.dataset.subOpen === 'price') price.value = button.dataset.amount ?? '';
                }
                if (button.dataset.subOpen === 'view') await renderView(dialog, button.dataset.url);
                dialog.showModal();
            });

            const renderView = async (dialog, url) => {
                const title = dialog.querySelector('[data-sub-view-title]');
                const body = dialog.querySelector('[data-sub-view-body]');
                body.replaceChildren(text('Loading…'));
                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('failed');
                    const data = await response.json();
                    title.textContent = data.school;
                    const row = (name, value) => { const line = document.createElement('div'); line.className = 'flex justify-between gap-4 border-b border-outline-variant/30 py-1.5'; const left = document.createElement('span'); left.className = 'text-on-surface-variant'; left.textContent = name; const right = document.createElement('span'); right.className = 'font-semibold text-right'; right.textContent = value; line.append(left, right); return line; };
                    const peso = (value) => '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                    const dates = data.dates;
                    body.replaceChildren(
                        row('Plan', data.plan), row('Status', data.status.replace('_', ' ')),
                        row('List price', peso(data.list_price)), row('Discount', peso(data.discount)), row('Amount to pay', peso(data.amount_to_pay)),
                        row('Started', dates.starts ?? '—'), row('Ends', dates.ends ?? '—'), row('Grace until', dates.grace_until ?? '—'),
                    );
                    const heading = document.createElement('h3'); heading.className = 'pt-2 text-xs font-bold uppercase tracking-wider text-on-surface-variant'; heading.textContent = 'Payments';
                    body.append(heading);
                    if (!data.payments.length) { const none = document.createElement('p'); none.className = 'text-xs text-on-surface-variant'; none.textContent = 'No payment recorded yet.'; body.append(none); }
                    data.payments.forEach((payment) => body.append(row(payment.paid_at + ' · ' + (payment.reference ?? ''), peso(payment.amount.replace(',', '')) + ' · ' + payment.period)));
                } catch (error) { body.replaceChildren(text('The details could not be loaded.')); }
            };

            // Create Subscription: the payment fields appear for the Annual plan.
            document.querySelector('[data-sub-mode]')?.addEventListener('change', (event) => {
                const annual = event.target.value === 'annual';
                const box = event.target.closest('form').querySelector('[data-sub-annual]');
                box.classList.toggle('hidden', !annual);
                box.querySelectorAll('input').forEach((input) => { input.required = annual; });
            });

            // Search and status filter: they work on the rows on the page, and keep their values when the live data redraws the table.
            const filters = () => ({ search: document.querySelector('[data-sub-filter="search"]')?.value ?? '', status: document.querySelector('[data-sub-filter="status"]')?.value ?? '' });
            const apply = () => {
                const { search, status } = filters();
                const needle = search.trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('[data-sub-row]').forEach((row) => {
                    const visible = (!needle || row.dataset.name.includes(needle)) && (!status || row.dataset.status === status);
                    row.classList.toggle('hidden', !visible);
                    if (visible) shown++;
                });
                document.querySelector('[data-sub-none]')?.classList.toggle('hidden', shown > 0 || !document.querySelector('[data-sub-row]'));
                const count = document.querySelector('[data-sub-count]');
                if (count) count.textContent = shown;
            };
            document.addEventListener('input', (event) => { if (event.target.closest('[data-sub-filter]')) apply(); });
            document.addEventListener('change', (event) => { if (event.target.closest('[data-sub-filter]')) apply(); });
            window.subFilters = { save: filters, restore: (saved) => { const search = document.querySelector('[data-sub-filter="search"]'); const status = document.querySelector('[data-sub-filter="status"]'); if (search) search.value = saved.search; if (status) status.value = saved.status; apply(); } };
        })();
    </script>
    @include('partials.action-guard')
    </div></main></div>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
