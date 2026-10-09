<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $section === 'cash' ? 'Cash' : 'Accounting' }} · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4','surface-high':'#e9e7ef',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.input-fixes')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isCash = $section === 'cash';
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']]; if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); }
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    $sub = [['Budget','account_balance','budget','budget'],['Accounting','request_quote','accounting','accounting'],['Chart of Accounts','list_alt','chart-of-accounts','chart-of-accounts'],['Allotment Registry','menu_book','allotment-registry','allotment-registry'],['Cash','payments','cash','cash']];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tabs = $isCash
        ? ['unpaid' => 'DVs For Payment', 'paid' => 'Paid']
        : ['for_review' => 'For Review', 'pending_documents' => 'Pending Documents', 'returned' => 'Returned', 'for_dv' => 'Ready for DV', 'with_dv' => 'With DV', 'all' => 'All'];
    $tones = ['for_review' => 'error', 'pending_documents' => 'primary', 'approved' => 'secondary', 'returned' => 'error', 'draft' => 'on-surface-variant'];
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col bg-primary px-4 py-6 text-white md:flex"><div class="mb-8 flex items-center gap-3 px-2"><div class="flex h-8 w-8 items-center justify-center rounded bg-secondary"><span class="material-symbols-outlined text-[20px]">school</span></div><span class="text-xl font-semibold">ProcureMS</span></div><nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)<a href="{{ route($item['route']) }}" class="flex items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if($item['label']==='Dashboard')<details class="group" open><summary class="flex cursor-pointer list-none items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1">@foreach($sub as [$label,$icon,$r,$key])<a href="{{ route($r) }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm {{ $key===$section ? 'bg-primary-container font-semibold text-white' : 'text-white/75 hover:bg-primary-container hover:text-white' }}"><span class="material-symbols-outlined mr-2 text-[17px]">{{ $icon }}</span>{{ $label }}</a>@endforeach</div></details>@endif @endforeach
</nav><div class="border-t border-white/15 pt-4 text-xs text-white/60"><p>Multi-School Procurement System</p></div></aside>

