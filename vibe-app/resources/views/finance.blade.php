<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $section === 'cash' ? 'Cash' : 'Accounting' }} · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4','surface-high':'#e9e7ef',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isCash = $section === 'cash';
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']];
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    $sub = [['Budget','account_balance','budget','budget'],['Accounting','request_quote','accounting','accounting'],['Cash','payments','cash','cash']];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tabs = $isCash
        ? ['unpaid' => 'DVs For Payment', 'paid' => 'Paid']
        : ['for_review' => 'For Review', 'pending_documents' => 'Pending Documents', 'returned' => 'Returned', 'for_dv' => 'Ready for DV', 'with_dv' => 'With DV', 'all' => 'All'];
    $tones = ['for_review' => 'error', 'pending_documents' => 'primary', 'approved' => 'secondary', 'returned' => 'error', 'draft' => 'on-surface-variant'];
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col bg-primary px-4 py-6 text-white md:flex"><div class="mb-8 flex items-center gap-3 px-2"><div class="flex h-8 w-8 items-center justify-center rounded bg-secondary"><span class="material-symbols-outlined text-[20px]">school</span></div><span class="text-xl font-semibold">ProcureMS</span></div><nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)<a href="{{ route($item['route']) }}" class="flex items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>@if($item['label']==='Dashboard')<details class="group" open><summary class="flex cursor-pointer list-none items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1">@foreach($sub as [$label,$icon,$r,$key])<a href="{{ route($r) }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm {{ $key===$section ? 'bg-primary-container font-semibold text-white' : 'text-white/75 hover:bg-primary-container hover:text-white' }}"><span class="material-symbols-outlined mr-2 text-[17px]">{{ $icon }}</span>{{ $label }}</a>@endforeach</div></details>@endif @endforeach
</nav><div class="border-t border-white/15 pt-4 text-xs text-white/60"><p>Multi-School Procurement System</p></div></aside>

