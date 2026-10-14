@extends('layouts.budget')

@section('title', 'School Operating Budget')
@section('section', 'Planning')
@section('crumb', 'SOB · '.$plan->quarterLabel().' · FY '.$plan->fiscal_year)

@section('content')
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    // The choice is the Specific Program / Project of the AIP, nothing added.
    $programLabel = fn ($kra, $activity) => $kra->program ?: $kra->kra;
    $box = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
    $draft = $plan->status === 'draft';
    $editable = $canManage;
    $grandTotal = collect($grouped)->sum('total');
@endphp
<div class="mb-5">
    <a href="{{ route('planning', ['school_id' => $plan->school_id, 'year' => $plan->fiscal_year]) }}#sob" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold leading-9 tracking-tight">School Operating Budget · {{ $plan->quarterLabel() }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $plan->school?->name }} · FY {{ $plan->fiscal_year }} · {{ $plan->fund_source }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($editable && $draft)<form method="POST" action="{{ route('planning.sob.approve', $plan) }}" onsubmit="return confirm('Approve this SOB? It creates or updates the Budget allotments of this quarter.')">@csrf<button @disabled(! $grouped) class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Approve SOB</button></form>
            @elseif(! $draft)<a href="{{ route('planning', ['school_id' => $plan->school_id, 'year' => $plan->fiscal_year]) }}#app" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Choose the APP items →</a>@endif
            <a href="{{ route('planning.sob.print', $plan) }}" target="_blank" rel="noopener" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">Print</a><span class="rounded-full px-3 py-1 text-xs font-bold {{ $draft ? 'bg-amber-100 text-amber-800' : 'bg-secondary/10 text-secondary' }}">{{ $draft ? 'Draft' : 'Approved' }}</span></div>
    </div>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
{{-- "Reopen as a draft" sits in the first box of the page (the document strip), under the form the SOB makes. --}}
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::sob($plan), 'reopenUrl' => (! $draft && $canManage) ? route('planning.sob.reopen', $plan) : null])
<style>details[open] > summary .sum-chevron{transform:rotate(180deg)}</style>
@if($over->isNotEmpty())<div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $over->count() }} program{{ $over->count() === 1 ? ' goes' : 's go' }} over the AIP amount for this quarter. Saving is still allowed; they are marked below.</div>@endif

@if(! $draft && $canManage)
<details class="group mb-5 rounded border border-amber-300 bg-amber-50 p-4">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2 text-amber-900"><h2 class="font-semibold">This SOB is approved</h2><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></summary>
    <p class="mt-1 text-sm text-amber-900">You can still change the fund, the quarter, the details and any item. Each change is saved at once and the Budget allotments and the draft APP follow it (a change is refused if a Purchase Request already draws from the item, if the line is already obligated, or if the item is in an approved APP: unlock the APP first). Reopen it only if you want it back as a draft.</p>
</details>
@endif