<div class="md:pl-72"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-72 md:px-6"><span class="text-sm font-semibold text-on-surface-variant">Finance · {{ $isCash ? 'Cash' : 'Accounting' }}</span><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
    <div class="mb-8"><h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $isCash ? 'Cash Operations' : 'Accounting Review' }}</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $isCash ? 'Disbursement vouchers created in Accounting, ready for payment. Record the check / ADA reference and release date once paid.' : 'Review ORS entries created in Budget, then approve them and create the Disbursement Voucher (DV) that goes to Cash for payment.' }}</p></div>

    @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metrics as [$label,$value,$icon,$tone,$money])<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ $money ? $peso($value) : $value }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $tone }}/10 text-{{ $tone }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></article>@endforeach</div>

    @php
        $tabMeta = [
            'for_review' => ['pending_actions', 'bg-error text-white'], 'pending_documents' => ['folder_open', 'bg-amber-500 text-white'], 'returned' => ['undo', 'bg-error/80 text-white'],
            'for_dv' => ['description', 'bg-secondary text-white'], 'with_dv' => ['task_alt', 'bg-primary text-white'], 'all' => ['list', 'bg-on-surface-variant text-white'],
            'unpaid' => ['hourglass_top', 'bg-error text-white'], 'paid' => ['check_circle', 'bg-secondary text-white'],
        ];
        $statusIcons = ['for_review' => 'pending_actions', 'pending_documents' => 'folder_open', 'approved' => 'check_circle', 'returned' => 'undo', 'draft' => 'edit_note'];
        $accents = ['for_review' => 'border-l-error', 'pending_documents' => 'border-l-amber-500', 'approved' => 'border-l-secondary', 'returned' => 'border-l-error/60', 'draft' => 'border-l-outline-variant'];
    @endphp
    <section class="overflow-hidden rounded-xl border border-outline-variant/40 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/30 bg-surface-low/60 p-3">
            <nav class="flex flex-wrap gap-1.5" aria-label="Views">
                @foreach($tabs as $key => $label)
                    @php [$tabIcon, $badge] = $tabMeta[$key] ?? ['list', 'bg-primary text-white']; $count = $key === 'all' ? null : $counts->get($key, 0); @endphp
                    <a href="{{ route($section, ['tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition {{ $tab === $key ? 'bg-primary text-white shadow' : 'bg-white text-on-surface-variant ring-1 ring-outline-variant/40 hover:bg-surface-high' }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $tabIcon }}</span>{{ $label }}@if($count !== null)<span class="min-w-[1.25rem] rounded-full px-1.5 py-0.5 text-center text-[10px] leading-none {{ $tab === $key ? 'bg-white/25 text-white' : ($count > 0 ? $badge : 'bg-surface-high text-on-surface-variant') }}">{{ $count }}</span>@endif</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-2">
                <label class="relative"><span class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant" aria-hidden="true">search</span><input id="row-search" type="search" placeholder="Search ORS, payee, school, DV…" class="w-64 rounded-lg border border-outline-variant/50 bg-white py-2 pl-9 pr-3 text-xs outline-none focus:border-primary"></label>
                <span id="row-count" class="whitespace-nowrap text-xs text-on-surface-variant">{{ count($rows) }} shown</span>
            </div>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-surface-low text-[11px] uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">ORS / Particulars</th><th class="px-5 py-3">School</th><th class="px-5 py-3">Fund</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">{{ $isCash ? 'Voucher / Payment' : 'Status / DV' }}</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($rows as $r)
            @php
                $tone = $tones[$r->status] ?? 'primary'; $label = $r->ors_number ?: $r->report_number;
                $accent = $isCash ? ($r->paid_at ? 'border-l-secondary' : 'border-l-error') : ($r->dv_number ? 'border-l-primary' : ($accents[$r->status] ?? 'border-l-outline-variant'));
                $payee = $r->payee ?: ($r->procurementRequest?->supplier_name ?? $r->school?->name);
            @endphp
            <tr data-row class="align-top border-l-4 {{ $accent }} hover:bg-surface-low/60">
                <td class="px-5 py-4">
                    <div class="flex flex-wrap items-center gap-2"><span class="font-bold text-primary">{{ $label }}</span>@if($r->procurementRequest)<span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">PR {{ $r->procurementRequest->request_number }}</span>@else<span class="rounded bg-surface-container px-1.5 py-0.5 text-[10px] font-bold uppercase text-on-surface-variant">No PR · Direct</span>@endif</div>
                    <div class="mt-1 text-xs text-on-surface">{{ $r->purpose ?: ($r->procurementRequest?->title ?? 'General') }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-on-surface-variant"><span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">storefront</span>{{ $payee }}</span><span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">schedule</span>{{ ($r->approved_at ?? $r->submitted_at ?? $r->created_at)?->diffForHumans() }}</span></div>
                </td>
                <td class="px-5 py-4 text-[13px]">{{ $r->school?->name }}</td>
                <td class="px-5 py-4"><span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold text-on-surface-variant">{{ $r->source_of_fund ?: '—' }}</span></td>
                <td class="px-5 py-4 text-right text-[15px] font-bold tabular-nums">{{ $peso($r->amount) }}</td>
                <td class="px-5 py-4">
                    @if($isCash)
                        <div class="font-bold">DV {{ $r->dv_number }}</div>
                        <div class="text-xs text-on-surface-variant">Payee: {{ $payee }}</div>
                        @if($r->paid_at)
                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-bold text-secondary"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>Paid {{ $r->paid_at->format('M d, Y') }}</span>
                            <div class="mt-1 text-xs text-on-surface-variant">{{ $r->payment_mode }}@if($r->payment_reference) · {{ $r->payment_reference }}@endif</div>
                        @else
                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-bold text-error"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>Awaiting payment · {{ $r->payment_mode }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-{{ $tone }}/10 px-2.5 py-1 text-xs font-bold text-{{ $tone }}"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $statusIcons[$r->status] ?? 'info' }}</span>{{ \Illuminate\Support\Str::headline($r->status) }}</span>
                        @if($r->dv_number)
                            <div class="mt-1.5 text-xs font-bold text-secondary">DV {{ $r->dv_number }}<span class="ml-1 font-normal text-on-surface-variant">· {{ $r->dv_date?->format('M d, Y') }}</span></div>
                            @if($r->journalLines->isNotEmpty())
                                <details class="mt-1 max-w-[300px] rounded-lg bg-surface-low text-[11px]"><summary class="flex cursor-pointer list-none items-center gap-1 px-2 py-1 font-bold text-primary"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">balance</span>Journal entry · {{ $r->journalLines->count() }} lines<span class="material-symbols-outlined ml-auto text-[14px]" aria-hidden="true">expand_more</span></summary>
                                    <ul class="space-y-0.5 border-t border-outline-variant/30 px-2 py-1.5">@foreach($r->journalLines as $line)<li class="flex items-start justify-between gap-2"><span class="{{ $line->credit > 0 ? 'pl-3' : '' }} text-on-surface-variant"><span class="font-semibold text-on-surface">{{ $line->account_code }}</span> {{ \Illuminate\Support\Str::limit($line->account_title, 34) }}</span><span class="whitespace-nowrap tabular-nums"><span class="font-bold {{ $line->debit > 0 ? 'text-primary' : 'text-secondary' }}">{{ $line->debit > 0 ? 'Dr' : 'Cr' }}</span> {{ number_format((float) ($line->debit > 0 ? $line->debit : $line->credit), 2) }}</span></li>@endforeach</ul></details>
                            @else
                                <div class="mt-1 text-[11px] text-on-surface-variant">No journal entry (created before it was required)</div>
                            @endif
                        @endif
                        @if($r->accounting_remarks)<div class="mt-1.5 max-w-[240px] rounded-lg border-l-2 border-amber-500 bg-amber-50 px-2 py-1 text-[11px] text-amber-900">{{ $r->accounting_remarks }}</div>@endif
                    @endif
                </td>
                <td class="px-5 py-4 text-right"><div class="flex flex-wrap items-center justify-end gap-1.5">
                    @if($isMasterUser && $isCash && ! $r->paid_at)<button type="button" data-pay="{{ route('cash.pay', $r) }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-mode="{{ $r->payment_mode }}" class="inline-flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">payments</span>Record Payment</button>@endif
                    @if($isMasterUser && ! $isCash && $r->status === 'approved' && ! $r->dv_number)<button type="button" data-dv="{{ route('accounting.dv.store', $r) }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-amount-raw="{{ number_format((float) $r->amount, 2, '.', '') }}" data-expense-code="{{ $r->chargedLine()?->account?->code ?? $r->chargedLine()?->uacs_code }}" data-payee="{{ $r->payee ?: ($r->procurementRequest?->supplier_name ?? $r->school?->name) }}" data-particulars="{{ $r->purpose ?: $r->procurementRequest?->title }}" class="inline-flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">receipt_long</span>Create DV</button>@endif
                    @if($isMasterUser && ! $isCash && ! $r->dv_number && $r->status !== 'approved')<form method="POST" action="{{ route('accounting.review', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-primary-container"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">check</span>Approve</button></form>@endif
                    <a href="{{ route('liquidation.print', $r) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-outline-variant/50 hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">print</span>ORS</a>
                    @if($r->dv_number)
                        <a href="{{ route('accounting.dv.print', $r) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-outline-variant/50 hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">print</span>DV</a>
                        @if(! $isCash && auth()->user()->hasPermission('accounting.approve'))<form method="POST" action="{{ route('accounting.dv.options', $r) }}">@csrf @method('PATCH')<input type="hidden" name="dv_include_appropriation" value="{{ $r->dv_include_appropriation ? 0 : 1 }}"><button class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-on-surface-variant ring-1 ring-outline-variant/50 hover:bg-surface-low" title="Show or hide the APPROPRIATION table on the printed DV">Appropriation table: {{ $r->dv_include_appropriation ? 'On' : 'Off' }}</button></form>@endif
                    @endif
                    @if($isMasterUser && ! $isCash && ! $r->dv_number)
                        <button type="button" data-review="{{ route('accounting.review', $r) }}" data-status="pending_documents" data-label="{{ $label }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-outline-variant/50 hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">folder_open</span>Request Docs</button>
                        <button type="button" data-review="{{ route('accounting.review', $r) }}" data-status="returned" data-label="{{ $label }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-error ring-1 ring-error/30 hover:bg-error hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">undo</span>Return</button>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-14 text-center text-on-surface-variant"><span class="material-symbols-outlined mb-1 block text-[34px] text-outline-variant" aria-hidden="true">inbox</span>{{ $isCash ? 'Nothing here. Approve ORS entries in Accounting to queue them for payment.' : 'No ORS entries in this view.' }}</td></tr>
        @endforelse
        <tr id="row-empty" hidden><td colspan="6" class="px-5 py-10 text-center text-sm text-on-surface-variant">No entry matches your search.</td></tr>
        </tbody></table></div>
    </section>
    <script>
        (() => {
            const input = document.getElementById('row-search');
            const rows = [...document.querySelectorAll('tr[data-row]')];
            const count = document.getElementById('row-count');
            const empty = document.getElementById('row-empty');
            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                let shown = 0;
                rows.forEach((row) => { const match = ! term || row.textContent.toLowerCase().includes(term); row.hidden = ! match; if (match) shown++; });
                count.textContent = shown + ' shown';
                empty.hidden = shown > 0 || rows.length === 0;
            });
        })();
    </script>
