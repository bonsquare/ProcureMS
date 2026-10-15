@extends('layouts.budget')

@section('title', 'APP-CSE')
@section('section', 'Planning')
@section('crumb', 'APP-CSE · FY '.$plan->fiscal_year)

@section('content')
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    $box = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
    $small = 'w-full rounded border border-outline-variant/50 bg-white px-1.5 py-1 text-right text-xs outline-none focus:border-primary';
    $rowColumns = 'grid-template-columns: minmax(13rem, 2.2fr) 6rem repeat(12, minmax(2.6rem, 1fr)) 4rem 7rem;';
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $draft = ! $plan->isApproved();
    $editable = $draft && $canManage;
@endphp
<div class="mb-5">
    @include('partials.planning-jump', ['schoolId' => $plan->school_id, 'year' => $plan->fiscal_year, 'backTo' => 'app-cse'])
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold leading-9 tracking-tight">APP-CSE · FY {{ $plan->fiscal_year }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $plan->school?->name }} · Annual Procurement Plan for Common Supplies and Equipment · Procurement Service catalog {{ $plan->catalog_year }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($editable)<form method="POST" action="{{ route('planning.app-cse.approve', $plan) }}" onsubmit="return confirm('Approve the APP-CSE for FY {{ $plan->fiscal_year }}? Approving locks it: items, quantities, details and signatories can no longer be changed.')">@csrf<button @disabled(! $hasQuantity) title="{{ $hasQuantity ? 'Approving locks the APP-CSE: items, quantities, details and signatories can no longer be changed.' : 'Enter a quantity for at least one item to enable approval.' }}" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Approve APP-CSE</button></form>@endif
            <a href="{{ route('planning.app-cse.print', $plan) }}" target="_blank" rel="noopener" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">Print official form</a><span class="rounded-full px-3 py-1 text-xs font-bold {{ $draft ? 'bg-amber-100 text-amber-900' : 'bg-secondary/10 text-secondary' }}">{{ $draft ? 'Draft' : 'Approved' }}</span></div>
    </div>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::appCse($plan), 'reopenUrl' => ($plan->isApproved() && $canManage) ? route('planning.app-cse.reopen', $plan) : null, 'reopenConfirm' => 'Reopen this APP-CSE as a draft? You can then change its details and items, and approve it again afterwards.'])

