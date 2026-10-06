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
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">FY {{ $year }}{{ $quarter ? ' · Q' . $quarter : '' }}. Allotments come from the approved AIP or budget allocation; obligations are ORS; disbursements are paid DVs.</p>
    </div>
    <div class="no-print flex flex-wrap items-center gap-2">
        <a href="{{ route('aip') }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">AIP</a>
        <a href="{{ route('allotment-registry', $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
        <button onclick="window.print()" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Print</button>
    </div>
</div>

<form method="GET" class="no-print mb-5 flex flex-wrap items-center gap-2">
    <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="{{ $field }} w-24" aria-label="Fiscal year">
    @if($schools->count() > 1)<select name="school_id" class="{{ $field }}"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select>@endif
    <select name="fund" class="{{ $field }}"><option value="">All fund sources</option>@foreach($funds as $f)<option @selected($fund === $f)>{{ $f }}</option>@endforeach</select>
    <select name="quarter" class="{{ $field }}"><option value="">Whole year</option>@foreach([1, 2, 3, 4] as $q)<option value="{{ $q }}" @selected($quarter === $q)>Q{{ $q }}</option>@endforeach</select>
    <button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Filter</button>
</form>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Summary per Source of Fund</h2>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-surface-low"><tr><th class="{{ $th }}">Source of Fund</th><th class="{{ $th }} text-right">Q1 Allotment</th><th class="{{ $th }} text-right">Q2</th><th class="{{ $th }} text-right">Q3</th><th class="{{ $th }} text-right">Q4</th><th class="{{ $th }} text-right">Total Allotment</th><th class="{{ $th }} text-right">Obligations</th><th class="{{ $th }} text-right">Disbursements</th><th class="{{ $th }} text-right">Not Yet Obligated</th><th class="{{ $th }} text-right">Unpaid Obligations</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20">
            @forelse($summary as $name => $row)
                <tr><td class="px-3 py-2.5 font-semibold">{{ $name }}</td>@foreach($row['allotment'] as $a)<td class="px-3 py-2.5 text-right tabular-nums">{{ $a ? number_format($a, 2) : '' }}</td>@endforeach<td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($row['allotment_total'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['obligation'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['disbursement'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['allotment_total'] - $row['obligation'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['obligation'] - $row['disbursement'], 2) }}</td></tr>
            @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-on-surface-variant">No allotments for FY {{ $year }}. Approve an AIP or add a budget allocation.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</section>

@foreach($registry as $r)
    @php $l = $r['line']; $t = $r['totals']; @endphp
    <section class="mb-5 overflow-hidden rounded border border-outline-variant/30 bg-white" style="break-inside: avoid">
        <div class="border-b border-outline-variant/30 bg-surface-low/60 px-5 py-3">
            <p class="text-sm font-semibold">{{ $l->source_of_fund }} · UACS {{ $l->uacs_code }} · {{ $l->particulars }}</p>
            <p class="text-xs text-on-surface-variant">{{ $l->office }}@if($l->program) · {{ $l->program }}@endif · {{ $l->budget_ref_no }}</p>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm">
            <thead><tr class="bg-surface-low"><th class="{{ $th }}">Date</th><th class="{{ $th }}">ORS / DV / Check No.</th><th class="{{ $th }}">Payee</th><th class="{{ $th }}">Particulars</th><th class="{{ $th }} text-right">Allotments</th><th class="{{ $th }} text-right">Obligations</th><th class="{{ $th }} text-right">Disbursements</th><th class="{{ $th }} text-right">Allotment Not Yet Obligated</th><th class="{{ $th }} text-right">Unpaid Obligations</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @foreach($r['entries'] as $e)
                    <tr class="{{ $e['type'] === 'Allotment' ? 'bg-primary/5' : '' }}"><td class="px-3 py-2.5 whitespace-nowrap">{{ $e['date']?->format('M d, Y') }}</td><td class="px-3 py-2.5">{{ $e['ref'] }}</td><td class="px-3 py-2.5">{{ $e['payee'] }}</td><td class="px-3 py-2.5">{{ $e['particulars'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $e['allotment'] ? number_format($e['allotment'], 2) : '' }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $e['obligation'] ? number_format($e['obligation'], 2) : '' }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $e['disbursement'] ? number_format($e['disbursement'], 2) : '' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($e['not_obligated'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($e['unpaid'], 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-outline-variant/40 bg-surface-low/60 font-bold"><tr><td colspan="4" class="px-3 py-2.5">TOTAL</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($t['allotment'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($t['obligation'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($t['disbursement'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($t['not_obligated'], 2) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($t['unpaid'], 2) }}</td></tr></tfoot>
        </table></div>
    </section>
@endforeach
@endsection
