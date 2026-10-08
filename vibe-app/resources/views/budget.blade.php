<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4','surface-high':'#e9e7ef',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.input-fixes')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isMasterUser = auth()->user()?->role === 'master_user';
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']]; if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); }
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col bg-primary px-4 py-6 text-white md:flex"><div class="mb-8 flex items-center gap-3 px-2"><div class="flex h-8 w-8 items-center justify-center rounded bg-secondary"><span class="material-symbols-outlined text-[20px]">school</span></div><span class="text-xl font-semibold">ProcureMS</span></div><nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)<a href="{{ route($item['route']) }}" class="flex items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if($item['label']==='Dashboard')<details class="group" open><summary class="flex cursor-pointer list-none items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded bg-primary-container px-3 py-2 text-sm font-semibold text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
</nav><div class="border-t border-white/15 pt-4 text-xs text-white/60"><p>Multi-School Procurement System</p></div></aside>

<div class="md:pl-72"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-72 md:px-6"><span class="text-sm font-semibold text-on-surface-variant">Finance · Budget</span><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Budget Operations</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Track obligations (procurement requests, ORS) and liquidations against each allocation. <a href="{{ route('budget.allocation', ['year' => $year, 'school_id' => $selectedSchoolId]) }}" class="font-semibold text-primary hover:underline">Open Budget Allocation →</a></p></div>
        <form method="GET" class="flex gap-2"><select name="school_id" onchange="this.form.submit()" class="rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int)$selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select><input type="number" name="year" value="{{ $year }}" min="2000" max="2100" onchange="this.form.submit()" class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none" aria-label="Fiscal year"></form></div>

    @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

    @php $cards=[['Total Allocation',$totals['allocated'],'account_balance','primary'],['Obligated',$totals['obligated'],'assignment','primary'],['Liquidated',$totals['liquidated'],'receipt_long','secondary'],['Remaining Balance',$totals['balance'],'savings',$totals['balance']<0?'error':'secondary']]; @endphp
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($cards as [$label,$value,$icon,$tone])<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tabular-nums {{ $tone==='error'?'text-error':'' }}">{{ $peso($value) }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $tone }}/10 text-{{ $tone }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></article>@endforeach</div>
    @if($unbudgeted > 0)<div class="mb-6 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $peso($unbudgeted) }} in procurement requests for FY {{ $year }} has no matching budget allocation (school + source of fund).</div>@endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section class="overflow-hidden rounded border border-outline-variant/30 bg-white xl:col-span-3"><div class="border-b border-outline-variant/30 p-5"><h2 class="text-lg font-semibold">Fund Utilization · FY {{ $year }}</h2><p class="mt-1 text-xs text-on-surface-variant">Obligated = procurement requests under the same school and source of fund. Liquidated = approved liquidation reports.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-3">School</th><th class="px-4 py-3">Fund</th><th class="px-4 py-3 text-right">Allocated</th><th class="px-4 py-3 text-right">Obligated</th><th class="px-4 py-3 text-right">Liquidated</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Utilization</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
            @forelse($lines as $line)<tr><td class="px-4 py-3">{{ $line['school'] }}</td><td class="px-4 py-3 font-semibold">{{ $line['fund'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($line['allocated']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($line['obligated']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($line['liquidated']) }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums {{ $line['balance']<0?'text-error':'' }}">{{ $peso($line['balance']) }}</td><td class="px-4 py-3"><div class="flex items-center gap-2"><div class="h-2 w-24 rounded-full bg-surface-container"><div class="h-full rounded-full {{ $line['rate']>100?'bg-error':($line['rate']>=80?'bg-amber-500':'bg-secondary') }}" style="width:{{ min($line['rate'],100) }}%"></div></div><span class="text-xs tabular-nums">{{ $line['rate'] }}%</span></div></td></tr>
            @empty<tr><td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">No budget allocations for FY {{ $year }} yet. Add one to start tracking.</td></tr>@endforelse</tbody></table></div></section>
    </div>

    <section class="mt-4 overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="flex flex-col justify-between gap-3 border-b border-outline-variant/30 p-5 sm:flex-row sm:items-center"><div><h2 class="text-lg font-semibold">Obligation Requests (ORS) · FY {{ $year }}</h2><p class="mt-1 text-xs text-on-surface-variant">Created here → reviewed in Accounting → DV created → paid in Cash.</p></div><button type="button" data-open-ors class="flex items-center justify-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">add</span>Create ORS</button></div><div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-3">ORS No.</th><th class="px-4 py-3">School</th><th class="px-4 py-3">Fund</th><th class="px-4 py-3">Particulars</th><th class="px-4 py-3">PR</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($orsList as $o)@php $stage = $o->paid_at ? ['Paid', 'secondary'] : ($o->dv_number ? ['DV ' . $o->dv_number, 'primary'] : ($o->status === 'approved' ? ['Approved · awaiting DV', 'primary'] : [\Illuminate\Support\Str::headline($o->status), in_array($o->status, ['returned', 'for_review']) ? 'error' : 'primary'])); @endphp<tr><td class="px-4 py-3 font-semibold text-primary">{{ $o->ors_number }}</td><td class="px-4 py-3">{{ $o->school?->name }}</td><td class="px-4 py-3">{{ $o->source_of_fund ?: '—' }}@if($line = $o->chargedLine())<span class="block text-[11px] text-on-surface-variant">{{ $line->uacs_code }} · {{ $line->particulars }}</span>@endif</td><td class="px-4 py-3">{{ $o->purpose }}</td><td class="px-4 py-3">{{ $o->procurementRequest?->request_number ?: 'No PR' }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($o->amount) }}</td><td class="px-4 py-3"><span class="inline-flex rounded-full bg-{{ $stage[1] }}/10 px-2 py-1 text-xs font-semibold text-{{ $stage[1] }}">{{ $stage[0] }}</span></td><td class="px-4 py-3 text-right"><a href="{{ route('liquidation.print', $o) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-primary hover:underline">Print ORS</a></td></tr>@empty<tr><td colspan="8" class="px-4 py-10 text-center text-on-surface-variant">No ORS created for FY {{ $year }}. Click Create ORS to obligate funds.</td></tr>@endforelse</tbody></table></div></section>
    <section class="mt-4 overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="border-b border-outline-variant/30 p-5"><h2 class="text-lg font-semibold">Obligations &amp; Transactions · FY {{ $year }}</h2><p class="mt-1 text-xs text-on-surface-variant">Every procurement request and direct (no-PR) ORS charged against your funds. Click a reference to open it.</p></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">School</th><th class="px-4 py-3">Fund</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($transactions as $t)<tr><td class="px-4 py-3">{{ $t['date']->format('M d, Y') }}</td><td class="px-4 py-3"><span class="rounded bg-surface-container px-1.5 py-0.5 text-[10px] font-semibold uppercase">{{ $t['type'] }}</span></td><td class="px-4 py-3"><a href="{{ $t['url'] }}" class="font-semibold text-primary hover:underline">{{ $t['ref'] }}</a></td><td class="px-4 py-3">{{ $t['description'] }}</td><td class="px-4 py-3">{{ $schoolNames[$t['school_id']] ?? '' }}</td><td class="px-4 py-3">{{ $t['fund'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($t['amount']) }}</td><td class="px-4 py-3">{{ \Illuminate\Support\Str::headline($t['status']) }}</td></tr>@empty<tr><td colspan="8" class="px-4 py-8 text-center text-on-surface-variant">No obligations for FY {{ $year }}.</td></tr>@endforelse</tbody></table></div></section>
</div></main></div>
<div id="ors-modal" class="fixed inset-0 z-[100] hidden items-center justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-2xl rounded bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-outline-variant/30 px-6 py-4"><div><h2 class="text-lg font-semibold">Create Obligation Request and Status (ORS)</h2><p class="mt-1 text-xs text-on-surface-variant">Obligating funds here sends the ORS to Accounting for review and DV creation.</p></div><button type="button" data-close-ors class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
    <form method="POST" action="{{ route('liquidation.store') }}" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf<input type="hidden" name="redirect_to" value="budget">
        <div>
            <label class="text-xs font-semibold text-on-surface-variant">ORS Serial No.<input id="ors-serial" readonly value="{{ old('manually_encode_ors_number') ? old('ors_number') : $nextOrsNumber }}" data-auto="{{ $nextOrsNumber }}" name="ors_number" class="{{ $inputClass }} bg-surface-low"></label>
            <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs font-semibold text-primary"><input id="ors-manual" type="checkbox" name="manually_encode_ors_number" value="1" @checked(old('manually_encode_ors_number')) class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">Manually encode ORS Serial No.</label>
            <span id="ors-serial-hint" class="mt-1 block text-[11px] font-normal text-on-surface-variant">{{ old('manually_encode_ors_number') ? 'Type the serial number from your ORS book. It must be unique.' : 'Auto-generated by the system when saved.' }}</span>
            @error('ors_number')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
        </div>
        @if($isMasterUser)<label class="text-xs font-semibold text-on-surface-variant">School <span class="text-error">*</span><select name="school_id" required class="{{ $inputClass }}"><option value="">Select school</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id', $selectedSchoolId) == $school->id)>{{ $school->name }}</option>@endforeach</select></label>@else<input type="hidden" name="school_id" value="{{ $schools->first()?->id }}">@endif
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Linked Procurement Request (optional)<select name="procurement_request_id" id="ors-pr" class="{{ $inputClass }}"><option value="">No PR — direct expense (utilities, travel, etc.)</option>@foreach($availableProcurements as $pr)<option value="{{ $pr->id }}" data-school="{{ $pr->school_id }}" data-fund="{{ $pr->source_of_fund }}" data-amount="{{ $pr->amount }}" data-title="{{ $pr->title }}" data-line="{{ $pr->budget_allocation_id }}" @php $awarded = $pr->awardedPayee(); @endphp data-payee="{{ $awarded['name'] ?? '' }}" data-payee-address="{{ $awarded['address'] ?? '' }}" data-payee-tin="{{ $awarded['tin'] ?? '' }}" @selected(old('procurement_request_id', request('pr')) == $pr->id)>{{ $pr->request_number }} · {{ $pr->school?->name }} · {{ $pr->title }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Source of Fund <span class="text-error">*</span><input name="source_of_fund" required list="ors-funds" value="{{ old('source_of_fund', 'MOOE') }}" class="{{ $inputClass }}"><datalist id="ors-funds"><option value="MOOE"><option value="Special Education Fund"><option value="General Fund"><option value="Canteen Fund"></datalist></label>
        <label class="text-xs font-semibold text-on-surface-variant">Amount (₱) <span class="text-error">*</span><input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Payee / Supplier<input name="payee" value="{{ old('payee') }}" placeholder="Pulled from the awarded supplier when a PR is selected" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Payee Address<input name="payee_address" value="{{ old('payee_address') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Payee TIN<input name="payee_tin" value="{{ old('payee_tin') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Responsibility Center Code<input name="responsibility_center_code" value="{{ old('responsibility_center_code') }}" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Purpose / Particulars <span class="text-error">*</span><input name="purpose" required value="{{ old('purpose') }}" class="{{ $inputClass }}"></label>
        <div class="md:col-span-2">@include('partials.budget-item-select', ['budgetItems' => $budgetItems, 'selectedItem' => null, 'selectClass' => $inputClass])
            <div id="charge-summary" class="mt-2 hidden rounded border border-primary/20 bg-primary/5 px-3 py-2 text-xs text-on-surface"><span class="font-semibold text-primary">Charging of fund:</span> <span id="charge-text"></span></div></div>
        @include('partials.budget-hint')
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Notes<textarea name="notes" rows="2" class="{{ $inputClass }}">{{ old('notes') }}</textarea></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4 md:col-span-2"><button type="button" data-close-ors class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Create ORS</button></div></form></div></div>
<script>
    const orsSerial = document.getElementById('ors-serial');
    const orsManual = document.getElementById('ors-manual');
    const syncOrsSerial = () => {
        orsSerial.readOnly = !orsManual.checked;
        orsSerial.classList.toggle('bg-surface-low', !orsManual.checked);
        if (!orsManual.checked) orsSerial.value = orsSerial.dataset.auto;
        document.getElementById('ors-serial-hint').textContent = orsManual.checked ? 'Type the serial number from your ORS book. It must be unique.' : 'Auto-generated by the system when saved.';
    };
    orsManual.addEventListener('change', () => { if (orsManual.checked) { orsSerial.value = ''; } syncOrsSerial(); if (orsManual.checked) orsSerial.focus(); });
    syncOrsSerial();
    const orsModal = document.getElementById('ors-modal');
    const lineSelect = orsModal.querySelector('[name=budget_allocation_id]');
    const chargeBox = document.getElementById('charge-summary');
    const showCharge = () => {
        const option = lineSelect.selectedOptions[0];
        if (!option || !option.value) { chargeBox.classList.add('hidden'); return; }
        const peso = (value) => '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('charge-text').textContent = option.dataset.fund + ' · ' + option.dataset.code + ' ' + option.dataset.title + ' · Available ' + peso(option.dataset.balance);
        chargeBox.classList.remove('hidden');
    };
    lineSelect.addEventListener('change', () => {
        const option = lineSelect.selectedOptions[0];
        if (option && option.value) {
            orsModal.querySelector('[name=source_of_fund]').value = option.dataset.fund;
            const rc = orsModal.querySelector('[name=responsibility_center_code]');
            if (option.dataset.rc && !rc.value) rc.value = option.dataset.rc;
        }
        showCharge();
    });
    showCharge();
    const openOrs = () => { orsModal.classList.remove('hidden'); orsModal.classList.add('flex'); };
    document.querySelectorAll('[data-open-ors]').forEach((b) => b.addEventListener('click', openOrs));
    document.querySelectorAll('[data-close-ors]').forEach((b) => b.addEventListener('click', () => { orsModal.classList.add('hidden'); orsModal.classList.remove('flex'); }));
    document.getElementById('ors-pr').addEventListener('change', (e) => {
        const o = e.target.selectedOptions[0];
        if (!o || !o.value) { lineSelect.disabled = false; return; }
        const f = (n) => orsModal.querySelector('[name=' + n + ']');
        if (o.dataset.school && f('school_id')) f('school_id').value = o.dataset.school;
        if (o.dataset.fund) f('source_of_fund').value = o.dataset.fund;
        // The obligation is charged to the budget line already chosen on the PR.
        lineSelect.value = o.dataset.line || '';
        lineSelect.disabled = !!o.dataset.line;
        showCharge();
        if (o.dataset.amount) f('amount').value = o.dataset.amount;
        if (o.dataset.title && !f('purpose').value) f('purpose').value = o.dataset.title;
        f('payee').value = o.dataset.payee || '';
        f('payee_address').value = o.dataset.payeeAddress || '';
        f('payee_tin').value = o.dataset.payeeTin || '';
        if (!o.dataset.payee) f('payee').placeholder = 'No awarded supplier on this PR yet - enter the payee';
    });
    @if($errors->any() || request('pr') || request('new')) openOrs(); document.getElementById('ors-pr').dispatchEvent(new Event('change')); @endif
</script>
@include('partials.profile-menu')
</body>
</html>
