<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $section === 'cash' ? 'Cash' : 'Accounting' }} · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#e6edf4','surface-low':'#dbe5ee','surface-container':'#cfdbe6','surface-high':'#c0cfdd',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#374a5d',secondary:'#369878','outline-variant':'#7f95a9',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>html{font-size:14px}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isCash = $section === 'cash';
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']]; if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); }
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; if (auth()->user()->hasAccess('subscriptions')) $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; if (auth()->user()?->hasAccess('schools')) $navigation[]=['icon'=>'domain','label'=>'School Management','route'=>'school-management']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    $sub = [['Budget','account_balance','budget','budget'],['Accounting','request_quote','accounting','accounting'],['Chart of Accounts','list_alt','chart-of-accounts','chart-of-accounts'],['Allotment Registry','menu_book','allotment-registry','allotment-registry'],['Cash','payments','cash','cash']];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tabs = $isCash
        ? ['unpaid' => 'DVs For Payment', 'paid' => 'Paid', 'cancelled' => 'Cancelled Checks']
        : ['for_review' => 'For Review', 'pending_documents' => 'Pending Documents', 'returned' => 'Returned', 'for_dv' => 'Ready for DV', 'with_dv' => 'With DV', 'all' => 'All'];
    $tones = ['for_review' => 'error', 'pending_documents' => 'primary', 'approved' => 'secondary', 'returned' => 'error', 'draft' => 'on-surface-variant'];
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#103967] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)<a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if($item['label']==='Dashboard')<details class="group" open><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1">@foreach($sub as [$label,$icon,$r,$key])<a href="{{ route($r) }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium {{ $key===$section ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-2 text-[17px]">{{ $icon }}</span>{{ $label }}</a>@endforeach</div></details>@endif @endforeach
</nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