</div></main></div>

<div id="pay-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 class="text-lg font-semibold">Record Payment</h2><p id="pay-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="pay-form" method="POST" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf
        <label class="text-xs font-semibold text-on-surface-variant">Mode of Payment <span class="text-error">*</span><select name="payment_mode" required class="{{ $inputClass }}"><option>MDS Check</option><option>Commercial Check</option><option>ADA</option><option>Others</option></select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Check / ADA / OR Reference No.<input name="payment_reference" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Date Paid <span class="text-error">*</span><input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="{{ $inputClass }}"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4 md:col-span-2"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white">Confirm Payment</button></div></form></div></div>

<div id="dv-modal" class="fixed inset-0 z-[100] hidden items-center justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true"><div class="my-auto w-full max-w-3xl rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 class="text-lg font-semibold">Create Disbursement Voucher</h2><p id="dv-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="dv-form" method="POST" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf
        <label class="text-xs font-semibold text-on-surface-variant">DV Number <span class="text-error">*</span><input name="dv_number" required value="{{ old('dv_number', $nextDv ?? '') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">DV Date <span class="text-error">*</span><input type="date" name="dv_date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Payee <span class="text-error">*</span><input name="payee" id="dv-payee" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Particulars <span class="text-error">*</span><input name="dv_particulars" id="dv-particulars" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Mode of Payment <span class="text-error">*</span><select name="payment_mode" required class="{{ $inputClass }}"><option>MDS Check</option><option>Commercial Check</option><option>ADA</option><option>Others</option></select></label>
        <div id="journal-box" class="rounded-xl border border-outline-variant/50 bg-surface-low/50 p-3 md:col-span-2">
            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                <div><p class="text-sm font-bold text-on-surface">Journal entry <span class="font-normal text-on-surface-variant">(double entry)</span></p><p class="text-[11px] font-normal text-on-surface-variant">Total debit must equal total credit and the DV amount. Up to {{ \App\Http\Controllers\FinanceController::JOURNAL_MAX_LINES }} lines fit the printed DV.</p></div>
                <span id="journal-badge" class="rounded-full bg-surface-high px-3 py-1 text-[11px] font-bold text-on-surface-variant">Add the entry</span>
            </div>
            <div class="mb-1 hidden grid-cols-[1fr_8rem_8rem_2rem] gap-2 text-[10px] font-bold uppercase tracking-wide text-on-surface-variant sm:grid"><span>Account (UACS code · title)</span><span class="text-right">Debit</span><span class="text-right">Credit</span><span></span></div>
            <div id="journal-rows" class="space-y-2"></div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <button type="button" id="journal-add" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Add line</button>
                <p class="text-xs text-on-surface-variant">Total debit <strong id="journal-debit" class="text-on-surface">0.00</strong> · Total credit <strong id="journal-credit" class="text-on-surface">0.00</strong></p>
            </div>
            <select id="journal-options" hidden aria-hidden="true"><option value="">Choose an account…</option>@foreach($journalAccounts as $category => $accounts)<optgroup label="{{ $category }}">@foreach($accounts as $account)<option value="{{ $account['code'] }}">{{ $account['code'] }} · {{ $account['title'] }}</option>@endforeach</optgroup>@endforeach</select>
        </div>
        <label class="flex items-start gap-2 text-xs font-semibold text-on-surface-variant md:col-span-2"><input type="checkbox" name="dv_include_appropriation" value="1" @checked(old('dv_include_appropriation')) class="mt-0.5 h-4 w-4 rounded border-outline-variant"><span>Add the APPROPRIATION table to the printed DV<span class="block font-normal">Columns: Appropriation, P/A/P, OR No., Amount, Expense Code. Left unchecked, the DV prints without it.</span></span></label>
        <div class="flex items-end justify-end gap-2 md:col-span-2"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button id="dv-submit" class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Create DV</button></div></form></div></div>