@if($editable)
{{-- Add item: a chip on the right opens (and closes) the form under it. --}}
<div class="mb-3 flex justify-end"><button type="button" data-toggle-item-form aria-controls="sob-item-form" aria-expanded="{{ $errors->any() || $plan->items->isEmpty() ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5 rounded-full border border-primary bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Add item</button></div>
<div class="mb-5 rounded border border-outline-variant/30 bg-white p-4 {{ $errors->any() || $plan->items->isEmpty() ? '' : 'hidden' }}" id="sob-item-form">
    <h2 class="font-semibold">Add item</h2>
    <p class="mt-1 text-xs text-on-surface-variant">Choose the Specific Program / Project and its activity, then what is bought for it. Use Add particulars for more than one, and Add other line for anything else (for example Labor). Each amount is frequency × quantity × unit cost.</p>
    <form method="POST" action="{{ route('planning.sob.items.store', $plan) }}" class="sob-line-form mt-3 grid gap-3 md:grid-cols-6">@csrf
        <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">AIP activity (PPA)
            <select name="aip_activity_id" required class="{{ $box }}">
                <option value="">Choose an activity</option>
                @foreach($plan->aip->kras->groupBy(fn ($kra) => \App\Services\SobService::pillarName($kra->pillar)) as $pillarName => $pillarKras)
                    <optgroup label="{{ $pillarName ?: 'Other' }}">
                        {{-- Every program once, by its name; the item is kept under the program's first activity, so later changes to the activities do not matter. --}}
                        @foreach($pillarKras->groupBy(fn ($kra) => $programLabel($kra, null)) as $label => $programKras)
                            @php $programActivities = $programKras->flatMap(fn ($kra) => $kra->activities->pluck('id')); @endphp
                            @if($programActivities->isNotEmpty())<option value="{{ $programActivities->min() }}" @selected($programActivities->contains((int) old('aip_activity_id')))>{{ $label }}</option>@endif
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
        <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">Object of expenditure (account)
            <select name="chart_of_account_id" required class="{{ $box }}">
                <option value="">Choose an account</option>
                @foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('chart_of_account_id') === (string) $account->id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach
            </select>
        </label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
        @include('partials.sob-item-fields', ['group' => null, 'box' => $box])
        <div class="flex items-end gap-3 md:col-span-6"><p class="text-sm font-semibold">Amount: <span data-amount>₱0.00</span></p><button class="ml-auto rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Add item</button></div>
    </form>
</div>
@endif

@forelse($grouped as $pillar)
<details open class="group mb-5 overflow-hidden rounded-lg border border-outline-variant/40 bg-white">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden flex-wrap items-center justify-between gap-x-3 gap-y-1 bg-primary px-5 py-3 text-white">
        <span class="flex items-center gap-3"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">flag</span>
            <span><span class="block text-[10px] font-semibold uppercase tracking-widest text-white/70">Pillar / Enabling Mechanism</span><h2 class="text-base font-semibold">{{ $pillar['pillar'] ?: 'Other' }}</h2></span></span>
        <span class="flex items-center gap-2 text-xs text-white/80 max-sm:basis-full max-sm:pl-9">Sub-Total <strong class="text-white">{{ $peso($pillar['total']) }}</strong><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span>
    </summary>
    @foreach($pillar['programs'] as $program)
        @foreach($program['activities'] as $row)
            @php $isOver = $program['over']; @endphp
            <div class="border-b border-outline-variant/20 px-4 py-3 last:border-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">{{ $program['program'] ?: 'No program' }}</p>
                    {{-- The AIP amount of the whole program in the quarter against what the SOB spends on it (shown once per program). --}}
                    @if($loop->first)<p class="text-xs {{ $isOver ? 'font-bold text-amber-800' : 'text-on-surface-variant' }}">AIP {{ $plan->quarterLabel() }}: {{ $program['aip_amount'] > 0 ? $peso($program['aip_amount']) : 'not yet determined' }} · SOB: {{ $peso($program['total']) }} @if($isOver)· Over the AIP amount @endif</p>@endif
                </div>
                @foreach($row['accounts'] as $group)
                <div class="mt-3 rounded border border-outline-variant/30">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant/20 bg-surface-low px-3 py-1.5">
                        <p class="text-xs font-semibold">{{ $group['account']?->code }} · {{ $group['account']?->title }}@if($group['note'])<span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900">{{ $group['note'] }}</span>@endif</p>
                        <p class="text-xs font-semibold">{{ $peso($group['total']) }}</p>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-xs"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-3 py-1.5">Particulars</th><th class="px-2 text-right">Frequency</th><th class="px-2 text-right">Quantity</th><th class="px-2">Unit</th><th class="px-2 text-right">Unit cost</th><th class="px-3 text-right">Amount</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
                    @foreach($group['items'] as $item)
                        <tr><td class="px-3 py-1.5">{{ $item->particulars }}</td><td class="px-2 text-right">{{ rtrim(rtrim(number_format((float) $item->frequency, 2, '.', ''), '0'), '.') }}</td><td class="px-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td><td class="px-2">{{ $item->unit }}</td><td class="px-2 text-right">{{ number_format((float) $item->unit_cost, 2) }}</td><td class="px-3 text-right font-semibold">{{ number_format((float) $item->amount, 2) }}</td></tr>
                    @endforeach
                    @foreach($group['extras'] as $extra)
                        <tr class="bg-surface-low/60"><td class="px-3 py-1.5 italic">{{ $extra->particulars }}</td><td></td><td></td><td class="px-2">{{ $extra->unit }}</td><td></td><td class="px-3 text-right font-semibold">{{ number_format((float) $extra->amount, 2) }}</td></tr>
                    @endforeach
                    </tbody></table></div>
                    @if($editable)
                    <div class="flex flex-wrap items-start gap-3 border-t border-outline-variant/20 px-3 py-2">
                        <details data-edit-item class="min-w-0 flex-1">
                            <summary class="cursor-pointer text-xs font-semibold text-primary">Edit item</summary>
                            <form method="POST" action="{{ route('planning.sob.groups.update', $plan) }}" class="sob-line-form mt-3 grid gap-3 md:grid-cols-6">@csrf @method('PUT')
                                <input type="hidden" name="old_activity" value="{{ $row['activity']->id }}"><input type="hidden" name="old_account" value="{{ $group['account']?->id }}">
                                <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">AIP activity (PPA)<select name="aip_activity_id" class="{{ $box }}">@foreach($plan->aip->kras->groupBy(fn ($kra) => $programLabel($kra, null)) as $label => $programKras)@php $programActivities = $programKras->flatMap(fn ($kra) => $kra->activities->pluck('id')); $holdsThis = $programActivities->contains($row['activity']->id); @endphp @if($programActivities->isNotEmpty())<option value="{{ $holdsThis ? $row['activity']->id : $programActivities->min() }}" @selected($holdsThis)>{{ $label }}</option>@endif @endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">Object of expenditure (account)<select name="chart_of_account_id" class="{{ $box }}">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($account->id === $group['account']?->id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                @include('partials.sob-item-fields', ['group' => $group, 'box' => $box])
                                <div class="flex items-end gap-3 md:col-span-6"><p class="text-sm font-semibold">Amount: <span data-amount>{{ $peso($group['total']) }}</span></p><button class="ml-auto rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Save item</button></div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('planning.sob.groups.destroy', $plan) }}" onsubmit="return confirm('Remove this whole item with all its particulars?')">@csrf @method('DELETE')<input type="hidden" name="old_activity" value="{{ $row['activity']->id }}"><input type="hidden" name="old_account" value="{{ $group['account']?->id }}"><button class="text-xs font-semibold text-error hover:underline">Remove item</button></form>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endforeach
    @endforeach
</details>
@empty
<p class="mb-5 rounded border border-outline-variant/30 bg-white p-6 text-center text-sm text-on-surface-variant">No items yet.@if($editable) Choose an AIP activity above and add what is bought for it.@endif</p>
@endforelse

@if($grouped)
<section class="mb-5 grid gap-5 lg:grid-cols-2">
    <div class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Grand total</p><p class="mt-1 text-2xl font-bold text-primary">{{ $peso($grandTotal) }}</p></div>
    <div class="rounded border border-outline-variant/30 bg-white p-4">
        <h2 class="font-semibold">Summary per object of expenditure</h2>
        <table class="mt-2 w-full text-xs"><tbody class="divide-y divide-outline-variant/20">
            @foreach($summary as $row)<tr><td class="py-1.5">{{ $row['code'] }} · {{ $row['title'] }}</td><td class="py-1.5 text-right font-semibold">{{ number_format($row['total'], 2) }}</td></tr>@endforeach
            <tr><td class="py-1.5 font-bold">GRAND TOTAL</td><td class="py-1.5 text-right font-bold">{{ number_format($summary->sum('total'), 2) }}</td></tr>
        </tbody></table>
    </div>
</section>
@endif

@if($editable)
<details class="group mb-5 rounded border border-outline-variant/30 bg-white p-4" @if($errors->any()) open @endif>
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2"><h2 class="font-semibold">Details and signatories</h2><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></summary>
    <form method="POST" action="{{ route('planning.sob.update', $plan) }}" class="mt-3 grid gap-3 md:grid-cols-3">@csrf @method('PUT')
        <label class="text-xs font-semibold text-on-surface-variant">Source of fund<select name="fund_source" class="{{ $box }}">@foreach($fundChoices as $fund)<option value="{{ $fund }}" @selected(old('fund_source', $plan->fund_source) === $fund)>{{ $fund }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Quarter<select name="quarter" class="{{ $box }}">@foreach(\App\Models\SobPlan::QUARTERS as $number => $quarterName)<option value="{{ $number }}" @selected((int) old('quarter', $plan->quarter) === $number)>{{ $quarterName }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">School ID<input name="school_id_number" value="{{ old('school_id_number', $plan->school_id_number) }}" maxlength="50" placeholder="Type the School ID" class="{{ $box }}"></label>
        @php $sobSlots = \App\Support\PlanningSignatories::slots([['prepared_by', 'Prepared by', 'Prepared by', null, null, null, null], ['recommended_by', 'Recommending approval', 'Recommending Approval', null, null, null, null], ['approved_by', 'Approved by', 'APPROVED', null, null, null, null]], $plan, \App\Support\PlanningSignatories::lastUsed($plan->school_id, ['prepared_by', 'recommended_by', 'approved_by']), true); @endphp
        <div class="md:col-span-3">@include('partials.signatory-editor', ['slots' => $sobSlots, 'editable' => true, 'document' => 'SOB', 'open' => $errors->any()])</div>
        <div class="md:col-span-3"><button class="rounded border border-primary px-4 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Save details</button></div>
    </form>
</details>
@endif

@if($editable)
<details class="mt-8 rounded-lg border border-error/30 bg-error/5">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2 px-4 py-3 text-xs font-bold uppercase tracking-wider text-error"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span>Danger zone</summary>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-error/20 px-4 py-3">
        <p class="text-xs text-on-surface-variant">{{ $draft ? 'Deleting removes this draft SOB and all of its items.' : 'Deleting removes this approved SOB, its items and releases its Budget allotments.' }}</p>
        <form method="POST" action="{{ route('planning.sob.destroy', $plan) }}" onsubmit="return confirm('{{ $draft ? 'Delete this draft SOB and its items?' : 'Delete this approved SOB, its items and release its Budget allotments?' }}')">@csrf @method('DELETE')<button class="rounded-lg border border-error/50 bg-white px-4 py-2 text-xs font-semibold text-error hover:bg-error/10">{{ $draft ? 'Delete draft' : 'Delete SOB' }}</button></form>
    </div>
</details>
@endif

<script>
    document.querySelector('[data-toggle-item-form]')?.addEventListener('click', (event) => {
        const form = document.getElementById('sob-item-form');
        const show = form.classList.toggle('hidden') === false;
        event.currentTarget.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) { form.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    });
(() => {
    const number = (value) => { const n = Number(String(value ?? '').replace(/[,₱\s]/g, '')); return Number.isFinite(n) ? n : 0; };
    const peso = (amount) => '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.querySelectorAll('.sob-line-form').forEach((form) => {
        const out = form.querySelector('[data-amount]');
        const field = (row, name) => (row.querySelector('[name$="[' + name + ']"]') || {}).value;
        const update = () => {
            let total = 0;
            form.querySelectorAll('[data-particulars-rows] [data-row]').forEach((row) => {
                total += Math.round(number(field(row, 'frequency') || 1) * number(field(row, 'quantity')) * number(field(row, 'unit_cost')) * 100) / 100;
            });
            form.querySelectorAll('[data-extra-rows] [data-row]').forEach((row) => { total += number(field(row, 'amount')); });
            out.textContent = peso(total);
        };
        // + Add particulars / + Add other line: a fresh row from the form's template; x Remove drops a row (one particulars always stays).
        const addRow = (buttonSelector, rowsSelector, templateSelector) => {
            const button = form.querySelector(buttonSelector);
            if (!button) { return; }
            button.addEventListener('click', () => {
                const rows = form.querySelector(rowsSelector);
                const index = Number(rows.dataset.nextIndex || rows.querySelectorAll('[data-row]').length);
                rows.dataset.nextIndex = index + 1;
                rows.insertAdjacentHTML('beforeend', form.querySelector(templateSelector).innerHTML.split('__INDEX__').join(index));
                rows.querySelector('[data-row]:last-child input:not([type=hidden])')?.focus();
                update();
            });
        };
        addRow('[data-add-particulars]', '[data-particulars-rows]', '[data-line-template]');
        addRow('[data-add-extra]', '[data-extra-rows]', '[data-extra-template]');
        form.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-row]');
            if (!remove) { return; }
            const row = remove.closest('[data-row]');
            if (row.parentElement.hasAttribute('data-particulars-rows') && row.parentElement.querySelectorAll('[data-row]').length < 2) { return; }
            row.remove();
            update();
        });
        form.addEventListener('input', update);
        update();
    });
})();
</script>
<script>
// A long activity or account title is cut with "..." inside the closed dropdown box, so the complete text of the chosen one is also written under it.
document.querySelectorAll('[data-full-text-box]').forEach((box) => {
    const select = box.querySelector('select');
    const output = box.querySelector('[data-full-text-output]');
    const show = () => {
        const option = select.options[select.selectedIndex];
        output.textContent = option && option.value ? option.text.replace(/\s+/g, ' ').trim() : '';
    };
    select.addEventListener('change', show);
    show();
});
</script>
@include('partials.doc-preview-dock', ['key' => 'sob-'.$plan->id, 'title' => 'Official SOB', 'savedUrl' => route('planning.sob.print', $plan), 'drafts' => 'form[action$="/items"], form[action*="/items/"], form[action$="/groups"]'])
@endsection
