@extends('layouts.budget')
@section('title', 'Allotment Registry')
@section('crumb', 'Allotment Registry')
@section('content')
@php
    $th = 'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $field = 'rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none focus:border-primary';
    $query = ['year' => $year, 'school_id' => $selectedSchoolId, 'fund' => $fund, 'quarter' => $quarter ?: null];
    $grand = ['allotment' => $registry->sum(fn ($r) => $r['totals']['allotment']), 'obligation' => $registry->sum(fn ($r) => $r['totals']['obligation']), 'disbursement' => $registry->sum(fn ($r) => $r['totals']['disbursement'])];
@endphp
<style>@media print { aside, header, .no-print { display: none !important; } .md\:pl-72 { padding-left: 0 !important; } main { padding: 0 !important; } }</style>
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Registry of Allotments, Obligations and Disbursements</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">FY {{ $year }}{{ $quarter ? ' · Q' . $quarter : '' }}. The approved SOB is the actual budget, quarter by quarter; funding that has no project is added as a manual allotment. Obligations are ORS; disbursements are paid DVs.</p>
    </div>
    <div class="no-print flex flex-wrap items-center gap-2">
        <a href="{{ route('planning') }}#sob" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">SOB</a>
        <a href="{{ route('allotment-registry', $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
        <button onclick="window.print()" class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Print</button>
    </div>
</div>

<form method="GET" class="no-print mb-5 flex flex-wrap items-center gap-2">
    <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="{{ $field }} w-24" aria-label="Fiscal year">
    @if($schools->count() > 1)<select name="school_id" class="{{ $field }}"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select>@endif
    <select name="fund" class="{{ $field }}"><option value="">All fund sources</option>@foreach($funds as $f)<option @selected($fund === $f)>{{ $f }}</option>@endforeach</select>
    <select name="quarter" class="{{ $field }}"><option value="">Whole year</option>@foreach([1, 2, 3, 4] as $q)<option value="{{ $q }}" @selected($quarter === $q)>Q{{ $q }}</option>@endforeach</select>
    <button class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Filter</button>
</form>

@php
    $sobRegistry = $registry->filter(fn ($r) => str_starts_with((string) $r['line']->remarks, 'From SOB'))->values();
    $manualRegistry = $registry->reject(fn ($r) => str_starts_with((string) $r['line']->remarks, 'From SOB'))->values();
    $kpiAllotment = $summary->sum('allotment_total');
    $kpiObligation = $summary->sum('obligation');
    $kpiDisbursement = $summary->sum('disbursement');
    $kpiRate = $kpiAllotment > 0 ? min(100, round($kpiObligation / $kpiAllotment * 100, 1)) : 0;
@endphp
<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['Total allotment', $kpiAllotment, 'account_balance_wallet', 'text-primary bg-primary/10'], ['Obligated', $kpiObligation, 'assignment_turned_in', 'text-amber-800 bg-amber-100'], ['Disbursed', $kpiDisbursement, 'payments', 'text-secondary bg-secondary/10'], ['Not yet obligated', $kpiAllotment - $kpiObligation, 'savings', 'text-primary bg-primary/10']] as [$kpiLabel, $kpiValue, $kpiIcon, $kpiTone])
        <div class="flex items-center gap-3 rounded-xl border border-outline-variant/40 bg-white px-4 py-3 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $kpiTone }}"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ $kpiIcon }}</span></span>
            <div class="min-w-0"><p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">{{ $kpiLabel }}</p><p class="text-lg font-bold tabular-nums">₱{{ number_format($kpiValue, 2) }}</p></div>
        </div>
    @endforeach
    <div class="rounded-xl border border-outline-variant/40 bg-white px-4 py-3 shadow-sm sm:col-span-2 xl:col-span-4">
        <div class="flex items-baseline justify-between text-xs font-semibold"><span class="text-on-surface-variant">Budget used (obligated ÷ allotment)</span><span class="text-primary">{{ $kpiRate }}%</span></div>
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-high" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $kpiRate }}"><div class="h-full rounded-full bg-gradient-to-r from-primary to-secondary" style="width: {{ $kpiRate }}%"></div></div>
    </div>
</div>