<div id="review-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 id="review-heading" class="text-lg font-semibold"></h2><p id="review-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="review-form" method="POST" class="grid gap-4 p-6">@csrf @method('PATCH')<input type="hidden" name="status" id="review-status">
        <label class="text-xs font-semibold text-on-surface-variant">Remarks <span class="text-error">*</span><textarea name="accounting_remarks" required maxlength="255" rows="3" placeholder="What is missing or wrong?" class="{{ $inputClass }}"></textarea></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white">Submit</button></div></form></div></div>

@include('partials.profile-menu')
<script>
    const show = (m) => { m.classList.remove('hidden'); m.classList.add('flex'); };
    const hide = (m) => { m.classList.add('hidden'); m.classList.remove('flex'); };
    const payModal = document.getElementById('pay-modal'), reviewModal = document.getElementById('review-modal'), dvModal = document.getElementById('dv-modal');
    document.querySelectorAll('[data-dv]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('dv-form').action = b.dataset.dv;
        document.getElementById('dv-title').textContent = b.dataset.label + ' · ' + b.dataset.amount;
        document.getElementById('dv-payee').value = b.dataset.payee || '';
        document.getElementById('dv-particulars').value = b.dataset.particulars || '';
        if (window.journalOpen) window.journalOpen(b);
        show(dvModal);
    }));
    document.querySelectorAll('[data-pay]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('pay-form').action = b.dataset.pay;
        document.getElementById('pay-title').textContent = b.dataset.label + ' · ' + b.dataset.amount;
        if (b.dataset.mode) document.querySelector('#pay-form [name=payment_mode]').value = b.dataset.mode;
        show(payModal);
    }));
    document.querySelectorAll('[data-review]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('review-form').action = b.dataset.review;
        document.getElementById('review-status').value = b.dataset.status;
        document.getElementById('review-heading').textContent = b.dataset.status === 'returned' ? 'Return ORS' : 'Request Documents';
        document.getElementById('review-title').textContent = b.dataset.label;
        show(reviewModal);
    }));
    [payModal, reviewModal, dvModal].forEach((m) => { m.addEventListener('click', (e) => { if (e.target === m) hide(m); }); m.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => hide(m))); });
