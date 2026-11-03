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
        @foreach($navigation as $item)<a href="{{ isset($item['route']) ? route($item['route']) : ($item['label'] === 'School Settings' ? route('school-settings') : '#') }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if(($item['label'] ?? '') === 'Dashboard')<details class="group"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('financial-reports') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">assessment</span>Financial Reports</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
    </nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

    <div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Subscription Administration</p><p class="truncate text-sm font-semibold text-on-surface">Subscriptions</p></div></div><div class="flex items-center gap-4"><button class="relative rounded p-1 text-on-surface-variant" aria-label="Notifications"><span class="material-symbols-outlined text-[21px]">notifications</span><span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-error"></span></button><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></div></header>

    <main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]" data-sub-tabs data-active="overview">
        <style>
            [data-sub-tabs][data-active="overview"] [data-tab-panel]:not([data-tab-panel="overview"]), [data-sub-tabs][data-active="schools"] [data-tab-panel]:not([data-tab-panel="schools"]), [data-sub-tabs][data-active="payments"] [data-tab-panel]:not([data-tab-panel="payments"]), [data-sub-tabs][data-active="settings"] [data-tab-panel]:not([data-tab-panel="settings"]) { display: none; }
            [data-sub-tabs][data-active="overview"] [data-sub-tab="overview"], [data-sub-tabs][data-active="schools"] [data-sub-tab="schools"], [data-sub-tabs][data-active="payments"] [data-sub-tab="payments"], [data-sub-tabs][data-active="settings"] [data-sub-tab="settings"] { background: #103967; border-color: #103967; color: #fff; }
        </style>
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">card_membership</span><span class="font-semibold text-primary">Plans &amp; Billing</span>@if(auth()->user()->isMaster())<span class="ml-2 inline-flex items-center gap-1 rounded bg-primary px-2 py-0.5 text-[10px] font-bold tracking-wider text-white"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">shield_person</span>Super Admin · {{ auth()->user()->name }}</span>@endif</div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Subscriptions</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Manage school plans, renewals, billing status, and organization subscriptions.</p></div><button type="button" data-sub-open="create" class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">add</span>Create Subscription</button></div>

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

    @if(auth()->user()->isMaster())
    <section data-payment-settings data-tab-panel="settings" class="mt-8 overflow-hidden rounded-2xl border border-outline-variant/40 bg-white">
        @php
            $payReady = filled($paymentSettings['account_name']) && filled($paymentSettings['account_number']);
            $payEmail = filled($paymentSettings['master_email']);
            $payField = 'mt-1.5 w-full rounded-xl border border-outline-variant/60 bg-white px-3.5 py-2.5 text-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10';
            $payLabel = 'block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant';
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/30 px-6 py-5">
            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined" aria-hidden="true">account_balance</span></span><div><h2 class="text-lg font-semibold tracking-tight">Payment Settings</h2><p class="text-xs text-on-surface-variant">Where a school sends its subscription payment. The schools see this on their Subscription page.</p></div></div>
            <div class="flex flex-wrap gap-2" data-payment-status>
                <{{ $payReady ? 'span' : 'a href=#pay-account data-pay-goto=pay-account' }} class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $payReady ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800 hover:bg-amber-200' }}"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $payReady ? 'check_circle' : 'error' }}</span>{{ $payReady ? 'Account is ready' : 'Complete the account name and number' }}</{{ $payReady ? 'span' : 'a' }}>
                <{{ $payEmail ? 'span' : 'a href=#pay-email data-pay-goto=pay-email' }} class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $payEmail ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800 hover:bg-amber-200' }}"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $payEmail ? 'check_circle' : 'mail' }}</span>{{ $payEmail ? 'Proofs are shared with your email' : 'Add your Google email: schools cannot send a proof yet' }}</{{ $payEmail ? 'span' : 'a' }}>
            </div>
        </div>
        <form method="POST" action="{{ route('payment-settings.update') }}" enctype="multipart/form-data" data-guard="Save the payment details" class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_21rem]">@csrf @method('PUT')
            <div class="flex flex-col gap-5">
                <fieldset class="rounded-xl border border-outline-variant/40 bg-surface-low/40 p-5">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-primary">1 · The account</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="{{ $payLabel }}">Bank<input name="bank_name" data-pay-bind="bank" maxlength="120" placeholder="GoTyme Bank" value="{{ $paymentSettings['bank_name'] }}" class="{{ $payField }}"></label>
                        <label class="{{ $payLabel }}">Account name<input id="pay-account" name="account_name" data-pay-bind="name" maxlength="120" placeholder="Name on the account" value="{{ $paymentSettings['account_name'] }}" class="{{ $payField }}"></label>
                        <label class="{{ $payLabel }}">Account number<input name="account_number" data-pay-bind="number" maxlength="60" inputmode="numeric" placeholder="0000 0000 0000" value="{{ $paymentSettings['account_number'] }}" class="{{ $payField }} tabular-nums tracking-wider"></label>
                    </div>
                </fieldset>
                <div class="grid flex-1 gap-5 xl:grid-cols-2">
                <fieldset class="flex flex-col rounded-xl border border-outline-variant/40 bg-surface-low/40 p-5">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-primary">2 · Where the proofs go</legend>
                    <label class="{{ $payLabel }}">Your Google email<input id="pay-email" type="email" name="master_email" maxlength="190" autocomplete="email" placeholder="you@gmail.com" value="{{ $paymentSettings['master_email'] }}" class="{{ $payField }}"></label>
                    <p class="mt-2 text-xs leading-relaxed text-on-surface-variant">Each school keeps its proof in its own Google Drive and shares it read-only with this address only (never public). Use a Google account you can open.</p>
                </fieldset>
                <fieldset class="flex flex-col rounded-xl border border-outline-variant/40 bg-surface-low/40 p-5">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-primary">3 · The QR code</legend>
                    <label data-pay-drop class="flex flex-1 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-outline-variant/70 bg-white px-4 py-6 text-center transition hover:border-primary hover:bg-primary/5">
                        <img data-pay-thumb src="{{ $paymentSettings['qr'] ?: '' }}" alt="Saved QR code" class="{{ $paymentSettings['qr'] ? '' : 'hidden' }} h-24 w-24 rounded-lg border border-outline-variant/40 bg-white object-contain p-1">
                        <span data-pay-icon class="material-symbols-outlined text-[28px] text-primary {{ $paymentSettings['qr'] ? 'hidden' : '' }}" aria-hidden="true">qr_code_2</span>
                        <span class="text-sm font-semibold text-on-surface" data-pay-title>{{ $paymentSettings['qr'] ? 'Replace the image or drop a new one' : 'Choose an image or drop it here' }}</span>
                        <span class="text-xs text-on-surface-variant" data-pay-file>PNG or JPG, up to 300 KB</span>
                        <input type="file" name="qr" accept="image/png,image/jpeg" data-pay-qr class="sr-only">
                    </label>
                    <p class="mt-2 hidden text-xs font-semibold text-error" data-pay-error></p>
                    @if($paymentSettings['qr'])<label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-on-surface-variant"><input type="checkbox" name="remove_qr" value="1" data-pay-remove class="h-4 w-4 rounded border-outline-variant">Remove the QR code that is saved</label>@endif
                </fieldset>
                </div>
            </div>
            <aside class="lg:sticky lg:top-24 lg:self-start" aria-label="What a school sees">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">What a school sees</p>
                <div class="overflow-hidden rounded-2xl p-5 text-white shadow-lg" style="background:linear-gradient(145deg,#0b2a66 0%,#103967 55%,#1b5088 100%)">
                    <p class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[.14em] text-white/60"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">account_balance</span>Send the payment to</p>
                    <p class="mt-3 text-sm font-semibold text-white/80" data-pay-view="bank">{{ $paymentSettings['bank_name'] ?: 'Bank' }}</p>
                    <div class="mt-0.5 flex items-center justify-between gap-2"><p class="min-w-0 break-words text-lg font-semibold tracking-tight" data-pay-view="name">{{ $paymentSettings['account_name'] ?: 'Account name' }}</p><button type="button" data-pay-copy="name" class="shrink-0 rounded-md border border-white/30 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-white/90 hover:bg-white/15">Copy</button></div>
                    <div class="mt-1 flex items-center justify-between gap-2"><p class="min-w-0 break-all font-mono text-base tracking-[.12em] tabular-nums text-white/90" data-pay-view="number">{{ $paymentSettings['account_number'] ?: '0000 0000 0000' }}</p><button type="button" data-pay-copy="number" class="shrink-0 rounded-md border border-white/30 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-white/90 hover:bg-white/15">Copy</button></div>
                    <div class="mt-4 grid place-items-center rounded-xl bg-white p-3"><img data-pay-view="qr" src="{{ $paymentSettings['qr'] ?: '' }}" alt="QR code of the account" class="{{ $paymentSettings['qr'] ? '' : 'hidden' }} h-64 w-64 object-contain"><p data-pay-view="noqr" class="{{ $paymentSettings['qr'] ? 'hidden' : '' }} py-12 text-xs font-medium text-on-surface-variant">No QR code yet</p></div>
                    <p class="mt-3 text-center text-xs font-semibold text-white/80">Scan with your bank app or e-wallet</p>
                    <p class="mt-1 text-center text-[11px] text-white/60">Amount to pay is shown on the school's page</p>
                </div>
            </aside>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/30 pt-5 lg:col-span-2"><p class="text-xs text-on-surface-variant">Saving asks for your reason and your password, and is kept in the history.</p><button class="rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-container">Save payment details</button></div>
        </form>
        <script>
            // The card on the right follows what is typed and the image that is chosen, before anything is saved.
            (() => {
                const root = document.querySelector('[data-payment-settings]');
                if (!root) return;
                const view = (key) => root.querySelector('[data-pay-view="' + key + '"]');
                const fallback = { bank: 'Bank', name: 'Account name', number: '0000 0000 0000' };
                // An account number of digits only is shown in groups of four on the card; what is saved is what is typed.
                const shown = (key, value) => { const clean = value.trim(); return key === 'number' && /^\d+$/.test(clean) ? clean.replace(/(\d{4})(?=\d)/g, '$1 ') : clean; };
                root.querySelectorAll('[data-pay-bind]').forEach((input) => input.addEventListener('input', () => { view(input.dataset.payBind).textContent = shown(input.dataset.payBind, input.value) || fallback[input.dataset.payBind]; }));
                view('number').textContent = shown('number', root.querySelector('[data-pay-bind="number"]').value) || fallback.number;
                root.querySelectorAll('[data-pay-copy]').forEach((button) => button.addEventListener('click', async () => {
                    const key = button.dataset.payCopy;
                    const input = root.querySelector('[data-pay-bind="' + key + '"]');
                    const value = key === 'number' ? input.value.replace(/\s+/g, '') : input.value.trim();
                    if (!value) return;
                    try { await navigator.clipboard.writeText(value); } catch (error) { input.select(); document.execCommand('copy'); }
                    const label = button.textContent; button.textContent = 'Copied'; setTimeout(() => { button.textContent = label; }, 1500);
                }));
                document.querySelectorAll('[data-pay-goto]').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); const field = document.getElementById(link.dataset.payGoto); field?.scrollIntoView({ behavior: 'smooth', block: 'center' }); field?.focus({ preventScroll: true }); }));

                const file = root.querySelector('[data-pay-qr]');
                const drop = root.querySelector('[data-pay-drop]');
                const label = root.querySelector('[data-pay-file]');
                const error = root.querySelector('[data-pay-error]');
                const show = (src) => {
                    const img = view('qr'); img.src = src; img.classList.toggle('hidden', !src); view('noqr').classList.toggle('hidden', Boolean(src));
                    const thumb = root.querySelector('[data-pay-thumb]'); thumb.src = src; thumb.classList.toggle('hidden', !src);
                    root.querySelector('[data-pay-icon]').classList.toggle('hidden', Boolean(src));
                    root.querySelector('[data-pay-title]').textContent = src ? 'Replace the image or drop a new one' : 'Choose an image or drop it here';
                };
                const fail = (message) => { error.textContent = message; error.classList.remove('hidden'); file.value = ''; label.textContent = 'PNG or JPG, up to 300 KB'; };
                const pick = (chosen) => {
                    error.classList.add('hidden');
                    if (!chosen) return;
                    if (!['image/png', 'image/jpeg'].includes(chosen.type)) return fail('The QR code must be a PNG or a JPG image.');
                    if (chosen.size > 300 * 1024) return fail('The image is ' + Math.round(chosen.size / 1024) + ' KB. The limit is 300 KB.');
                    label.textContent = chosen.name + ' · ' + Math.round(chosen.size / 1024) + ' KB';
                    const reader = new FileReader();
                    reader.onload = () => show(reader.result);
                    reader.readAsDataURL(chosen);
                    const remove = root.querySelector('[data-pay-remove]'); if (remove) remove.checked = false;
                };
                file.addEventListener('change', () => pick(file.files[0]));
                ['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.add('border-primary', 'bg-primary/5'); }));
                ['dragleave', 'drop'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.remove('border-primary', 'bg-primary/5'); }));
                drop.addEventListener('drop', (event) => { const dropped = event.dataTransfer?.files?.[0]; if (!dropped) return; const transfer = new DataTransfer(); transfer.items.add(dropped); file.files = transfer.files; pick(dropped); });
                root.querySelector('[data-pay-remove]')?.addEventListener('change', (event) => { if (event.target.checked) { show(''); file.value = ''; label.textContent = 'PNG or JPG, up to 300 KB'; } });
            })();
        </script>
    </section>

    <section data-promo-panel data-tab-panel="settings" class="mt-8 overflow-hidden rounded border border-outline-variant/30 bg-white">
        <div class="flex flex-col justify-between gap-3 border-b border-outline-variant/30 p-5 sm:flex-row sm:items-center"><div><h2 class="text-lg font-semibold">Promo Codes</h2><p class="mt-1 text-xs text-on-surface-variant">A school types a code to pay less for the Annual plan. The price you set for a school always wins over a code.</p></div><button type="button" data-promo-open class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">+ New promo code</button></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">Code</th><th class="px-5 py-3">Takes off</th><th class="px-5 py-3">Valid</th><th class="px-5 py-3">Used</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($promoCodes as $promo)
            @php $usable = $promo->usableAt(); @endphp
            <tr><td class="px-5 py-3 font-mono font-semibold">{{ $promo->code }}</td><td class="px-5 py-3 tabular-nums">{{ $promo->type === 'percent' ? rtrim(rtrim(number_format((float) $promo->value, 2), '0'), '.').'%' : '₱'.number_format((float) $promo->value, 2) }}</td><td class="px-5 py-3 text-xs text-on-surface-variant">{{ $promo->starts_at?->format('M d, Y') ?? 'now' }} to {{ $promo->ends_at?->format('M d, Y') ?? 'no end' }}</td><td class="px-5 py-3 tabular-nums">{{ $promo->used_count }}{{ $promo->max_uses ? ' / '.$promo->max_uses : '' }}</td><td class="px-5 py-3"><span class="inline-flex rounded-full {{ $usable ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error' }} px-2 py-1 text-xs font-semibold">{{ ! $promo->active ? 'Off' : ($usable ? 'On' : 'Not usable') }}</span></td>
            <td class="px-5 py-3"><div class="flex items-center justify-end gap-1.5"><button type="button" data-promo-open data-url="{{ route('promo-codes.update', $promo) }}" data-code="{{ $promo->code }}" data-type="{{ $promo->type }}" data-value="{{ (float) $promo->value }}" data-starts="{{ $promo->starts_at?->toDateString() }}" data-ends="{{ $promo->ends_at?->toDateString() }}" data-max="{{ $promo->max_uses }}" title="Edit" aria-label="Edit {{ $promo->code }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></button>
            <form method="POST" action="{{ route('promo-codes.toggle', $promo) }}" data-guard="{{ $promo->active ? 'Switch off' : 'Switch on' }} {{ $promo->code }}">@csrf<button title="{{ $promo->active ? 'Switch off' : 'Switch on' }}" aria-label="{{ $promo->active ? 'Switch off' : 'Switch on' }} {{ $promo->code }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $promo->active ? 'toggle_on' : 'toggle_off' }}</span></button></form></div></td></tr>
        @empty
            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-on-surface-variant">No promo code yet.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    <dialog data-promo-dialog style="width:min(32rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="{{ route('promo-codes.store') }}" data-store="{{ route('promo-codes.store') }}" data-guard="Save the promo code" class="space-y-3 p-6">@csrf<input type="hidden" name="_method" value="POST" data-promo-method>
        <h2 class="text-lg font-semibold" data-promo-title>New promo code</h2>
        <label class="block text-xs font-semibold text-on-surface-variant">Code (letters, numbers, - and _)<input name="code" required maxlength="40" pattern="[A-Za-z0-9_-]+" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary uppercase"></label>
        <div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold text-on-surface-variant">Kind<select name="type" required class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"><option value="percent">Percent off</option><option value="fixed">Pesos off</option></select></label><label class="block text-xs font-semibold text-on-surface-variant">Value<input type="number" name="value" required min="1" step="0.01" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary text-right"></label></div>
        <div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold text-on-surface-variant">Valid from (optional)<input type="date" name="starts_at" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">Valid until (optional)<input type="date" name="ends_at" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></label></div>
        <label class="block text-xs font-semibold text-on-surface-variant">Most uses (optional)<input type="number" name="max_uses" min="1" max="100000" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary text-right"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Save</button></div>
    </form></dialog>
    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-promo-open]');
            if (!button) return;
            const dialog = document.querySelector('[data-promo-dialog]');
            const form = dialog.querySelector('form');
            form.reset(); delete form.dataset.guarded;
            const editing = Boolean(button.dataset.url);
            form.action = editing ? button.dataset.url : form.dataset.store;
            form.querySelector('[data-promo-method]').value = editing ? 'PUT' : 'POST';
            dialog.querySelector('[data-promo-title]').textContent = editing ? 'Edit ' + button.dataset.code : 'New promo code';
            if (editing) {
                form.elements.code.value = button.dataset.code; form.elements.type.value = button.dataset.type; form.elements.value.value = button.dataset.value;
                form.elements.starts_at.value = button.dataset.starts ?? ''; form.elements.ends_at.value = button.dataset.ends ?? ''; form.elements.max_uses.value = button.dataset.max ?? '';
            }
            dialog.showModal();
        });
    </script>
    @endif

    <dialog data-sub-dialog="approve-proof" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.proofs.approve', ['proof' => 0]) }}" data-guard-base="Approve the proof of" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Approve the proof of payment</h2>
        <p class="text-xs text-on-surface-variant"><span data-sub-name></span></p>
        <p class="text-xs text-on-surface-variant">Records the payment of <strong data-sub-amount></strong> and makes the school Annual for a year. The reason you type is kept in the history.</p>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Approve</button></div>
    </form></dialog>
    <dialog data-sub-dialog="reject-proof" style="width:min(30rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="" data-action="{{ route('subscriptions.proofs.reject', ['proof' => 0]) }}" data-guard-base="Reject the proof of" class="space-y-3 p-6">@csrf
        <h2 class="text-lg font-semibold">Reject the proof of payment</h2>
        <p class="text-xs text-on-surface-variant"><span data-sub-name></span></p>
        <p class="text-xs text-on-surface-variant">The reason you type is shown to the school, so say what is wrong (for example, the amount or the reference cannot be read).</p>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" data-sub-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Reject</button></div>
    </form></dialog>
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
    <dialog data-sub-dialog="proof-view" style="width:min(64rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><div class="p-5">
        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate text-lg font-semibold" data-proof-view-title>Proof of payment</h2><p class="mt-0.5 text-xs text-on-surface-variant">View only. The file stays in the school's own Google Drive; nothing is saved on this system. Sign in to Google with the email that received the share.</p></div><button type="button" data-sub-close aria-label="Close" class="rounded p-1 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
        <iframe data-proof-view-frame title="Proof of payment" class="mt-3 h-[70vh] w-full rounded border border-outline-variant/40 bg-surface-low" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>
        <p class="mt-2 text-right text-xs"><a data-proof-view-link href="#" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">Open in Google Drive</a></p>
    </div></dialog>
    <dialog data-sub-dialog="school" style="width:min(40rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><div class="max-h-[85vh] overflow-y-auto p-6">
        <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold" data-sub-view-title>School</h2><button type="button" data-sub-close aria-label="Close" class="rounded p-1 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
        <div data-sub-view-body class="mt-3 space-y-3 text-sm"></div>
        <h3 class="mt-5 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Actions</h3>
        <div data-sub-school-actions class="mt-2 flex flex-wrap gap-2"></div>
    </div></dialog>
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
                const proofView = event.target.closest('[data-proof-view]');
                if (proofView) {
                    const viewer = dialogOf('proof-view');
                    viewer.querySelector('[data-proof-view-title]').textContent = proofView.dataset.title || 'Proof of payment';
                    viewer.querySelector('[data-proof-view-frame]').src = 'https://drive.google.com/file/d/' + encodeURIComponent(proofView.dataset.proofView) + '/preview';
                    viewer.querySelector('[data-proof-view-link]').href = proofView.dataset.link;
                    viewer.addEventListener('close', () => viewer.querySelector('[data-proof-view-frame]').removeAttribute('src'), { once: true });
                    viewer.showModal();
                    return;
                }
                const schoolRow = event.target.closest('[data-sub-school]');
                if (schoolRow) { await openSchool(schoolRow); return; }
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

            // A click on a school opens its details; the actions of the school are in that window, each opening its own form.
            const openSchool = async (row) => {
                const dialog = dialogOf('school');
                const actions = dialog.querySelector('[data-sub-school-actions]');
                actions.replaceChildren();
                row.querySelector('template[data-sub-actions]')?.content.querySelectorAll('button').forEach((source) => {
                    const button = source.cloneNode(true);
                    const danger = button.classList.contains('text-error');
                    button.className = 'inline-flex items-center gap-2 rounded border bg-white px-3 py-2 text-xs font-semibold hover:bg-surface-low ' + (danger ? 'border-error/50 text-error' : 'border-outline-variant/60 text-primary');
                    button.append(text(button.title));
                    actions.append(button);
                });
                dialog.querySelector('[data-sub-view-title]').textContent = row.dataset.title;
                dialog.showModal();
                await renderView(dialog, row.dataset.url);
            };
            document.addEventListener('keydown', (event) => {
                const row = event.target.closest?.('[data-sub-school]');
                if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openSchool(row); }
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
            // Payment requests: search and status filter, kept when the live data redraws the list.
            const proofState = { search: '', status: '' };
            const applyProofs = () => {
                const search = document.querySelector('[data-proof-filter="search"]');
                const status = document.querySelector('[data-proof-filter="status"]');
                if (search) search.value = proofState.search;
                if (status) status.value = proofState.status;
                const needle = proofState.search.trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('[data-proof-row]').forEach((row) => {
                    const visible = (!needle || row.dataset.text.includes(needle)) && (!proofState.status || row.dataset.status === proofState.status);
                    row.classList.toggle('hidden', !visible);
                    if (visible) shown++;
                });
                document.querySelector('[data-proof-none]')?.classList.toggle('hidden', shown > 0 || !document.querySelector('[data-proof-row]'));
            };
            document.addEventListener('input', (event) => { const control = event.target.closest('[data-proof-filter]'); if (control) { proofState[control.dataset.proofFilter] = control.value; applyProofs(); } });
            document.addEventListener('change', (event) => { const control = event.target.closest('[data-proof-filter]'); if (control) { proofState[control.dataset.proofFilter] = control.value; applyProofs(); } });

            // The tabs: one group of the page at a time; the last one used is remembered while the browser tab stays open.
            const tabsRoot = document.querySelector('[data-sub-tabs]');
            const openTab = (name) => {
                if (!tabsRoot.querySelector('[data-sub-tab="' + name + '"]')) name = 'overview';
                tabsRoot.dataset.active = name;
                try { sessionStorage.setItem('subscriptionsTab', name); } catch (error) {}
            };
            document.addEventListener('click', (event) => { const tab = event.target.closest('[data-sub-tab]'); if (tab) openTab(tab.dataset.subTab); });
            let remembered = 'overview';
            try { remembered = sessionStorage.getItem('subscriptionsTab') || 'overview'; } catch (error) {}
            openTab(remembered);

            window.subFilters = { save: filters, restore: (saved) => { const search = document.querySelector('[data-sub-filter="search"]'); const status = document.querySelector('[data-sub-filter="status"]'); if (search) search.value = saved.search; if (status) status.value = saved.status; apply(); applyProofs(); openTab(tabsRoot.dataset.active); } };
        })();
    </script>
    @include('partials.action-guard')
    </div></main></div>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
