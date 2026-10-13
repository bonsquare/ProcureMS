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
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $draft = ! $plan->isApproved();
    $editable = $draft && $canManage;
@endphp
<div class="mb-5">
    <a href="{{ route('planning', ['school_id' => $plan->school_id, 'year' => $plan->fiscal_year]) }}#app-cse" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold leading-9 tracking-tight">APP-CSE · FY {{ $plan->fiscal_year }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $plan->school?->name }} · Annual Procurement Plan for Common Supplies and Equipment · Procurement Service catalog {{ $plan->catalog_year }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($editable)<a href="#approve" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Review and approve</a>@endif
            <a href="{{ route('planning.app-cse.print', $plan) }}" target="_blank" rel="noopener" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">Print official form</a><span class="rounded-full px-3 py-1 text-xs font-bold {{ $draft ? 'bg-amber-100 text-amber-900' : 'bg-secondary/10 text-secondary' }}">{{ $draft ? 'Draft' : 'Approved' }}</span></div>
    </div>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::appCse($plan)])

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Details and signatories</h2>
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
            <div class="md:col-span-3">@include('partials.signatory-editor', ['slots' => $cseSlots, 'editable' => true, 'document' => 'APP-CSE', 'open' => $errors->any()])</div>
            <div class="md:col-span-3"><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Save details</button></div>
        </form>
    @else
        <dl class="mt-3 grid gap-2 text-sm md:grid-cols-3">
            @foreach([['Department/Bureau/Office', $plan->office_name], ['Agency Account Code', $plan->account_code], ['Region', $plan->region], ['Address', $plan->address], ['Organization Type', $plan->organization_type], ['Contact Person', $plan->contact_person], ['Position', $plan->contact_position], ['E-mail', $plan->contact_email], ['Telephone/Mobile Nos.', $plan->contact_phone], ['Date Prepared', $plan->date_prepared?->format('F d, Y')], ['Approved budget', $plan->approved_budget !== null ? $peso($plan->approved_budget) : null], ['Approved by', trim($plan->agency_head_name.' '.$plan->agency_head_position)]] as [$label, $value])
                <div><dt class="text-xs font-semibold text-on-surface-variant">{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
    @endif
</section>

@if($editable)
<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Add from the Procurement Service catalog (Part I)</h2>
    <form method="GET" action="{{ route('planning.app-cse.show', $plan) }}" class="mt-3 flex gap-2"><input name="q" value="{{ $query }}" placeholder="Search by description or code" maxlength="100" class="w-full max-w-md rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary"><button class="rounded border border-primary px-3 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Search</button></form>
    @if($query !== '' && $results->isEmpty())<p class="mt-3 text-sm text-on-surface-variant">No catalog item matches "{{ $query }}" that is not already in the plan.</p>@endif
    @foreach($results as $category => $group)
        <h3 class="mt-4 text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ $category }}</h3>
        @foreach($group as $found)
            <form method="POST" action="{{ route('planning.app-cse.items.store', $plan) }}" class="flex flex-wrap items-center gap-3 border-b border-outline-variant/10 py-2 text-sm">@csrf<input type="hidden" name="cse_catalog_item_id" value="{{ $found->id }}">
                <span class="min-w-0 flex-1"><span class="font-mono text-xs text-on-surface-variant">{{ $found->code }}</span> {{ $found->description }} <span class="text-xs text-on-surface-variant">· {{ $found->unit }} · {{ $found->price !== null ? $peso($found->price) : 'no price in the catalog' }}</span></span>
                @if($found->price === null)<input type="number" name="unit_price" step="0.01" min="0.01" required placeholder="Price" class="w-28 rounded border border-outline-variant/50 bg-white px-2 py-1.5 text-sm">@endif
                <button class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-container">Add</button>
            </form>
        @endforeach
    @endforeach
</section>

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Add my own item (Part II: not available at PS)</h2>
    <form method="POST" action="{{ route('planning.app-cse.custom.store', $plan) }}" class="mt-3 grid gap-3 md:grid-cols-5">@csrf
        <label class="text-xs font-semibold text-on-surface-variant">Category<input name="category" required maxlength="150" class="{{ $box }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Description<input name="description" required maxlength="500" class="{{ $box }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Unit<input name="unit" required maxlength="50" class="{{ $box }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">Price<input type="number" name="unit_price" step="0.01" min="0" required class="{{ $box }}"></label>
        <div class="md:col-span-5"><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Add to Part II</button></div>
    </form>
</section>
@endif

@foreach([['Part I · Available at Procurement Service stores', $partOne], ['Part II · Other items not available at PS', $partTwo]] as [$partTitle, $partItems])
<section class="mb-5 rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/20 px-4 py-3"><h2 class="font-semibold">{{ $partTitle }}</h2></div>
    @php
        $withQuantity = $partItems->filter(fn ($item) => $item->totalQuantity() > 0);
        $atZero = $partItems->reject(fn ($item) => $item->totalQuantity() > 0);
    @endphp
    @foreach($withQuantity as $item)
        @include('partials.app-cse-item-card')
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
            @foreach($atZero as $item)
                @include('partials.app-cse-item-card')
            @endforeach
        </details>
    @endif
</section>
@endforeach

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Totals</h2>
    <table class="mt-3 w-full max-w-xl text-sm">
        <tr><td class="py-1">A. Total</td><td class="py-1 text-right">{{ $peso($totals['a']) }}</td></tr>
        <tr><td class="py-1">B. Additional provision for inflation (10% of total)</td><td class="py-1 text-right">{{ $peso($totals['b']) }}</td></tr>
        <tr><td class="py-1">C. Additional provision for transport and freight cost (5%)</td><td class="py-1 text-right">{{ $peso($totals['c']) }}</td></tr>
        <tr class="font-bold"><td class="py-1">D. Grand total (A + B + C)</td><td class="py-1 text-right">{{ $peso($totals['d']) }}</td></tr>
        <tr><td class="py-1">E. Approved budget by the agency head</td><td class="py-1 text-right">{{ $totals['e'] !== null ? $peso($totals['e']) : '—' }}</td></tr>
    </table>
    <table class="mt-4 w-full max-w-xl text-sm">
        <thead><tr class="text-left text-xs text-on-surface-variant"><th class="py-1">F. Monthly cash requirements</th><th class="py-1 text-right">Q1</th><th class="py-1 text-right">Q2</th><th class="py-1 text-right">Q3</th><th class="py-1 text-right">Q4</th><th class="py-1 text-right">Year</th></tr></thead>
        @foreach([['G.1 Available at Procurement Service stores', $totals['g1']], ['G.2 Other items not available at PS', $totals['g2']], ['Total monthly cash requirements', $totals['f']]] as [$label, $row])
            <tr class="{{ $loop->last ? 'font-bold' : '' }}"><td class="py-1">{{ $label }}</td>@foreach(['q1', 'q2', 'q3', 'q4', 'year'] as $key)<td class="py-1 text-right">{{ $peso($row[$key]) }}</td>@endforeach</tr>
        @endforeach
    </table>
</section>

@if($editable)
<section id="approve" class="mb-8 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Approve</h2>
    <p class="mt-1 text-sm text-on-surface-variant">Approving locks the APP-CSE: items, quantities, details and signatories can no longer be changed.</p>
    @if($hasQuantity)
        <form method="POST" action="{{ route('planning.app-cse.approve', $plan) }}" class="mt-3" onsubmit="return confirm('Approve the APP-CSE for FY {{ $plan->fiscal_year }}? It will be locked.')">@csrf<button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Approve APP-CSE</button></form>
    @else
        <p class="mt-3 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">Enter a quantity for at least one item to enable approval.</p>
    @endif
</section>
@endif
@include('partials.doc-preview-dock', ['key' => 'cse-'.$plan->id, 'title' => 'Official APP-CSE', 'savedUrl' => route('planning.app-cse.print', $plan), 'drafts' => 'form[method="POST"][action*="/planning/app-cse/"]:not([action$="/approve"])'])
@endsection