</script>
<script>
    // Double-entry journal of the DV: lines of account + debit or credit that must balance and equal the DV amount.
    (() => {
        const MAX = {{ \App\Http\Controllers\FinanceController::JOURNAL_MAX_LINES }};
        const MDS = '1010404000';
        const form = document.getElementById('dv-form');
        const rows = document.getElementById('journal-rows');
        const options = document.getElementById('journal-options');
        const badge = document.getElementById('journal-badge');
        const submit = document.getElementById('dv-submit');
        const field = 'w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm font-normal outline-none focus:border-primary';
        let amount = 0;
        const cents = (value) => Math.round((parseFloat(value) || 0) * 100);
        const peso = (value) => (value / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const hasOption = (code) => code && [...options.options].some((option) => option.value === code);

        const renumber = () => rows.querySelectorAll('.journal-row').forEach((row, index) => {
            row.querySelector('select').name = `journal[${index}][account_code]`;
            row.querySelector('[data-side=debit]').name = `journal[${index}][debit]`;
            row.querySelector('[data-side=credit]').name = `journal[${index}][credit]`;
        });

        const refresh = () => {
            const lines = [...rows.querySelectorAll('.journal-row')];
            let debit = 0; let credit = 0; let complete = lines.length >= 2;
            lines.forEach((row) => {
                const d = cents(row.querySelector('[data-side=debit]').value);
                const c = cents(row.querySelector('[data-side=credit]').value);
                debit += d; credit += c;
                if (! row.querySelector('select').value || (d > 0) === (c > 0)) complete = false;
            });
            document.getElementById('journal-debit').textContent = peso(debit);
            document.getElementById('journal-credit').textContent = peso(credit);
            const balanced = debit === credit && debit === amount;
            const ok = complete && balanced;
            let text = 'Balanced'; let tone = 'bg-secondary/15 text-secondary';
            if (debit !== credit) { text = 'Out of balance by ₱' + peso(Math.abs(debit - credit)); tone = 'bg-error/10 text-error'; }
            else if (debit !== amount) { text = 'Entry must total ₱' + peso(amount); tone = 'bg-error/10 text-error'; }
            else if (! complete) { text = 'Complete every line'; tone = 'bg-amber-100 text-amber-800'; }
            badge.textContent = text;
            badge.className = 'rounded-full px-3 py-1 text-[11px] font-bold ' + tone;
            submit.disabled = ! ok;
            document.getElementById('journal-add').disabled = lines.length >= MAX;
            document.getElementById('journal-add').classList.toggle('opacity-40', lines.length >= MAX);
        };

        const addRow = (code = '', debit = '', credit = '', auto = false) => {
            if (rows.children.length >= MAX) return null;
            const row = document.createElement('div');
            row.className = 'journal-row grid grid-cols-1 gap-2 sm:grid-cols-[1fr_8rem_8rem_2rem]';
            if (auto) row.dataset.auto = '1';
            const select = options.cloneNode(true);
            select.removeAttribute('id'); select.removeAttribute('aria-hidden'); select.hidden = false; select.className = field; select.required = true;
            const amountInput = (side, value) => {
                const input = document.createElement('input');
                input.type = 'number'; input.step = '0.01'; input.min = '0'; input.placeholder = side === 'debit' ? 'Debit' : 'Credit';
                input.className = field + ' text-right tabular-nums'; input.dataset.side = side; input.value = value;
                return input;
            };
            const debitInput = amountInput('debit', debit); const creditInput = amountInput('credit', credit);
            const remove = document.createElement('button');
            remove.type = 'button'; remove.title = 'Remove line'; remove.setAttribute('aria-label', 'Remove line');
            remove.className = 'grid h-9 w-8 place-items-center rounded text-on-surface-variant hover:bg-error/10 hover:text-error';
            remove.innerHTML = '<span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>';
            row.append(select, debitInput, creditInput, remove);
            rows.append(row);
            select.value = hasOption(code) ? code : '';
            select.addEventListener('change', () => { delete row.dataset.auto; refresh(); });
            debitInput.addEventListener('input', () => { if (debitInput.value) creditInput.value = ''; refresh(); });
            creditInput.addEventListener('input', () => { if (creditInput.value) debitInput.value = ''; refresh(); });
            remove.addEventListener('click', () => { row.remove(); renumber(); refresh(); });
            renumber(); refresh();
            return row;
        };

        const creditAccountFor = (mode) => (mode === 'MDS Check' || mode === 'ADA') ? MDS : '';

        window.journalOpen = (button) => {
            rows.innerHTML = '';
            amount = cents(button.dataset.amountRaw);
            const total = (amount / 100).toFixed(2);
            addRow(button.dataset.expenseCode || '', total, '');
            addRow(creditAccountFor(form.querySelector('[name=payment_mode]').value), '', total, true);
        };

        form.querySelector('[name=payment_mode]').addEventListener('change', (event) => {
            const auto = rows.querySelector('.journal-row[data-auto]');
            if (! auto) return;
            const code = creditAccountFor(event.target.value);
            auto.querySelector('select').value = hasOption(code) ? code : '';
            refresh();
        });
        document.getElementById('journal-add').addEventListener('click', () => addRow());
        form.addEventListener('submit', (event) => { refresh(); if (submit.disabled) event.preventDefault(); });
    })();
</script>
</body>
</html>
