@extends('layouts.budget')
@section('title', 'Budget Allocation')
@section('content')
@php
    $t = $summary['totals'];
    $statusTone = ['Not Yet Used' => 'on-surface-variant', 'Partially Obligated' => 'primary', 'Fully Obligated' => 'primary', 'Partially Liquidated' => 'secondary', 'Fully Liquidated' => 'secondary', 'Over Budget' => 'error', 'Closed' => 'on-surface-variant'];
    $query = ['year' => $year, 'school_id' => $selectedSchoolId];
    $th = 'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
@endphp
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Budget Allocation</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Yearly operating budget per expense item, divided into four quarters. <a href="{{ route('budget', $query) }}" class="font-semibold text-primary hover:underline">Obligations &amp; ORS →</a></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="GET" class="flex gap-2">
            <select name="school_id" onchange="this.form.submit()" class="rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none">
                <option value="">All schools</option>
                @foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach
            </select>
            <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" onchange="this.form.submit()" class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none" aria-label="Fiscal year">
        </form>
        <a href="{{ route('budget.allocation.create', $query) }}" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">+ Add Budget Item</a>
        <a href="{{ route('budget.allocation.report', ['type' => 'annual'] + $query + ['print' => 1]) }}" target="_blank" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Print Budget Report</a>
        <a href="{{ route('budget.allocation.report', ['type' => 'annual'] + $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
        <a href="{{ route('budget.allocation.report', ['type' => 'annual'] + $query + ['print' => 1]) }}" target="_blank" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to PDF</a>
        <form method="POST" action="{{ route('budget.allocation.close') }}" onsubmit="return confirm('Close the FY {{ $year }} budget period? Closed items can no longer be edited or take new obligations.')">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
            <button class="rounded border border-error/40 bg-white px-4 py-2.5 text-xs font-semibold text-error hover:bg-error/5">Close Budget Period</button>
        </form>
    </div>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

@php $cards = [['Total Annual Budget', $peso($t['allocated']), ''], ['Total Obligated', $peso($t['obligated']), ''], ['Total Liquidated', $peso($t['liquidated']), 'text-secondary'], ['Total Remaining Balance', $peso($t['balance']), $t['balance'] < 0 ? 'text-error' : 'text-secondary'], ['Total Unliquidated', $peso($t['unliquidated']), ''], ['Budget Usage', $t['usage'] . '%', $t['usage'] > 100 ? 'text-error' : '']]; @endphp
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
    @foreach($cards as [$label, $value, $tone])
        <article class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-2 text-xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p></article>
    @endforeach