<div class="md:pl-72"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-72 md:px-6"><span class="text-sm font-semibold text-on-surface-variant">Finance · {{ $isCash ? 'Cash' : 'Accounting' }}</span><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
    <div class="mb-8"><h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $isCash ? 'Cash Operations' : 'Accounting Review' }}</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $isCash ? 'Disbursement vouchers created in Accounting, ready for payment. Record the check / ADA reference and release date once paid.' : 'Review ORS entries created in Budget, then approve them and create the Disbursement Voucher (DV) that goes to Cash for payment.' }}</p></div>

    @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metrics as [$label,$value,$icon,$tone,$money])<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ $money ? $peso($value) : $value }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $tone }}/10 text-{{ $tone }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></article>@endforeach</div>

    <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
        <div class="flex flex-wrap gap-1 border-b border-outline-variant/30 p-3">@foreach($tabs as $key => $label)<a href="{{ route($section, ['tab' => $key]) }}" class="rounded px-3 py-2 text-xs font-semibold {{ $tab===$key ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-high' }}">{{ $label }}@if($key!=='all')<span class="ml-1 opacity-70">{{ $counts->get($key, 0) }}</span>@endif</a>@endforeach</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">ORS / Particulars</th><th class="px-5 py-3">School</th><th class="px-5 py-3">Fund</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">{{ $isCash ? 'Voucher / Payment' : 'Status / DV' }}</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($rows as $r)
            @php $tone = $tones[$r->status] ?? 'primary'; $label = $r->ors_number ?: $r->report_number; @endphp
            <tr class="align-top hover:bg-surface-low/60"><td class="px-5 py-3.5"><div class="font-semibold text-primary">{{ $label }}</div><div class="mt-1 text-xs text-on-surface-variant">{{ $r->purpose ?: ($r->procurementRequest?->title ?? 'General') }}</div><div class="mt-1 text-[11px] text-on-surface-variant">@if($r->procurementRequest)PR {{ $r->procurementRequest->request_number }}@else<span class="rounded bg-surface-container px-1.5 py-0.5 text-[10px] font-semibold uppercase">No PR · Direct</span>@endif</div></td>
            <td class="px-5 py-3.5">{{ $r->school?->name }}</td><td class="px-5 py-3.5">{{ $r->source_of_fund ?: '—' }}</td><td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ $peso($r->amount) }}</td>
            <td class="px-5 py-3.5">@if($isCash)<div class="font-semibold">DV {{ $r->dv_number }}</div><div class="text-xs text-on-surface-variant">Payee: {{ $r->payee ?: $r->school?->name }}</div>@if($r->paid_at)<div class="text-xs text-on-surface-variant">{{ $r->payment_mode }}@if($r->payment_reference) · {{ $r->payment_reference }}@endif · {{ $r->paid_at->format('M d, Y') }}</div>@else<span class="mt-1 inline-flex rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">Awaiting payment · {{ $r->payment_mode }}</span>@endif @else<span class="inline-flex rounded-full bg-{{ $tone }}/10 px-2 py-1 text-xs font-semibold text-{{ $tone }}">{{ \Illuminate\Support\Str::headline($r->status) }}</span>@if($r->dv_number)<div class="mt-1 text-xs font-semibold text-secondary">DV {{ $r->dv_number }}</div>@endif @if($r->accounting_remarks)<div class="mt-1 max-w-[220px] text-[11px] text-on-surface-variant">{{ $r->accounting_remarks }}</div>@endif @endif</td>
            <td class="px-5 py-3.5 text-right"><div class="flex flex-wrap justify-end gap-1"><a href="{{ route('liquidation.print', $r) }}" target="_blank" class="rounded px-2 py-1 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Print ORS</a>@if($r->dv_number)<a href="{{ route('accounting.dv.print', $r) }}" target="_blank" class="rounded px-2 py-1 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Print DV</a>@endif
                @if($isMasterUser && $isCash && !$r->paid_at)<button type="button" data-pay="{{ route('cash.pay', $r) }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-mode="{{ $r->payment_mode }}" class="rounded bg-secondary px-2.5 py-1 text-xs font-semibold text-white hover:opacity-90">Record Payment</button>@endif
                @if($isMasterUser && !$isCash && $r->status === 'approved' && !$r->dv_number)<button type="button" data-dv="{{ route('accounting.dv.store', $r) }}" data-label="{{ $label }}" data-amount="{{ $peso($r->amount) }}" data-payee="{{ $r->payee ?: ($r->procurementRequest?->supplier_name ?? $r->school?->name) }}" data-particulars="{{ $r->purpose ?: $r->procurementRequest?->title }}" class="rounded bg-secondary px-2.5 py-1 text-xs font-semibold text-white hover:opacity-90">Create DV</button>@endif
                @if($isMasterUser && !$isCash && !$r->dv_number)
                    @if($r->status !== 'approved')<form method="POST" action="{{ route('accounting.review', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="rounded px-2 py-1 text-xs font-semibold text-secondary hover:bg-secondary hover:text-white">Approve</button></form>@endif
                    <button type="button" data-review="{{ route('accounting.review', $r) }}" data-status="pending_documents" data-label="{{ $label }}" class="rounded px-2 py-1 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Request Docs</button>
                    <button type="button" data-review="{{ route('accounting.review', $r) }}" data-status="returned" data-label="{{ $label }}" class="rounded px-2 py-1 text-xs font-semibold text-error hover:bg-error hover:text-white">Return</button>
                @endif</div></td></tr>
        @empty<tr><td colspan="6" class="px-5 py-12 text-center text-on-surface-variant">{{ $isCash ? 'Nothing here. Approve ORS entries in Accounting to queue them for payment.' : 'No ORS entries in this view.' }}</td></tr>@endforelse</tbody></table></div>
    </section>
</div></main></div>

<div id="pay-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 class="text-lg font-semibold">Record Payment</h2><p id="pay-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="pay-form" method="POST" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf
        <label class="text-xs font-semibold text-on-surface-variant">Mode of Payment <span class="text-error">*</span><select name="payment_mode" required class="{{ $inputClass }}"><option>MDS Check</option><option>Commercial Check</option><option>ADA</option><option>Others</option></select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Check / ADA / OR Reference No.<input name="payment_reference" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Date Paid <span class="text-error">*</span><input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="{{ $inputClass }}"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4 md:col-span-2"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white">Confirm Payment</button></div></form></div></div>

<div id="dv-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-xl rounded bg-white shadow-2xl"><div class="border-b border-outline-variant/30 px-6 py-4"><h2 class="text-lg font-semibold">Create Disbursement Voucher</h2><p id="dv-title" class="mt-1 text-xs text-on-surface-variant"></p></div>
    <form id="dv-form" method="POST" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf
        <label class="text-xs font-semibold text-on-surface-variant">DV Number <span class="text-error">*</span><input name="dv_number" required value="{{ old('dv_number', $nextDv ?? '') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">DV Date <span class="text-error">*</span><input type="date" name="dv_date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Payee <span class="text-error">*</span><input name="payee" id="dv-payee" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Particulars <span class="text-error">*</span><input name="dv_particulars" id="dv-particulars" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Mode of Payment <span class="text-error">*</span><select name="payment_mode" required class="{{ $inputClass }}"><option>MDS Check</option><option>Commercial Check</option><option>ADA</option><option>Others</option></select></label>
        <div class="flex items-end justify-end gap-2 md:col-span-2"><button type="button" data-close class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white">Create DV</button></div></form></div></div>

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
</body>
</html>
