@extends('layouts.budget')
@section('title', 'Budget Allocation')
@section('content')
@php
    $t = $summary['totals'];
    $counts = $summary['counts'];
    $badge = ['Available' => 'bg-secondary/10 text-secondary', 'Near Limit' => 'bg-amber-100 text-amber-800', 'Fully Obligated' => 'bg-error/10 text-error', 'Closed' => 'bg-surface-container text-on-surface-variant'];
    $query = ['year' => $year, 'school_id' => $selectedSchoolId] + $filters;
    $th = 'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $quarter = (int) ($filters['quarter'] ?? 0);
    $field = 'rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none focus:border-primary';
@endphp
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Budget Allocation</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Annual budget per account code, divided into four quarters. <a href="{{ route('budget', ['year' => $year, 'school_id' => $selectedSchoolId]) }}" class="font-semibold text-primary hover:underline">Obligations &amp; ORS →</a></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($canManage)<a href="{{ route('budget.allocation.create', ['year' => $year, 'school_id' => $selectedSchoolId]) }}" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">+ Add Budget Allocation</a>@endif
        <a href="{{ route('budget.allocation.report', ['type' => 'annual'] + $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
        <a href="{{ route('budget.allocation.report', ['type' => 'annual'] + $query + ['print' => 1]) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Print Report</a>
        @if($canManage)
            <form method="POST" action="{{ route('budget.allocation.close') }}" onsubmit="return confirm('Close the FY {{ $year }} budget period? Closed lines can no longer be edited or take new obligations.')">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                <button class="rounded border border-error/40 bg-white px-4 py-2.5 text-xs font-semibold text-error hover:bg-error/5">Close Budget Period</button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search account, office, program" class="{{ $field }} w-64">
    <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="{{ $field }} w-24" aria-label="Budget year">
    @if($schools->count() > 1)
        <select name="school_id" class="{{ $field }}"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select>
    @endif
    <select name="fund" class="{{ $field }}"><option value="">All fund sources</option>@foreach($funds as $fund)<option @selected(($filters['fund'] ?? '') === $fund)>{{ $fund }}</option>@endforeach</select>
    <select name="office" class="{{ $field }}"><option value="">All offices</option>@foreach($offices as $office)<option @selected(($filters['office'] ?? '') === $office)>{{ $office }}</option>@endforeach</select>
    <select name="quarter" class="{{ $field }}"><option value="">All quarters</option>@foreach([1, 2, 3, 4] as $q)<option value="{{ $q }}" @selected($quarter === $q)>Q{{ $q }}</option>@endforeach</select>
    <button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Filter</button>
    @if(array_filter($filters))<a href="{{ route('budget.allocation', ['year' => $year]) }}" class="text-xs font-semibold text-primary hover:underline">Clear</a>@endif
</form>

@php $cards = [['Total Annual Allocation', $peso($t['allocated']), ''], ['Total Obligated', $peso($t['obligated']), ''], ['Total Liquidated', $peso($t['liquidated']), 'text-secondary'], ['Total Remaining Balance', $peso($t['balance']), $t['balance'] < 0 ? 'text-error' : 'text-secondary'], ['Fully Obligated Items', $counts['fully'], $counts['fully'] ? 'text-error' : ''], ['Near Limit Items', $counts['near'], $counts['near'] ? 'text-amber-700' : ''], ['Available Budget Items', $counts['available'], 'text-secondary']]; @endphp
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-7">
    @foreach($cards as [$label, $value, $tone])
        <article class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-2 text-xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p></article>
    @endforeach
