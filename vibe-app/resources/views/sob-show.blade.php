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
    $editable = $draft && $canManage;
    $grandTotal = collect($grouped)->sum('total');
@endphp
<div class="mb-5">
    <a href="{{ route('planning', ['school_id' => $plan->school_id, 'year' => $plan->fiscal_year]) }}#sob" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">School Operating Budget · {{ $plan->quarterLabel() }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $plan->school?->name }} · FY {{ $plan->fiscal_year }} · {{ $plan->fund_source }}</p>
        </div>
        <div class="flex items-center gap-2"><a href="{{ route('planning.sob.print', $plan) }}" target="_blank" rel="noopener" class="rounded border border-primary px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Print</a><span class="rounded-full px-3 py-1 text-xs font-bold {{ $draft ? 'bg-amber-100 text-amber-800' : 'bg-secondary/10 text-secondary' }}">{{ $draft ? 'Draft' : 'Approved' }}</span></div>
    </div>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@if($over->isNotEmpty())<div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $over->count() }} activit{{ $over->count() === 1 ? 'y goes' : 'ies go' }} over the AIP amount for this quarter. Saving is still allowed; they are marked below.</div>@endif

@if(! $draft && $canManage)
<section class="mb-5 rounded border border-amber-300 bg-amber-50 p-4">
    <h2 class="font-semibold text-amber-900">Revise this SOB</h2>
    <p class="mt-1 text-sm text-amber-900">This SOB is approved and locked. Reopen it to change the fund, the quarter, the details or any item. The budget lines keep their amounts until you approve it again; then they follow your changes.</p>
    <form method="POST" action="{{ route('planning.sob.reopen', $plan) }}" class="mt-3" onsubmit="return confirm('Reopen this SOB for revision? It becomes a draft until you approve it again.')">@csrf<button class="rounded bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Reopen for revision</button></form>
</section>
@endif