<div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex min-w-0 items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Finance</p><p class="truncate text-sm font-semibold text-on-surface">{{ $isCash ? 'Cash' : 'Accounting' }}</p></div></div><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
    <div class="mb-8"><h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $isCash ? 'Cash Operations' : 'Accounting Review' }}</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $isCash ? 'Disbursement vouchers created in Accounting, ready for payment. Record the check / ADA reference and release date once paid.' : 'Review ORS entries created in Budget, then approve them and create the Disbursement Voucher (DV) that goes to Cash for payment.' }}</p></div>

    @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metrics as [$label,$value,$icon,$tone,$money])<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ $money ? $peso($value) : $value }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $tone }}/10 text-{{ $tone }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></article>@endforeach</div>

    @php
        $tabMeta = [
            'for_review' => ['pending_actions', 'bg-error text-white'], 'pending_documents' => ['folder_open', 'bg-amber-500 text-white'], 'returned' => ['undo', 'bg-error/80 text-white'],
            'for_dv' => ['description', 'bg-secondary text-white'], 'with_dv' => ['task_alt', 'bg-primary text-white'], 'all' => ['list', 'bg-on-surface-variant text-white'],
            'unpaid' => ['hourglass_top', 'bg-error text-white'], 'paid' => ['check_circle', 'bg-secondary text-white'], 'cancelled' => ['cancel', 'bg-on-surface-variant text-white'],
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
        @if($isCash && $tab === 'cancelled')
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">Check No.</th><th class="px-5 py-3">DV / ORS</th><th class="px-5 py-3">Mode</th><th class="px-5 py-3">Date Paid</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">Reason</th><th class="px-5 py-3">Cancelled By</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($cancelledChecks as $c)
            <tr data-row class="align-top hover:bg-surface-low/60">
                <td class="px-5 py-4 font-bold text-primary">{{ $c->check_number ?: '—' }}</td>
                <td class="px-5 py-4 text-[13px]"><div class="font-semibold">DV {{ $c->dv_number }}</div><div class="text-xs text-on-surface-variant">{{ $c->ors_number }}</div></td>
                <td class="px-5 py-4 text-[13px]">{{ $c->payment_mode }}</td>
                <td class="px-5 py-4 text-[13px]">{{ $c->paid_at?->format('M d, Y') }}</td>
                <td class="px-5 py-4 text-right text-[15px] font-bold tabular-nums">{{ $peso($c->amount) }}</td>
                <td class="max-w-[320px] px-5 py-4 text-[13px]">{{ $c->reason }}</td>
                <td class="px-5 py-4 text-[13px]">{{ $c->canceller?->name }}<div class="text-xs text-on-surface-variant">{{ $c->cancelled_at?->format('M d, Y h:i A') }}</div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-14 text-center text-on-surface-variant"><span class="material-symbols-outlined mb-1 block text-[34px] text-outline-variant" aria-hidden="true">inbox</span>No cancelled checks.</td></tr>
        @endforelse
        <tr id="row-empty" hidden><td colspan="7" class="px-5 py-10 text-center text-sm text-on-surface-variant">No entry matches your search.</td></tr>
        </tbody></table></div>
        @else
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">ORS / Particulars</th><th class="px-5 py-3">School</th><th class="px-5 py-3">Fund</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">{{ $isCash ? 'Voucher / Payment' : 'Status / DV' }}</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
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
                            <span class="mt-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-bold text-secondary"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>Paid {{ $r->paid_at->format('M d, Y') }}</span>
                            <div class="mt-1 text-xs text-on-surface-variant">{{ $r->payment_mode }}@if($r->payment_reference) · {{ $r->payment_reference }}@endif</div>
                        @else
                            <span class="mt-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-error/10 px-2.5 py-1 text-xs font-bold text-error"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>Awaiting payment · {{ $r->payment_mode }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-{{ $tone }}/10 px-2.5 py-1 text-xs font-bold text-{{ $tone }}"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $statusIcons[$r->status] ?? 'info' }}</span>{{ \Illuminate\Support\Str::headline($r->status) }}</span>
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
                    @if(auth()->user()->hasPermission('cash.pay') && $isCash && $r->paid_at)
                        <button type="button" onclick="document.getElementById('cancel-{{ $r->id }}').showModal()" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-error ring-1 ring-error/30 hover:bg-error hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">cancel</span>Cancel Check</button>
                        <dialog id="cancel-{{ $r->id }}" class="w-full max-w-md rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="{{ route('cash.cancel', $r) }}" class="space-y-3 p-6">@csrf
                            <h2 class="text-lg font-semibold">Cancel check {{ $r->payment_reference }}</h2>
                            <p class="text-xs text-on-surface-variant">{{ $label }} · DV {{ $r->dv_number }} · {{ $peso($r->amount) }}. The check goes to the Cancelled Checks register and the ORS, DV and journal entry open for editing.</p>
                            <label class="block text-xs font-semibold text-on-surface-variant">Reason <span class="text-error">*</span><input name="reason" required maxlength="255" class="{{ $inputClass }}"></label>
                            <label class="block text-xs font-semibold text-on-surface-variant">Your password <span class="text-error">*</span><input type="password" name="password" required autocomplete="current-password" class="{{ $inputClass }}"></label>
                            <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" onclick="this.closest('dialog').close()" class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-error px-4 py-2.5 text-xs font-bold text-white hover:opacity-90">Cancel Check</button></div>
                        </form></dialog>
                    @endif
                    @if(auth()->user()->hasPermission('cash.pay') && $isCash && ! $r->paid_at)<button type="button" data-pay="{{ route('cash.pay', $r) }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-mode="{{ $r->payment_mode }}" class="inline-flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">payments</span>Record Payment</button>@endif
                    @if(auth()->user()->hasPermission('accounting.approve') && ! $isCash && $r->reissue_pending)
                        <button type="button" onclick="document.getElementById('edit-{{ $r->id }}').showModal()" class="inline-flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">edit</span>Edit ORS / DV</button>
                        <dialog id="edit-{{ $r->id }}" class="max-h-[92vh] w-full max-w-3xl rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50"><form method="POST" action="{{ route('accounting.dv.store', $r) }}" class="grid grid-cols-1 gap-3 p-6 md:grid-cols-2">@csrf
                            <h2 class="text-lg font-semibold md:col-span-2">Edit {{ $label }} · DV {{ $r->dv_number }}</h2>
                            <p class="text-xs text-on-surface-variant md:col-span-2">The check was cancelled. Correct what is needed, then approve the ORS again to send it back to Cash.</p>
                            <label class="text-xs font-semibold text-on-surface-variant">Amount <span class="text-error">*</span><input type="number" step="0.01" min="0.01" name="amount" required value="{{ number_format((float) $r->amount, 2, '.', '') }}" class="{{ $inputClass }} text-right"></label>
                            <label class="text-xs font-semibold text-on-surface-variant">DV Date <span class="text-error">*</span><input type="date" name="dv_date" required max="{{ now()->toDateString() }}" value="{{ ($r->dv_date ?? now())->toDateString() }}" class="{{ $inputClass }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Payee <span class="text-error">*</span><input name="payee" required maxlength="255" value="{{ $payee }}" class="{{ $inputClass }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">ORS Purpose<input name="purpose" maxlength="255" value="{{ $r->purpose }}" class="{{ $inputClass }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">DV Particulars <span class="text-error">*</span><input name="dv_particulars" required maxlength="255" value="{{ $r->dv_particulars }}" class="{{ $inputClass }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Mode of Payment <span class="text-error">*</span><select name="payment_mode" required class="{{ $inputClass }}">@foreach(\App\Http\Controllers\FinanceController::PAYMENT_MODES as $mode)<option @selected($mode === 'MDS Check')>{{ $mode }}</option>@endforeach</select></label>
                            <div class="rounded-xl border border-outline-variant/50 bg-surface-low/50 p-3 md:col-span-2" data-edit-journal>
                                <p class="mb-2 text-sm font-bold">Journal entry <span class="font-normal text-on-surface-variant">(total debit = total credit = amount)</span></p>
                                <div class="space-y-2" data-lines>
                                    @foreach($r->journalLines as $i => $line)
                                        <div class="grid grid-cols-[1fr_7rem_7rem] gap-2"><select name="journal[{{ $i }}][account_code]" required class="{{ $inputClass }} mt-0">@foreach($journalAccounts as $category => $accounts)<optgroup label="{{ $category }}">@foreach($accounts as $account)<option value="{{ $account['code'] }}" @selected($account['code'] === $line->account_code)>{{ $account['code'] }} · {{ $account['title'] }}</option>@endforeach</optgroup>@endforeach</select><input type="number" step="0.01" min="0" name="journal[{{ $i }}][debit]" value="{{ $line->debit > 0 ? number_format((float) $line->debit, 2, '.', '') : '' }}" placeholder="Debit" class="{{ $inputClass }} mt-0 text-right"><input type="number" step="0.01" min="0" name="journal[{{ $i }}][credit]" value="{{ $line->credit > 0 ? number_format((float) $line->credit, 2, '.', '') : '' }}" placeholder="Credit" class="{{ $inputClass }} mt-0 text-right"></div>
                                    @endforeach
                                </div>
                                <button type="button" class="mt-2 rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low" onclick="const box=this.closest('[data-edit-journal]').querySelector('[data-lines]');const row=box.lastElementChild.cloneNode(true);const n=box.children.length;row.querySelectorAll('select,input').forEach((el)=>{el.name=el.name.replace(/journal\[\d+\]/,'journal['+n+']');if(el.tagName==='INPUT')el.value='';});box.appendChild(row);">Add line</button>
                            </div>
                            <div class="rounded-xl border border-outline-variant/50 bg-surface-low/50 p-3 md:col-span-2">
                                <p class="mb-2 text-sm font-bold">Less: Deductions <span class="font-normal text-on-surface-variant">(optional)</span></p>
                                <div class="grid gap-2 sm:grid-cols-2">@foreach(\App\Models\LiquidationReport::DV_DEDUCTIONS as $deductionKey => $deductionLabel)<label class="text-xs font-semibold text-on-surface-variant">{{ $deductionLabel }}<input type="number" step="0.01" min="0" name="deductions[{{ $deductionKey }}]" value="{{ $r->dv_deductions[$deductionKey] ?? '' }}" class="{{ $inputClass }} text-right"></label>@endforeach</div>
                                @foreach(($r->dv_deductions['other'] ?? []) as $i => $other)
                                    <div class="mt-2 grid grid-cols-[1fr_8rem] gap-2"><input name="deductions_other[{{ $i }}][label]" value="{{ $other['label'] }}" maxlength="40" class="{{ $inputClass }} mt-0"><input type="number" step="0.01" min="0" name="deductions_other[{{ $i }}][amount]" value="{{ $other['amount'] }}" class="{{ $inputClass }} mt-0 text-right"></div>
                                @endforeach
                            </div>
                            <label class="text-xs font-semibold text-on-surface-variant">Reason for the change <span class="text-error">*</span><input name="reason" required maxlength="255" class="{{ $inputClass }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant">Your password <span class="text-error">*</span><input type="password" name="password" required autocomplete="current-password" class="{{ $inputClass }}"></label>
                            <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3 md:col-span-2"><button type="button" onclick="this.closest('dialog').close()" class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Save changes</button></div>
                        </form></dialog>
                    @endif
                    @if(auth()->user()->hasPermission('accounting.approve') && ! $isCash && $r->status === 'approved' && ! $r->dv_number)<button type="button" data-dv="{{ route('accounting.dv.store', $r) }}" data-school="{{ $r->school_id }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-amount-raw="{{ number_format((float) $r->amount, 2, '.', '') }}" data-expense-code="{{ $r->chargedLine()?->account?->code ?? $r->chargedLine()?->uacs_code }}" data-payee="{{ $r->payee ?: ($r->procurementRequest?->supplier_name ?? $r->school?->name) }}" data-particulars="{{ $r->purpose ?: $r->procurementRequest?->title }}" class="inline-flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">receipt_long</span>Create DV</button>@endif
                    @if(auth()->user()->hasPermission('accounting.approve') && ! $isCash && (! $r->dv_number || $r->reissue_pending) && $r->status !== 'approved')<form method="POST" action="{{ route('accounting.review', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-primary-container"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">check</span>Approve</button></form>@endif
                    <a href="{{ route('liquidation.print', $r) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-outline-variant/50 hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">print</span>ORS</a>
                    @if($r->dv_number)
                        <a href="{{ route('accounting.dv.print', $r) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-outline-variant/50 hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">print</span>DV</a>
                        @if(! $isCash && auth()->user()->hasPermission('accounting.approve'))<form method="POST" action="{{ route('accounting.dv.options', $r) }}">@csrf @method('PATCH')<input type="hidden" name="dv_include_appropriation" value="{{ $r->dv_include_appropriation ? 0 : 1 }}"><button class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-on-surface-variant ring-1 ring-outline-variant/50 hover:bg-surface-low" title="Show or hide the APPROPRIATION table on the printed DV">Appropriation table: {{ $r->dv_include_appropriation ? 'On' : 'Off' }}</button></form>
                        @if(! empty($dvRowPanels[$r->id]))
                            <details class="relative">
                                <summary class="cursor-pointer list-none rounded-lg px-2.5 py-1.5 text-xs font-semibold text-on-surface-variant ring-1 ring-outline-variant/50 hover:bg-surface-low" title="Change who signs the printed DV">Signatories</summary>
                                <form method="POST" action="{{ route('accounting.dv.options', $r) }}" class="absolute right-0 z-20 mt-2 w-72 space-y-3 rounded-lg border border-outline-variant/50 bg-white p-4 shadow-xl">@csrf @method('PATCH')
                                    @include('partials.signatory-picker', ['rows' => $dvRowPanels[$r->id], 'enabled' => true])
                                    <button class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-container">Save signatories</button>
                                </form>
                            </details>
                        @endif
                    @endif
                    @endif
                    @if(auth()->user()->hasPermission('accounting.approve') && ! $isCash && ! $r->dv_number)
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
        @endif
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

<div id="dv-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true"><div class="flex max-h-[94vh] w-full max-w-[1400px] flex-col overflow-hidden rounded bg-white shadow-2xl"><div class="shrink-0 border-b border-outline-variant/30 px-5 py-3"><h2 class="text-lg font-semibold">Create Disbursement Voucher</h2><p id="dv-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <div id="dv-split"><aside id="dv-preview-pane" aria-label="DV preview"><div id="dv-preview-bar"><span>Official DV (updates as you edit)</span><span class="flex items-center gap-3"><small id="dv-preview-status">Updates as you type</small><select id="dv-preview-zoom" aria-label="Preview zoom" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option></select></span></div><iframe id="dv-preview-frame" title="Official DV preview"></iframe></aside><div id="dv-form-pane">
    <form id="dv-form" method="POST" class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">@csrf
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
            <select id="journal-options" hidden aria-hidden="true"><option value="">Choose an account…</option>@foreach(($journalAccounts ?? []) as $category => $accounts)<optgroup label="{{ $category }}">@foreach($accounts as $account)<option value="{{ $account['code'] }}">{{ $account['code'] }} · {{ $account['title'] }}</option>@endforeach</optgroup>@endforeach</select>
        </div>
        <div class="rounded-xl border border-outline-variant/50 bg-surface-low/50 p-3 md:col-span-2">
            <p class="text-sm font-bold text-on-surface">Less: Deductions <span class="font-normal text-on-surface-variant">(optional)</span></p>
            <p class="mb-2 text-[11px] font-normal text-on-surface-variant">Typed only when they apply. They are printed above Amount Due, and Amount Due becomes the amount less the deductions. Add another one by name if it is not listed (up to {{ \App\Http\Controllers\FinanceController::DEDUCTION_MAX_LINES }} lines in all).</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(\App\Models\LiquidationReport::DV_DEDUCTIONS as $deductionKey => $deductionLabel)
                    <label class="text-xs font-semibold text-on-surface-variant">{{ $deductionLabel }}<input type="number" step="0.01" min="0" name="deductions[{{ $deductionKey }}]" value="{{ old('deductions.'.$deductionKey) }}" class="{{ $inputClass }} text-right"></label>
                @endforeach
            </div>
            <div id="deduction-others" class="mt-2 space-y-2"></div>
            <button type="button" id="deduction-add" class="mt-2 inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Add other deduction</button>
        </div>
        <label class="flex items-start gap-2 text-xs font-semibold text-on-surface-variant md:col-span-2"><input type="checkbox" name="dv_include_appropriation" value="1" @checked(old('dv_include_appropriation')) class="mt-0.5 h-4 w-4 rounded border-outline-variant"><span>Add the APPROPRIATION table to the printed DV<span class="block font-normal">Columns: Appropriation, P/A/P, OR No., Amount, Expense Code. Left unchecked, the DV prints without it.</span></span></label>
        @foreach(($dvDialogPanels ?? []) as $signatorySchoolId => $signatoryRows)
            <section data-signatories-school="{{ $signatorySchoolId }}" class="hidden rounded-xl border border-outline-variant/50 bg-surface-low/50 p-3 md:col-span-2">
                <p class="text-sm font-bold text-on-surface">Signatories</p>
                <p class="mb-3 text-[11px] font-normal text-on-surface-variant">Who signs this DV. Leave "Default" to follow School Settings; a different choice applies to this DV only.</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">@include('partials.signatory-picker', ['rows' => $signatoryRows])</div>
            </section>
        @endforeach
        <div class="flex items-end justify-end gap-2 md:col-span-2"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button id="dv-submit" class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Create DV</button></div></form></div></div></div></div>
<style>
    #dv-split{display:flex;flex-direction:row-reverse;min-height:0;flex:1}
    #dv-preview-pane{display:flex;min-width:0;flex:1 1 50%;flex-direction:column;background:#d9dee5;border-left:1px solid #cbd7e1}
    #dv-preview-bar{display:flex;justify-content:space-between;align-items:center;padding:8px 14px;background:#fff;border-bottom:1px solid #cbd7e1;font-size:12px;font-weight:700;color:#103967}
    #dv-preview-bar small{font-weight:400;color:#536273}
    #dv-preview-frame{width:100%;flex:1;min-height:60vh;border:0;background:#d9dee5}
    #dv-form-pane{flex:1 1 50%;min-width:0;overflow-y:auto;max-height:calc(94vh - 4.5rem)}
    #dv-form input:not([type=checkbox]),#dv-form select:not(.journal-row select),#dv-form textarea{margin-top:.25rem;padding-top:.4rem;padding-bottom:.4rem;font-size:.8125rem}
    @media(max-width:1023px){#dv-modal{align-items:flex-start;overflow-y:auto}#dv-split{flex-direction:column-reverse;overflow-y:auto}#dv-preview-pane{flex:none}#dv-preview-frame{min-height:55vh}#dv-form-pane{flex:none;max-height:none;overflow:visible}}
</style>

<div id="review-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 id="review-heading" class="text-lg font-semibold"></h2><p id="review-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="review-form" method="POST" class="grid gap-4 p-6">@csrf @method('PATCH')<input type="hidden" name="status" id="review-status">
        <label class="text-xs font-semibold text-on-surface-variant">Remarks <span class="text-error">*</span><textarea name="accounting_remarks" required maxlength="255" rows="3" placeholder="What is missing or wrong?" class="{{ $inputClass }}"></textarea></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white">Submit</button></div></form></div></div>

@include('partials.profile-menu')
@include('partials.mobile-nav')
<script>
    const show = (m) => { m.classList.remove('hidden'); m.classList.add('flex'); };
    const hide = (m) => { m.classList.add('hidden'); m.classList.remove('flex'); };
    const payModal = document.getElementById('pay-modal'), reviewModal = document.getElementById('review-modal'), dvModal = document.getElementById('dv-modal');
    document.querySelectorAll('[data-dv]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('dv-form').action = b.dataset.dv;
        // Each school has its own staff, so only the signatory selects of this report's school are shown and posted.
        document.querySelectorAll('#dv-form [data-signatories-school]').forEach((section) => {
            const active = section.dataset.signatoriesSchool === b.dataset.school;
            section.classList.toggle('hidden', !active);
            section.querySelectorAll('select').forEach((control) => { control.disabled = !active; });
        });
        document.getElementById('dv-title').textContent = b.dataset.label + ' · ' + b.dataset.amount;
        document.getElementById('dv-payee').value = b.dataset.payee || '';
        document.getElementById('dv-particulars').value = b.dataset.particulars || '';
        if (window.deductionsReset) window.deductionsReset();
        if (window.journalOpen) window.journalOpen(b);
        show(dvModal);
        if (window.refreshDvPreview) window.refreshDvPreview();
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
    // Live preview: the right pane draws the real official DV from the form as it is now (nothing is saved).
    (() => {
        const form = document.getElementById('dv-form');
        const frame = document.getElementById('dv-preview-frame');
        const status = document.getElementById('dv-preview-status');
        const zoomSelect = document.getElementById('dv-preview-zoom');
        const modal = document.getElementById('dv-modal');
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}.official-sheet{zoom:var(--pz,1)!important;margin-top:12px!important;margin-bottom:12px!important}</style>';
        let timer = null; let controller = null;
        const applyZoom = () => {
            const doc = frame.contentDocument;
            const sheet = doc && doc.querySelector('.official-sheet, [data-official-page]');
            if (!sheet) return;
            let zoom = parseFloat(zoomSelect.value);
            if (zoomSelect.value === 'fit') {
                doc.documentElement.style.setProperty('--pz', 1);
                const box = sheet.getBoundingClientRect();
                zoom = Math.min(0.72, Math.max(0.3, (frame.clientWidth - 48) / (Math.max(sheet.scrollWidth, box.width) || 1)));
            }
            doc.documentElement.style.setProperty('--pz', zoom);
        };
        frame.addEventListener('load', () => setTimeout(applyZoom, 350));
        zoomSelect.addEventListener('change', applyZoom);
        const retryDelays = [1500, 4000];
        const refresh = async (attempt = 0) => {
            if (modal.classList.contains('hidden') || !form.action) return;
            if (controller) controller.abort();
            controller = new AbortController();
            status.onclick = null; status.style.cursor = ''; status.style.textDecoration = '';
            status.textContent = attempt ? 'Updating preview... (trying again)' : 'Updating preview...';
            let reason = 'no connection to the server';
            try {
                const response = await fetch(form.action.replace(/\/dv$/, '/dv/preview'), { method: 'POST', body: new FormData(form), signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                const html = await response.text();
                if (response.ok || (response.status < 500 && response.status !== 419)) {
                    frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html;
                    status.textContent = response.ok ? 'Updates as you type' : 'Preview unavailable (HTTP ' + response.status + ')';
                    return;
                }
                reason = response.status === 419 ? 'your session expired, reload the page' : 'server error ' + response.status;
                if (response.status === 419) attempt = retryDelays.length;
            } catch (error) { if (error.name === 'AbortError') return; }
            if (attempt < retryDelays.length) { timer = setTimeout(() => refresh(attempt + 1), retryDelays[attempt]); return; }
            status.textContent = 'Preview unavailable: ' + reason + '. Click to retry';
            status.style.cursor = 'pointer'; status.style.textDecoration = 'underline';
            status.onclick = () => refresh();
        };
        const schedule = () => { clearTimeout(timer); timer = setTimeout(() => refresh(), 900); };
        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        window.refreshDvPreview = () => { clearTimeout(timer); timer = setTimeout(() => refresh(), 400); };
    })();
</script>
<script>
    // Deductions of the DV: the four usual ones are fields; any other one is added by name and amount.
    (() => {
        const MAX = {{ \App\Http\Controllers\FinanceController::DEDUCTION_MAX_LINES - count(\App\Models\LiquidationReport::DV_DEDUCTIONS) }};
        const form = document.getElementById('dv-form');
        const box = document.getElementById('deduction-others');
        const add = document.getElementById('deduction-add');
        const field = 'w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm font-normal outline-none focus:border-primary';
        const changed = () => form.dispatchEvent(new Event('input', { bubbles: true }));
        const renumber = () => {
            box.querySelectorAll('[data-other]').forEach((row, index) => {
                row.querySelector('[data-label]').name = `deductions_other[${index}][label]`;
                row.querySelector('[data-amount]').name = `deductions_other[${index}][amount]`;
            });
            add.disabled = box.children.length >= MAX;
            add.classList.toggle('opacity-40', box.children.length >= MAX);
        };
        add.addEventListener('click', () => {
            if (box.children.length >= MAX) return;
            const row = document.createElement('div');
            row.dataset.other = '1';
            row.className = 'grid grid-cols-[1fr_9rem_2rem] gap-2';
            row.innerHTML = `<input type="text" data-label maxlength="40" placeholder="Name of the deduction" class="${field}"><input type="number" data-amount step="0.01" min="0" placeholder="Amount" class="${field} text-right tabular-nums"><button type="button" title="Remove" aria-label="Remove deduction" class="grid h-9 w-8 place-items-center rounded text-on-surface-variant hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button>`;
            row.querySelector('button').addEventListener('click', () => { row.remove(); renumber(); changed(); });
            box.append(row);
            renumber();
            row.querySelector('[data-label]').focus();
        });
        window.deductionsReset = () => {
            box.innerHTML = '';
            form.querySelectorAll('input[name^="deductions["]').forEach((input) => { input.value = ''; });
            renumber();
        };
        renumber();
    })();
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