</div>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/30 px-5 py-4">
        <h2 class="text-lg font-semibold">Quarterly Breakdown · FY {{ $year }}</h2>
        <p class="mt-1 text-xs text-on-surface-variant">Balance = Allocation − Obligated. Obligated comes from PRs and ORS charged to the item; Liquidated from approved ORS.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[2400px] text-left text-sm">
            <thead class="bg-surface-low">
                <tr>
                    <th class="{{ $th }}" rowspan="2">Expense Item</th><th class="{{ $th }}" rowspan="2">Account Code</th><th class="{{ $th }}" rowspan="2">Fund Source</th><th class="{{ $th }} text-right" rowspan="2">Annual Allocation</th>
                    @foreach([1, 2, 3, 4] as $q)<th class="{{ $th }} border-l border-outline-variant/30 text-center" colspan="4">{{ ['1st', '2nd', '3rd', '4th'][$q - 1] }} Quarter</th>@endforeach
                    <th class="{{ $th }} border-l border-outline-variant/30 text-right" rowspan="2">Total Obligated</th><th class="{{ $th }} text-right" rowspan="2">Total Liquidated</th><th class="{{ $th }} text-right" rowspan="2">Annual Balance</th><th class="{{ $th }}" rowspan="2">Status</th><th class="{{ $th }}" rowspan="2"></th>
                </tr>
                <tr>
                    @foreach([1, 2, 3, 4] as $q)
                        <th class="{{ $th }} border-l border-outline-variant/30 text-right">Alloc.</th><th class="{{ $th }} text-right">Oblig.</th><th class="{{ $th }} text-right">Liquid.</th><th class="{{ $th }} text-right">Balance</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($rows as $r)
                    @php $i = $r['item']; @endphp
                    <tr class="align-top">
                        <td class="px-3 py-3 font-semibold">{{ $i->particulars }}
                            @if($i->program)<span class="block text-xs font-normal text-on-surface-variant">{{ $i->program }}</span>@endif
                            <span class="block text-[11px] font-normal text-on-surface-variant">{{ $i->budget_ref_no }}@if(!$selectedSchoolId) · {{ $i->school?->name }}@endif</span>
                        </td>
                        <td class="px-3 py-3 tabular-nums">{{ $i->uacs_code }}</td>
                        <td class="px-3 py-3">{{ $i->source_of_fund }}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $peso($r['allocated']) }}</td>
                        @foreach($r['quarters'] as $v)
                            <td class="border-l border-outline-variant/30 px-3 py-3 text-right tabular-nums">{{ $peso($v['allocated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['obligated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['liquidated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums {{ $v['balance'] < 0 ? 'font-semibold text-error' : '' }}">{{ $peso($v['balance']) }}</td>
                        @endforeach
                        <td class="border-l border-outline-variant/30 px-3 py-3 text-right tabular-nums">{{ $peso($r['obligated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($r['liquidated']) }}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums {{ $r['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($r['balance']) }}</td>
                        <td class="px-3 py-3">
                            <span class="inline-flex whitespace-nowrap rounded-full bg-{{ $statusTone[$r['status']] }}/10 px-2 py-1 text-xs font-semibold text-{{ $statusTone[$r['status']] }}">{{ $r['status'] }}</span>
                            @foreach($r['warnings'] as $w)<span class="mt-1 block max-w-[200px] text-[11px] font-semibold text-error">⚠ {{ $w }}</span>@endforeach
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold">
                            <a href="{{ route('budget.allocation.show', $i) }}" class="text-primary hover:underline">View</a>
                            @unless($i->closed_at)
                                · <a href="{{ route('budget.allocation.edit', $i) }}" class="text-primary hover:underline">Edit</a>
                                · <form method="POST" action="{{ route('budget.allocation.destroy', $i) }}" class="inline" onsubmit="return confirm('Remove this budget item?')">@csrf @method('DELETE')<button class="text-error hover:underline">Delete</button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="26" class="px-4 py-12 text-center text-on-surface-variant">No budget items for FY {{ $year }}. Click “Add Budget Item” to start.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="border-t-2 border-outline-variant/40 bg-surface-low/60 font-bold">
                    <tr>
                        <td class="px-3 py-3" colspan="3">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['allocated']) }}</td>
                        @foreach($summary['quarters'] as $v)
                            <td class="border-l border-outline-variant/30 px-3 py-3 text-right tabular-nums">{{ $peso($v['allocated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['obligated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['liquidated']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $peso($v['balance']) }}</td>
                        @endforeach
                        <td class="border-l border-outline-variant/30 px-3 py-3 text-right tabular-nums">{{ $peso($t['obligated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['liquidated']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $peso($t['balance']) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>

@php $groups = [['Per Quarter', $summary['quarters']->mapWithKeys(fn ($v, $q) => ['Q' . $q => $v])], ['Per Fund Source', $summary['funds']], ['Per Expense Category', $summary['categories']]]; @endphp
<div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
    @foreach($groups as [$heading, $set])
        <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
            <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">{{ $heading }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-low"><tr><th class="{{ $th }}"></th><th class="{{ $th }} text-right">Allocated</th><th class="{{ $th }} text-right">Obligated</th><th class="{{ $th }} text-right">Liquidated</th><th class="{{ $th }} text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        @forelse($set as $label => $v)
                            <tr><td class="px-3 py-2.5 font-semibold">{{ $label }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($v['allocated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($v['obligated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($v['liquidated']) }}</td><td class="px-3 py-2.5 text-right tabular-nums {{ $v['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($v['balance']) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-on-surface-variant">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>

<section class="rounded border border-outline-variant/30 bg-white p-5">
    <h2 class="text-base font-semibold">Reports</h2>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach($reports as $key => $label)
            <a href="{{ route('budget.allocation.report', ['type' => $key] + $query) }}" class="rounded border border-outline-variant/60 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">{{ $label }}</a>
        @endforeach
    </div>
</section>
@endsection