</div>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/30 px-5 py-4">
        <h2 class="text-lg font-semibold">Budget Ledger · FY {{ $year }}</h2>
        <p class="mt-1 text-xs text-on-surface-variant">Remaining Balance = Annual Allocation − Obligated. Unliquidated = Obligated − Liquidated. Near Limit means more than 80% obligated.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1500px] text-left text-sm">
            <thead class="bg-surface-low">
                <tr>
                    <th class="{{ $th }}">Year</th><th class="{{ $th }}">Office / Department</th><th class="{{ $th }}">Fund Source</th><th class="{{ $th }}">Account Code</th><th class="{{ $th }}">Expense Item</th>
                    <th class="{{ $th }} text-right">Annual Allocation</th><th class="{{ $th }} text-right">Q1</th><th class="{{ $th }} text-right">Q2</th><th class="{{ $th }} text-right">Q3</th><th class="{{ $th }} text-right">Q4</th>
                    <th class="{{ $th }} text-right">Obligated</th><th class="{{ $th }} text-right">Liquidated</th><th class="{{ $th }} text-right">Unliquidated</th><th class="{{ $th }} text-right">Remaining</th>
                    @if($quarter)<th class="{{ $th }} text-right">Q{{ $quarter }} Obligated</th><th class="{{ $th }} text-right">Q{{ $quarter }} Available</th>@endif
                    <th class="{{ $th }}">Status</th><th class="{{ $th }} text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($rows as $r)
                    @php $i = $r['item']; $qv = $quarter ? $r['quarters'][$quarter] : null; @endphp
                    <tr class="align-top">
                        <td class="px-3 py-3">{{ $i->fiscal_year }}</td>
                        <td class="px-3 py-3">{{ $i->office }}@if(!$selectedSchoolId)<span class="block text-[11px] text-on-surface-variant">{{ $i->school?->name }}</span>@endif</td>
                        <td class="px-3 py-3">{{ $i->source_of_fund }}</td>
                        <td class="px-3 py-3 tabular-nums">{{ $i->uacs_code }}</td>
                        <td class="px-3 py-3 font-semibold">{{ $i->particulars }}@if($i->program)<span class="block text-xs font-normal text-on-surface-variant">{{ $i->program }}</span>@endif</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $peso($r['allocated']) }}</td>
                        @foreach([1, 2, 3, 4] as $q)<td class="px-3 py-3 text-right tabular-nums {{ $quarter === $q ? 'bg-primary/5 font-semibold' : '' }}">{{ $peso($r['quarters'][$q]['allocated']) }}</td>@endforeach
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($r['obligated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($r['liquidated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($r['unliquidated']) }}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums {{ $r['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($r['balance']) }}</td>
                        @if($quarter)<td class="px-3 py-3 text-right tabular-nums">{{ $peso($qv['obligated']) }}</td><td class="px-3 py-3 text-right tabular-nums {{ $qv['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($qv['balance']) }}</td>@endif
                        <td class="px-3 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold {{ $badge[$r['status']] }}">{{ $r['status'] }}</span>@foreach($r['warnings'] as $w)<span class="mt-1 block max-w-[200px] text-[11px] font-semibold text-error">⚠ {{ $w }}</span>@endforeach</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">
                            @php $icon = 'inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container'; @endphp
                            <a href="{{ route('budget.allocation.show', $i) }}" title="View" aria-label="View" class="{{ $icon }} text-primary"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                            @if($canManage && !$i->closed_at)<a href="{{ route('budget.allocation.edit', $i) }}" title="Edit" aria-label="Edit" class="{{ $icon }} text-primary"><span class="material-symbols-outlined text-[20px]">edit</span></a>@endif
                            <a href="{{ route('budget.allocation.show', [$i, 'print' => 1]) }}" target="_blank" rel="noopener" title="Print" aria-label="Print" class="{{ $icon }} text-on-surface-variant"><span class="material-symbols-outlined text-[20px]">print</span></a>
                            @if($canManage && !$i->closed_at)<form method="POST" action="{{ route('budget.allocation.destroy', $i) }}" class="inline" onsubmit="return confirm('Delete this budget line?')">@csrf @method('DELETE')<button title="Delete" aria-label="Delete" class="{{ $icon }} text-error"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="18" class="px-4 py-12 text-center text-on-surface-variant">No budget allocations match. @if($canManage)Click “Add Budget Allocation” to start.@endif</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="border-t-2 border-outline-variant/40 bg-surface-low/60 font-bold">
                    <tr>
                        <td class="px-3 py-3" colspan="5">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['allocated']) }}</td>
                        @foreach($summary['quarters'] as $v)<td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['allocated']) }}</td>@endforeach
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['obligated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['liquidated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['unliquidated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['balance']) }}</td>
                        @if($quarter)<td class="px-3 py-3 text-right tabular-nums">{{ $peso($summary['quarters'][$quarter]['obligated']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $peso($summary['quarters'][$quarter]['balance']) }}</td>@endif
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>

<details class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <summary class="cursor-pointer px-5 py-4 text-base font-semibold">Quarterly breakdown (allocation, obligated, liquidated, balance per quarter)</summary>
    <div class="overflow-x-auto border-t border-outline-variant/30">
        <table class="w-full min-w-[1800px] text-left text-sm">
            <thead class="bg-surface-low">
                <tr>
                    <th class="{{ $th }}" rowspan="2">Expense Item</th><th class="{{ $th }}" rowspan="2">Account Code</th>
                    @foreach([1, 2, 3, 4] as $q)<th class="{{ $th }} border-l border-outline-variant/30 text-center" colspan="4">Q{{ $q }}</th>@endforeach
                </tr>
                <tr>@foreach([1, 2, 3, 4] as $q)<th class="{{ $th }} border-l border-outline-variant/30 text-right">Alloc.</th><th class="{{ $th }} text-right">Oblig.</th><th class="{{ $th }} text-right">Liquid.</th><th class="{{ $th }} text-right">Available</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                @foreach($rows as $r)
                    <tr><td class="px-3 py-2.5 font-semibold">{{ $r['item']->particulars }}<span class="block text-[11px] font-normal text-on-surface-variant">{{ $r['item']->office }} · {{ $r['item']->source_of_fund }}</span></td><td class="px-3 py-2.5 tabular-nums">{{ $r['item']->uacs_code }}</td>
                        @foreach($r['quarters'] as $v)<td class="border-l border-outline-variant/30 px-3 py-2.5 text-right tabular-nums">{{ $peso($v['allocated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($v['obligated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($v['liquidated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums {{ $v['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($v['balance']) }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>

<section class="rounded border border-outline-variant/30 bg-white p-5">
    <h2 class="text-base font-semibold">Reports</h2>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach($reports as $key => $label)
            <a href="{{ route('budget.allocation.report', ['type' => $key] + $query) }}" class="rounded border border-outline-variant/60 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">{{ $label }}</a>
        @endforeach
    </div>
</section>
@endsection