@if($editable)
<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Details and signatories</h2>
    <form method="POST" action="{{ route('planning.sob.update', $plan) }}" class="mt-3 grid gap-3 md:grid-cols-3">@csrf @method('PUT')
        <label class="text-xs font-semibold text-on-surface-variant">Source of fund<select name="fund_source" class="{{ $box }}">@foreach($fundChoices as $fund)<option value="{{ $fund }}" @selected(old('fund_source', $plan->fund_source) === $fund)>{{ $fund }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Quarter<select name="quarter" class="{{ $box }}">@foreach(\App\Models\SobPlan::QUARTERS as $number => $quarterName)<option value="{{ $number }}" @selected((int) old('quarter', $plan->quarter) === $number)>{{ $quarterName }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">School ID<input name="school_id_number" value="{{ old('school_id_number', $plan->school_id_number) }}" maxlength="50" placeholder="Type the School ID" class="{{ $box }}"></label>
        @foreach([['prepared_by', 'Prepared by'], ['recommended_by', 'Recommending approval'], ['approved_by', 'Approved by']] as [$prefix, $title])
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-on-surface-variant">{{ $title }}: name<input name="{{ $prefix }}_name" value="{{ old($prefix.'_name', $plan->{$prefix.'_name'}) }}" maxlength="255" class="{{ $box }}"></label>
                <label class="block text-xs font-semibold text-on-surface-variant">Position<input name="{{ $prefix }}_position" value="{{ old($prefix.'_position', $plan->{$prefix.'_position'}) }}" maxlength="255" class="{{ $box }}"></label>
            </div>
        @endforeach
        <div class="md:col-span-3"><button class="rounded border border-primary px-4 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Save details</button></div>
    </form>
</section>

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4" id="sob-item-form">
    <h2 class="font-semibold">Add item</h2>
    <p class="mt-1 text-xs text-on-surface-variant">Choose the Specific Program / Project and its activity, then what is bought for it. Use Add particulars for more than one, and Add other line for anything else (for example Labor). Each amount is frequency × quantity × unit cost.</p>
    <form method="POST" action="{{ route('planning.sob.items.store', $plan) }}" class="sob-line-form mt-3 grid gap-3 md:grid-cols-6">@csrf
        <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">AIP activity (PPA)
            <select name="aip_activity_id" required class="{{ $box }}">
                <option value="">Choose an activity</option>
                @foreach($plan->aip->kras->groupBy(fn ($kra) => \App\Services\SobService::pillarName($kra->pillar)) as $pillarName => $pillarKras)
                    <optgroup label="{{ $pillarName ?: 'Other' }}">
                        @foreach($pillarKras as $kra)@foreach($kra->activities as $activity)<option value="{{ $activity->id }}" @selected((string) old('aip_activity_id') === (string) $activity->id)>{{ $programLabel($kra, $activity) }}</option>@endforeach @endforeach
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
        <div class="flex items-end gap-3 md:col-span-6"><p class="text-sm font-semibold">Amount: <span data-amount>₱0.00</span></p><button class="ml-auto rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Add item</button></div>
    </form>
</section>
@endif

@forelse($grouped as $pillar)
<section class="mb-5 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="flex items-center justify-between border-b border-outline-variant/20 bg-surface-low px-4 py-2.5"><h2 class="text-sm font-bold uppercase tracking-wide text-primary">{{ $pillar['pillar'] ?: 'Other' }}</h2><span class="text-xs font-semibold">Sub-Total {{ $peso($pillar['total']) }}</span></div>
    @foreach($pillar['programs'] as $program)
        @foreach($program['activities'] as $row)
            @php $isOver = $over->has($row['activity']->id); @endphp
            <div class="border-b border-outline-variant/20 px-4 py-3 last:border-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">{{ $program['program'] ?: 'No program' }} <span class="font-normal text-on-surface-variant">· {{ $row['activity']->activity }}</span></p>
                    <p class="text-xs {{ $isOver ? 'font-bold text-amber-800' : 'text-on-surface-variant' }}">AIP {{ $plan->quarterLabel() }}: {{ $row['aip_amount'] > 0 ? $peso($row['aip_amount']) : 'not yet determined' }} · SOB: {{ $peso($row['total']) }} @if($isOver)· Over the AIP amount @endif</p>
                </div>
                @foreach($row['accounts'] as $group)
                <div class="mt-3 rounded border border-outline-variant/30">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant/20 bg-surface-low px-3 py-1.5">
                        <p class="text-xs font-semibold">{{ $group['account']?->code }} · {{ $group['account']?->title }}@if($group['note'])<span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900">{{ $group['note'] }}</span>@endif</p>
                        <p class="text-xs font-semibold">{{ $peso($group['total']) }}</p>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-xs"><thead class="text-on-surface-variant"><tr><th class="px-3 py-1.5">Particulars</th><th class="px-2 text-right">Frequency</th><th class="px-2 text-right">Quantity</th><th class="px-2">Unit</th><th class="px-2 text-right">Unit cost</th><th class="px-3 text-right">Amount</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
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
                                <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">AIP activity (PPA)<select name="aip_activity_id" class="{{ $box }}">@foreach($plan->aip->kras as $kra)@foreach($kra->activities as $activity)<option value="{{ $activity->id }}" @selected($activity->id === $row['activity']->id)>{{ $programLabel($kra, $activity) }}</option>@endforeach @endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                <div class="md:col-span-6" data-full-text-box><label class="block text-xs font-semibold text-on-surface-variant">Object of expenditure (account)<select name="chart_of_account_id" class="{{ $box }}">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($account->id === $group['account']?->id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                @include('partials.sob-item-fields', ['group' => $group, 'box' => $box])
                                <div class="flex items-end gap-3 md:col-span-6"><p class="text-sm font-semibold">Amount: <span data-amount>{{ $peso($group['total']) }}</span></p><button class="ml-auto rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Save item</button></div>
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
</section>
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
<div class="flex flex-wrap items-center gap-2 border-t border-outline-variant/40 pt-4">
    <form method="POST" action="{{ route('planning.sob.approve', $plan) }}" onsubmit="return confirm('Approve this SOB? It is locked and creates or updates the Budget allotments of this quarter.')">@csrf<button @disabled(! $grouped) class="rounded bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Approve SOB</button></form>
    <form method="POST" action="{{ route('planning.sob.destroy', $plan) }}" onsubmit="return confirm('Delete this draft SOB and its items?')">@csrf @method('DELETE')<button class="rounded border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10">Delete draft</button></form>
</div>
@endif

<script>
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
@endsection