<style>details[open] > summary .sum-chevron{transform:rotate(180deg)}</style>
<details open id="plan-details" class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span><h2 class="font-semibold">Details</h2></summary>
    @if($editable)
        <form method="POST" action="{{ route('planning.app-cse.update', $plan) }}" class="mt-3 grid gap-3 md:grid-cols-3">@csrf @method('PUT')
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Department/Bureau/Office<input name="office_name" value="{{ old('office_name', $plan->office_name) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Agency Account Code<input name="account_code" value="{{ old('account_code', $plan->account_code) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Region<input name="region" value="{{ old('region', $plan->region) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Address<input name="address" value="{{ old('address', $plan->address) }}" maxlength="500" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Organization Type<input name="organization_type" value="{{ old('organization_type', $plan->organization_type) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Contact Person<input name="contact_person" value="{{ old('contact_person', $plan->contact_person) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Position<input name="contact_position" value="{{ old('contact_position', $plan->contact_position) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">E-mail<input name="contact_email" value="{{ old('contact_email', $plan->contact_email) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Telephone/Mobile Nos.<input name="contact_phone" value="{{ old('contact_phone', $plan->contact_phone) }}" maxlength="255" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Date Prepared<input type="date" name="date_prepared" value="{{ old('date_prepared', $plan->date_prepared?->toDateString()) }}" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">E. Approved budget by the agency head (figures)<input type="number" name="approved_budget" step="0.01" min="0" value="{{ old('approved_budget', $plan->approved_budget) }}" class="{{ $box }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">E. Approved budget (in words)<input name="approved_budget_words" value="{{ old('approved_budget_words', $plan->approved_budget_words) }}" maxlength="500" class="{{ $box }}"></label>
            <div class="md:col-span-3"><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Save details</button></div>
        </form>
    @else
        <dl class="mt-3 grid gap-2 text-sm md:grid-cols-3">
            @foreach([['Department/Bureau/Office', $plan->office_name], ['Agency Account Code', $plan->account_code], ['Region', $plan->region], ['Address', $plan->address], ['Organization Type', $plan->organization_type], ['Contact Person', $plan->contact_person], ['Position', $plan->contact_position], ['E-mail', $plan->contact_email], ['Telephone/Mobile Nos.', $plan->contact_phone], ['Date Prepared', $plan->date_prepared?->format('F d, Y')], ['Approved budget', $plan->approved_budget !== null ? $peso($plan->approved_budget) : null], ['Approved by', trim($plan->agency_head_name.' '.$plan->agency_head_position)]] as [$label, $value])
                <div><dt class="text-xs font-semibold text-on-surface-variant">{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
    @endif
</details>

@foreach([['Part I · Available at Procurement Service stores', $partOne], ['Part II · Other items not available at PS', $partTwo]] as [$partTitle, $partItems])
<details open class="mb-5 rounded border border-outline-variant/30 bg-white">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2 border-b border-outline-variant/20 px-4 py-3"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span><h2 class="font-semibold">{{ $partTitle }}</h2></summary>
    @php
        $withQuantity = $partItems->filter(fn ($item) => $item->totalQuantity() > 0);
        $atZero = $partItems->reject(fn ($item) => $item->totalQuantity() > 0);
    @endphp
    @foreach($withQuantity->groupBy('category') as $categoryName => $categoryItems)
        <div class="overflow-x-auto"><div class="min-w-[68rem]">
        <div class="border-b border-outline-variant/20 bg-primary/5">
                    <div class="bg-primary px-4 py-2 text-sm font-bold uppercase tracking-wide text-white">{{ $categoryName ?: 'Other' }}</div>
                    <div class="grid items-end gap-x-1 px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant" style="{{ $rowColumns }}">
                        <span style="grid-column: 1; grid-row: 1 / span 2;">Item</span>
                        <span class="text-right" style="grid-column: 2; grid-row: 1 / span 2;">Unit Price</span>
                        @foreach([1, 2, 3, 4] as $quarter)<span class="rounded-t border-b border-primary/40 bg-primary/10 py-0.5 text-center text-primary" style="grid-column: {{ 3 + ($quarter - 1) * 3 }} / span 3; grid-row: 1;">Q{{ $quarter }}</span>@endforeach
                        @foreach($monthNames as $index => $name)<span class="text-center" style="grid-column: {{ 3 + $index }}; grid-row: 2;">{{ $name }}</span>@endforeach
                        <span class="text-right" style="grid-column: 15; grid-row: 1 / span 2;">Qty</span>
                        <span class="text-right" style="grid-column: 16; grid-row: 1 / span 2;">Amount</span>
                    </div>
                </div>
        @foreach($categoryItems as $item)
            @include('partials.app-cse-item-card')
        @endforeach
        </div></div>
    @endforeach
    @if($partItems->isEmpty())
        <div class="px-4 py-6 text-center text-sm text-on-surface-variant">No items yet.</div>
    @elseif($withQuantity->isEmpty())
        <div class="px-4 py-4 text-center text-sm text-on-surface-variant">No item has a quantity yet.</div>
    @endif
    {{-- Lines still at zero are not printed; they are kept here, folded, so a quantity can still be entered. --}}
    @if($atZero->isNotEmpty())
        <details class="border-t border-outline-variant/20">
            <summary class="cursor-pointer px-4 py-3 text-xs font-semibold text-primary">{{ $atZero->count() }} item{{ $atZero->count() === 1 ? '' : 's' }} with no quantity yet (not printed)</summary>
            @foreach($atZero->groupBy('category') as $categoryName => $categoryItems)
                <div class="overflow-x-auto"><div class="min-w-[68rem]">
                <div class="border-b border-outline-variant/20 bg-primary/5">
                    <div class="bg-primary px-4 py-2 text-sm font-bold uppercase tracking-wide text-white">{{ $categoryName ?: 'Other' }}</div>
                    <div class="grid items-end gap-x-1 px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant" style="{{ $rowColumns }}">
                        <span style="grid-column: 1; grid-row: 1 / span 2;">Item</span>
                        <span class="text-right" style="grid-column: 2; grid-row: 1 / span 2;">Unit Price</span>
                        @foreach([1, 2, 3, 4] as $quarter)<span class="rounded-t border-b border-primary/40 bg-primary/10 py-0.5 text-center text-primary" style="grid-column: {{ 3 + ($quarter - 1) * 3 }} / span 3; grid-row: 1;">Q{{ $quarter }}</span>@endforeach
                        @foreach($monthNames as $index => $name)<span class="text-center" style="grid-column: {{ 3 + $index }}; grid-row: 2;">{{ $name }}</span>@endforeach
                        <span class="text-right" style="grid-column: 15; grid-row: 1 / span 2;">Qty</span>
                        <span class="text-right" style="grid-column: 16; grid-row: 1 / span 2;">Amount</span>
                    </div>
                </div>
                @foreach($categoryItems as $item)
                    @include('partials.app-cse-item-card')
                @endforeach
                </div></div>
            @endforeach
        </details>
    @endif
</details>
@endforeach

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Totals</h2>
    <div class="mt-3 grid gap-4 lg:grid-cols-5">
        <div class="overflow-hidden rounded-lg border border-outline-variant/40 lg:col-span-2">
            <p class="border-b border-outline-variant/30 bg-surface-low px-4 py-2 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Summary of amounts</p>
            <dl class="divide-y divide-outline-variant/20 text-sm">
                <div class="flex items-baseline justify-between gap-4 px-4 py-2.5"><dt><span class="mr-1.5 font-bold text-primary">A</span>Total</dt><dd class="font-semibold tabular-nums">{{ $peso($totals['a']) }}</dd></div>
                <div class="flex items-baseline justify-between gap-4 px-4 py-2.5"><dt><span class="mr-1.5 font-bold text-primary">B</span>Additional provision for inflation <span class="text-xs text-on-surface-variant">(10% of total)</span></dt><dd class="tabular-nums">{{ $peso($totals['b']) }}</dd></div>
                <div class="flex items-baseline justify-between gap-4 px-4 py-2.5"><dt><span class="mr-1.5 font-bold text-primary">C</span>Additional provision for transport and freight cost <span class="text-xs text-on-surface-variant">(5%)</span></dt><dd class="tabular-nums">{{ $peso($totals['c']) }}</dd></div>
                <div class="flex items-baseline justify-between gap-4 bg-primary px-4 py-3 text-white"><dt class="font-bold"><span class="mr-1.5">D</span>Grand total <span class="text-xs font-normal text-white/80">(A + B + C)</span></dt><dd class="text-base font-bold tabular-nums">{{ $peso($totals['d']) }}</dd></div>
                <div class="flex items-baseline justify-between gap-4 px-4 py-2.5"><dt><span class="mr-1.5 font-bold text-primary">E</span>Approved budget by the agency head</dt><dd class="font-semibold tabular-nums">{{ $totals['e'] !== null ? $peso($totals['e']) : '—' }}</dd></div>
            </dl>
        </div>
        <div class="overflow-x-auto rounded-lg border border-outline-variant/40 lg:col-span-3">
            <p class="border-b border-outline-variant/30 bg-surface-low px-4 py-2 text-xs font-bold uppercase tracking-wider text-on-surface-variant"><span class="mr-1.5 text-primary">F</span>Monthly cash requirements</p>
            <table class="w-full min-w-[34rem] text-sm">
                <thead><tr class="border-b border-outline-variant/30 text-left text-[11px] uppercase tracking-wider text-on-surface-variant"><th class="px-4 py-2 font-semibold">Source</th>@foreach(['Q1', 'Q2', 'Q3', 'Q4', 'Year'] as $heading)<th class="px-3 py-2 text-right font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-outline-variant/20">
                @foreach([['G.1', 'Available at Procurement Service stores', $totals['g1']], ['G.2', 'Other items not available at PS', $totals['g2']], ['', 'Total monthly cash requirements', $totals['f']]] as [$code, $label, $row])
                    <tr class="{{ $loop->last ? 'bg-primary/5 font-bold' : '' }}"><td class="px-4 py-2.5">@if($code)<span class="mr-1.5 font-bold text-primary">{{ $code }}</span>@endif{{ $label }}</td>@foreach(['q1', 'q2', 'q3', 'q4', 'year'] as $key)<td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $key === 'year' ? 'font-semibold' : '' }}">{{ $peso($row[$key]) }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<details open class="mb-5 rounded border border-outline-variant/30 bg-white">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2 p-4"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span><h2 class="font-semibold">SOB signatories <span class="ml-1 text-xs font-normal text-on-surface-variant">history of SOB created · {{ $sobHistory->count() }} SOB · view only</span></h2></summary>
    @if($sobHistory->isEmpty())
        <p class="border-t border-outline-variant/20 px-4 py-6 text-center text-sm text-on-surface-variant">No SOB created yet for FY {{ $plan->fiscal_year }}.</p>
    @else
        <div class="overflow-x-auto border-t border-outline-variant/20">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-2.5 font-semibold">SOB</th><th class="px-3 py-2.5 font-semibold">Created</th><th class="px-3 py-2.5 font-semibold">Prepared by</th><th class="px-3 py-2.5 font-semibold">Recommending approval</th><th class="px-3 py-2.5 font-semibold">Approved by</th><th class="px-4 py-2.5"></th></tr></thead>
                <tbody class="divide-y divide-outline-variant/20">
                    @foreach($sobHistory as $sob)
                        <tr>
                            <td class="px-4 py-3"><span class="font-semibold">{{ $sob->quarterLabel() }}</span><span class="block text-xs text-on-surface-variant">{{ $sob->fund_source }} · {{ $sob->status === 'approved' ? 'Approved' : 'Draft' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-on-surface-variant">{{ $sob->created_at?->format('M d, Y') }}</td>
                            @foreach(['prepared_by', 'recommended_by', 'approved_by'] as $signatoryKey)
                                <td class="px-3 py-3">@if($sob->{$signatoryKey.'_name'})<span class="font-semibold">{{ $sob->{$signatoryKey.'_name'} }}</span><span class="block text-xs text-on-surface-variant">{{ $sob->{$signatoryKey.'_position'} }}</span>@else<span class="text-on-surface-variant">—</span>@endif</td>
                            @endforeach
                            <td class="whitespace-nowrap px-4 py-3 text-right"><a href="{{ route('planning.sob.show', $sob) }}" class="text-xs font-semibold text-primary hover:underline">Edit in the SOB</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</details>

@if($editable)
{{-- Signatories of the APP-CSE: at the very bottom of the page, side by side (a form of their own: only the signatory fields are sent). --}}
<section class="mb-8 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Signatories</h2>
    <form method="POST" action="{{ route('planning.app-cse.update', $plan) }}" class="mt-3">@csrf @method('PUT')
        @php
            $cseDefs = [];
            foreach ([['prepared_by', 'Prepared by', 'Prepared by:', 'Property/Supply Officer'], ['noted_by', 'Noted by', 'NOTED:', 'School Head'], ['certified_by', 'Certified funds available', 'Certified Funds Available / Certified Appropriate Funds Available:', 'Accountant / Local Budget Officer']] as [$slotKey, $slotLegend, $slotTitle, $slotPosition]) {
                $slotRow = collect($signatoryRows)->first(fn ($row) => $row['slot']['key'] === $slotKey);
                $cseDefs[] = [$slotKey, $slotLegend, rtrim($slotTitle, ': '), null, null, $slotRow['default']?->name ?: 'Full name', 'Blank prints: '.$slotPosition];
            }
            $cseDefs[] = ['agency_head', 'Approved by', 'Approved by', null, null, 'Full name (Head of Office/Agency)', null];
            $cseSlots = \App\Support\PlanningSignatories::slots($cseDefs, $plan, [], true);
            foreach ($cseSlots as $slotIndex => $cseSlot) {
                $cseSlots[$slotIndex]['picker'] = collect($signatoryRows)->first(fn ($row) => $row['slot']['key'] === $cseSlot['key']);
            }
        @endphp
        @include('partials.signatory-editor', ['slots' => $cseSlots, 'editable' => true, 'document' => 'APP-CSE', 'open' => $errors->any(), 'saveLabel' => 'Save signatories', 'horizontal' => true])
    </form>
</section>
@endif
@include('partials.doc-preview-dock', ['key' => 'cse-'.$plan->id, 'title' => 'Official APP-CSE', 'savedUrl' => route('planning.app-cse.print', $plan), 'drafts' => 'form[method="POST"][action*="/planning/app-cse/"]:not([action$="/approve"])'])
@endsection
