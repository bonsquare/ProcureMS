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
    <p class="mt-1 text-xs text-on-surface-variant">Choose the Specific Program / Project and its activity, then what is bought for it. Use Add particulars for more than one. Each amount is frequency × quantity × unit cost.</p>
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
        <div class="grid gap-3 md:col-span-6 md:grid-cols-6" data-particulars-rows>
            <div class="contents" data-particulars-row>
                <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Particulars<input name="lines[0][particulars]" required maxlength="255" value="{{ old('lines.0.particulars') }}" class="{{ $box }}"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Frequency<input name="lines[0][frequency]" type="text" inputmode="decimal" value="{{ old('lines.0.frequency', 1) }}" class="{{ $box }}"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Quantity<input name="lines[0][quantity]" type="text" inputmode="decimal" required value="{{ old('lines.0.quantity') }}" class="{{ $box }}"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Unit<input name="lines[0][unit]" required maxlength="50" value="{{ old('lines.0.unit') }}" placeholder="pcs, ream, month" class="{{ $box }}"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Unit cost<input name="lines[0][unit_cost]" type="text" inputmode="decimal" required value="{{ old('lines.0.unit_cost') }}" class="{{ $box }}"></label>
            </div>
        </div>
        <div class="md:col-span-6"><button type="button" data-add-particulars class="rounded border border-primary px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low">+ Add particulars</button></div>
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
                <div class="mt-2 overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="text-on-surface-variant"><tr><th class="py-1.5 pr-2">Particulars</th><th class="px-2">Account</th><th class="px-2 text-right">Frequency</th><th class="px-2 text-right">Quantity</th><th class="px-2">Unit</th><th class="px-2 text-right">Unit cost</th><th class="px-2 text-right">Amount</th>@if($editable)<th></th>@endif</tr></thead><tbody class="divide-y divide-outline-variant/20">
                @foreach($row['items'] as $item)
                    <tr><td class="py-1.5 pr-2">{{ $item->particulars }}</td><td class="px-2">{{ $item->account?->code }} · {{ $item->account?->title }}</td><td class="px-2 text-right">{{ rtrim(rtrim(number_format((float) $item->frequency, 2, '.', ''), '0'), '.') }}</td><td class="px-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td><td class="px-2">{{ $item->unit }}</td><td class="px-2 text-right">{{ number_format((float) $item->unit_cost, 2) }}</td><td class="px-2 text-right font-semibold">{{ number_format((float) $item->amount, 2) }}</td>
                    @if($editable)<td class="whitespace-nowrap px-2 text-right">
                        <details class="inline-block text-left"><summary class="cursor-pointer text-primary">Edit</summary>
                            <form method="POST" action="{{ route('planning.sob.items.update', $item) }}" class="sob-line-form absolute z-10 mt-1 grid w-[min(34rem,90vw)] gap-2 rounded border border-outline-variant/50 bg-white p-3 shadow-lg md:grid-cols-4">@csrf @method('PUT')
                                <div class="md:col-span-4" data-full-text-box><label class="block text-[11px] font-semibold">Activity<select name="aip_activity_id" class="{{ $box }}">@foreach($plan->aip->kras as $kra)@foreach($kra->activities as $activity)<option value="{{ $activity->id }}" @selected($activity->id === $item->aip_activity_id)>{{ $programLabel($kra, $activity) }}</option>@endforeach @endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                <div class="md:col-span-4" data-full-text-box><label class="block text-[11px] font-semibold">Account<select name="chart_of_account_id" class="{{ $box }}">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($account->id === $item->chart_of_account_id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach</select></label><p data-full-text-output class="mt-1 text-xs font-normal leading-5 text-on-surface"></p></div>
                                <label class="text-[11px] font-semibold md:col-span-2">Particulars<input name="particulars" value="{{ $item->particulars }}" maxlength="255" class="{{ $box }}"></label>
                                <label class="text-[11px] font-semibold">Frequency<input name="frequency" value="{{ (float) $item->frequency }}" class="{{ $box }}"></label>
                                <label class="text-[11px] font-semibold">Quantity<input name="quantity" value="{{ (float) $item->quantity }}" class="{{ $box }}"></label>
                                <label class="text-[11px] font-semibold">Unit<input name="unit" value="{{ $item->unit }}" maxlength="50" class="{{ $box }}"></label>
                                <label class="text-[11px] font-semibold">Unit cost<input name="unit_cost" value="{{ (float) $item->unit_cost }}" class="{{ $box }}"></label>
                                <div class="flex items-end justify-between gap-2 md:col-span-2"><span class="text-xs font-semibold">Amount: <span data-amount>{{ $peso($item->amount) }}</span></span><button class="rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white">Save</button></div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('planning.sob.items.destroy', $item) }}" class="ml-2 inline" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')<button class="text-error hover:underline">Remove</button></form>
                    </td>@endif</tr>
                @endforeach
                </tbody></table></div>
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
        const update = () => {
            const rows = form.querySelectorAll('[data-particulars-row]');
            const total = (rows.length ? [...rows] : [form]).reduce((sum, row) => {
                const field = (name) => (row.querySelector('[name$="[' + name + ']"], [name="' + name + '"]') || {}).value;
                return sum + Math.round(number(field('frequency') || 1) * number(field('quantity')) * number(field('unit_cost')) * 100) / 100;
            }, 0);
            out.textContent = peso(total);
        };
        form.addEventListener('input', update);
        update();
    });
    // Add particulars: another row of the same fields under the chosen activity and account.
    document.querySelectorAll('[data-add-particulars]').forEach((button) => {
        const form = button.closest('form');
        const holder = form.querySelector('[data-particulars-rows]');
        button.addEventListener('click', () => {
            const row = holder.querySelector('[data-particulars-row]').cloneNode(true);
            const index = holder.querySelectorAll('[data-particulars-row]').length;
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                input.value = input.name.endsWith('[frequency]') ? '1' : '';
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '× Remove';
            remove.className = 'justify-self-start rounded px-2 py-1 text-xs font-semibold text-error hover:bg-surface-low md:col-span-6';
            remove.addEventListener('click', () => { row.remove(); form.dispatchEvent(new Event('input')); });
            row.appendChild(remove);
            holder.appendChild(row);
            row.querySelector('input').focus();
        });
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