<style>details[open] > summary .sum-chevron{transform:rotate(180deg)}</style>
<details open class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden border-b border-outline-variant/30 px-5 py-3"><h2 class="text-base font-semibold">Summary per Source of Fund</h2><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span></summary>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant">
            <tr><th rowspan="2" class="{{ $th }} align-bottom">Source of Fund</th><th colspan="4" class="{{ $th }} text-center">Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Total Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Obligations</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Disbursements</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Not Yet Obligated</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Unpaid Obligations</th></tr>
            <tr><th class="{{ $th }} w-28 text-center">Q1</th><th class="{{ $th }} w-28 text-center">Q2</th><th class="{{ $th }} w-28 text-center">Q3</th><th class="{{ $th }} w-28 text-center">Q4</th></tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/20">
            @forelse($summary as $name => $row)
                <tr data-fund-row="{{ $name }}" tabindex="0" role="button" aria-expanded="false" title="Show the items funded from the SOB" class="cursor-pointer hover:bg-surface-low/60"><td class="px-3 py-2.5 font-semibold"><span class="inline-flex items-center gap-1"><span class="material-symbols-outlined sum-chevron text-[18px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span>{{ $name }}</span></td>@foreach($row['allotment'] as $a)<td class="px-3 py-2.5 text-center tabular-nums {{ $a ? '' : 'text-on-surface-variant/40' }}">{{ $a ? number_format($a, 2) : '–' }}</td>@endforeach<td class="px-3 py-2.5 text-center font-semibold tabular-nums">{{ number_format($row['allotment_total'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($row['obligation'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($row['disbursement'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($row['allotment_total'] - $row['obligation'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($row['obligation'] - $row['disbursement'], 2) }}</td></tr>
                @php $fundItems = $sobRegistry->filter(fn ($r) => $r['line']->source_of_fund === $name)->values(); @endphp
                <tr data-fund-detail="{{ $name }}" hidden>
                    <td colspan="10" class="bg-surface-low/40 p-0">
                        <p class="px-5 pt-3 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Items funded from the SOB · {{ $name }} · {{ $fundItems->count() }} item{{ $fundItems->count() === 1 ? '' : 's' }}</p>
                        <div class="overflow-x-auto px-3 pb-3"><table class="w-full min-w-[900px] text-left text-sm">
                            <thead class="text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr><th rowspan="2" class="{{ $th }} align-bottom">Program / Project</th><th rowspan="2" class="{{ $th }} align-bottom">Object of Expenditure</th><th colspan="4" class="{{ $th }} text-center">Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Total Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Obligated</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Not Yet Obligated</th></tr>
                                <tr><th class="{{ $th }} w-28 text-center">Q1</th><th class="{{ $th }} w-28 text-center">Q2</th><th class="{{ $th }} w-28 text-center">Q3</th><th class="{{ $th }} w-28 text-center">Q4</th></tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/20 bg-white">
                                @forelse($fundItems as $r)
                                    @php $l = $r['line']; @endphp
                                    <tr><td class="px-3 py-2.5">{{ $l->program ?: '—' }}@if($selectedSchoolId === null && $schools->count() > 1)<span class="block text-xs text-on-surface-variant">{{ $l->school?->name }}</span>@endif</td><td class="min-w-[16rem] px-3 py-2.5"><span class="block break-all text-sm font-semibold tabular-nums text-primary">{{ $l->uacs_code }}</span><span class="block whitespace-normal break-words">{{ $l->particulars }}</span></td>@foreach([1, 2, 3, 4] as $q)<td class="px-3 py-2.5 text-center tabular-nums {{ $l->quarterAmount($q) > 0 ? 'font-semibold text-primary' : 'text-on-surface-variant/40' }}">{{ $l->quarterAmount($q) > 0 ? number_format($l->quarterAmount($q), 2) : '–' }}</td>@endforeach<td class="px-3 py-2.5 text-center font-semibold tabular-nums">{{ number_format($r['totals']['allotment'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($r['totals']['obligation'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($r['totals']['not_obligated'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="9" class="px-4 py-5 text-center text-on-surface-variant">No item funded from the SOB under this fund.</td></tr>
                                @endforelse
                            </tbody>
                            @if($fundItems->isNotEmpty())
                            <tfoot class="border-t-2 border-outline-variant/40 bg-white font-bold"><tr><td colspan="2" class="px-3 py-2.5">TOTAL</td>@foreach([1, 2, 3, 4] as $q)<td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($fundItems->sum(fn ($r) => $r['line']->quarterAmount($q)), 2) }}</td>@endforeach<td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($fundItems->sum(fn ($r) => $r['totals']['allotment']), 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($fundItems->sum(fn ($r) => $r['totals']['obligation']), 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($fundItems->sum(fn ($r) => $r['totals']['not_obligated']), 2) }}</td></tr></tfoot>
                            @endif
                        </table></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-on-surface-variant">No allotments for FY {{ $year }}. Approve a quarter of the SOB or add a manual allotment.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</details>

<details open class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden border-b border-outline-variant/30 px-5 py-3"><h2 class="text-base font-semibold">Manual allotments <span class="ml-1 text-xs font-normal text-on-surface-variant">funding with no project · {{ $manualRegistry->count() }} item{{ $manualRegistry->count() === 1 ? '' : 's' }}</span></h2><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span></summary>
    @if($canManage)
        <div class="no-print flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 bg-surface-low/40 px-5 py-3">
            <p class="text-xs text-on-surface-variant">For funds that do not come from a project of the SOB, such as a donation, a fund transfer or a grant. Pick the source of fund, the object of expenditure and the amount per quarter.</p>
            <a href="{{ route('budget.allocation.create', array_filter(['year' => $year, 'school_id' => $selectedSchoolId])) }}" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary px-3.5 py-1.5 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Add manual allotment</a>
        </div>
    @endif
    <div class="overflow-x-auto"><table class="w-full min-w-[960px] text-left text-sm">
        <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant">
            <tr><th rowspan="2" class="{{ $th }} align-bottom">Source of Fund</th><th rowspan="2" class="{{ $th }} align-bottom">Particulars</th><th rowspan="2" class="{{ $th }} align-bottom">Object of Expenditure</th><th colspan="4" class="{{ $th }} text-center">Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Total Allotment</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Obligated</th><th rowspan="2" class="{{ $th }} text-center align-bottom">Not Yet Obligated</th><th rowspan="2" class="{{ $th }}"></th></tr>
            <tr><th class="{{ $th }} w-28 text-center">Q1</th><th class="{{ $th }} w-28 text-center">Q2</th><th class="{{ $th }} w-28 text-center">Q3</th><th class="{{ $th }} w-28 text-center">Q4</th></tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/20">
            @forelse($manualRegistry as $r)
                @php $l = $r['line']; @endphp
                <tr><td class="px-3 py-2.5 font-semibold">{{ $l->source_of_fund }}</td><td class="px-3 py-2.5">{{ $l->program ?: ($l->remarks ?: '—') }}<span class="block text-xs text-on-surface-variant">{{ $l->budget_ref_no }}</span></td><td class="min-w-[16rem] px-3 py-2.5"><span class="block break-all text-sm font-semibold tabular-nums text-primary">{{ $l->uacs_code }}</span><span class="block whitespace-normal break-words">{{ $l->particulars }}</span></td>@foreach([1, 2, 3, 4] as $q)<td class="px-3 py-2.5 text-center tabular-nums {{ $l->quarterAmount($q) > 0 ? 'font-semibold text-primary' : 'text-on-surface-variant/40' }}">{{ $l->quarterAmount($q) > 0 ? number_format($l->quarterAmount($q), 2) : '–' }}</td>@endforeach<td class="px-3 py-2.5 text-center font-semibold tabular-nums">{{ number_format($r['totals']['allotment'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($r['totals']['obligation'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($r['totals']['not_obligated'], 2) }}</td><td class="no-print whitespace-nowrap px-3 py-2.5 text-right"><a href="{{ route('budget.allocation.show', $l) }}" title="Open" aria-label="Open {{ $l->budget_ref_no }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-primary bg-white text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span></a></td></tr>
            @empty
                <tr><td colspan="11" class="px-4 py-8 text-center text-on-surface-variant">No manual allotment for FY {{ $year }}.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</details>

<script>
    // The registry can be printed: every line is opened for printing and put back afterwards.
    (() => {
        let closed = [];
        window.addEventListener('beforeprint', () => { closed = Array.from(document.querySelectorAll('details:not([open])')); closed.forEach((box) => { box.open = true; }); });
        window.addEventListener('afterprint', () => { closed.forEach((box) => { box.open = false; }); closed = []; });
    })();
</script>

<script>
    // Click a source of fund in the summary to open its details (the items the SOB funded under it) right under it; click it again to close them.
    (() => {
        const rows = [...document.querySelectorAll('[data-fund-row]')];
        const detailOf = (row) => [...document.querySelectorAll('[data-fund-detail]')].find((detail) => detail.dataset.fundDetail === row.dataset.fundRow);
        const choose = (row) => {
            const detail = detailOf(row);
            if (!detail) return;
            const opening = detail.hidden;
            detail.hidden = !opening;
            row.setAttribute('aria-expanded', opening ? 'true' : 'false');
            row.querySelector('.sum-chevron').style.transform = opening ? 'rotate(180deg)' : '';
            row.classList.toggle('bg-primary/5', opening);
        };
        rows.forEach((row) => {
            row.addEventListener('click', () => choose(row));
            row.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(row); } });
        });
        window.addEventListener('beforeprint', () => { document.querySelectorAll('[data-fund-detail]').forEach((detail) => { detail.hidden = false; }); });
    })();
</script>
@endsection
