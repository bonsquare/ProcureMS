@extends('layouts.budget')
@section('title', 'Budget Item')
@section('content')
@if(request('print'))
<style>aside, header.fixed { display: none !important; } .md\:pl-72 { padding-left: 0 !important; } main { padding-top: 24px !important; background: #fff !important; } body { background: #fff !important; }</style>
@endif
<style>@media print { aside, header, .no-print { display: none !important; } .md\:pl-72 { padding-left: 0 !important; } main { padding: 0 !important; } }</style>
@php $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant'; @endphp
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $item->particulars }}</h1>
        <p class="mt-1 text-[15px] text-on-surface-variant">{{ $item->budget_ref_no }} · {{ $item->uacs_code }} · {{ $item->source_of_fund }} · FY {{ $item->fiscal_year }} · {{ $item->office }}@if($item->responsibility_center) · RC {{ $item->responsibility_center }}@endif</p>
    </div>
    <div class="no-print flex gap-2">
        <button onclick="window.print()" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Print</button>
        @if(auth()->user()->canManageBudget() && !$item->closed_at)<a href="{{ route('budget.allocation.edit', $item) }}" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Edit Budget Allocation</a>@endif
        @if(request('print'))<button type="button" onclick="window.close(); setTimeout(function () { if (!window.closed) window.location.href = '{{ route('budget.allocation', ['year' => $item->fiscal_year, 'school_id' => $item->school_id]) }}'; }, 250)" class="rounded border border-primary bg-white px-4 py-2.5 text-xs font-semibold text-primary hover:bg-surface-low">Close</button>@else<a href="{{ route('budget.allocation', ['year' => $item->fiscal_year, 'school_id' => $item->school_id]) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">← Back</a>@endif
    </div>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
    @foreach([['Annual Allocation', $row['allocated']], ['Obligated', $row['obligated']], ['Liquidated', $row['liquidated']], ['Remaining Balance', $row['balance']], ['Unliquidated', $row['unliquidated']]] as [$label, $value])
        <article class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p><p class="mt-2 text-xl font-semibold tabular-nums {{ $value < 0 ? 'text-error' : '' }}">{{ $peso($value) }}</p></article>
    @endforeach
</div>
<p class="mb-4 text-sm"><span class="font-semibold">Status:</span> {{ $row['status'] }} @foreach($row['warnings'] as $w)<span class="ml-3 font-semibold text-error">⚠ {{ $w }}</span>@endforeach</p>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white">
    <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">By Quarter</h2>
    <table class="w-full text-left text-sm"><thead class="bg-surface-low"><tr><th class="{{ $th }}">Quarter</th><th class="{{ $th }} text-right">Allocation</th><th class="{{ $th }} text-right">Obligated</th><th class="{{ $th }} text-right">Liquidated</th><th class="{{ $th }} text-right">Balance</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20">@foreach($row['quarters'] as $q => $v)<tr><td class="px-4 py-3 font-semibold">Q{{ $q }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($v['allocated']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($v['obligated']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($v['liquidated']) }}</td><td class="px-4 py-3 text-right tabular-nums {{ $v['balance'] < 0 ? 'text-error' : '' }}">{{ $peso($v['balance']) }}</td></tr>@endforeach</tbody>
    </table>
</section>

<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Obligation &amp; Liquidation Transactions</h2>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-surface-low"><tr><th class="{{ $th }}">Date</th><th class="{{ $th }}">Type</th><th class="{{ $th }}">Reference</th><th class="{{ $th }}">Description</th><th class="{{ $th }} text-right">Obligated</th><th class="{{ $th }} text-right">Liquidated</th><th class="{{ $th }}">Status</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20">
            @forelse($transactions as $t)<tr><td class="px-4 py-3">{{ $t['date']->format('M d, Y') }}</td><td class="px-4 py-3">{{ $t['type'] }}</td><td class="px-4 py-3"><a href="{{ $t['url'] }}" class="font-semibold text-primary hover:underline">{{ $t['ref'] }}</a></td><td class="px-4 py-3">{{ $t['description'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($t['obligated']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $peso($t['liquidated']) }}</td><td class="px-4 py-3">{{ \Illuminate\Support\Str::headline($t['status']) }}</td></tr>
            @empty<tr><td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">No transactions yet. Choose this item as the Budget Item on a PR or ORS to charge it.</td></tr>@endforelse
        </tbody></table></div>
</section>
@if($item->description || $item->remarks || $item->program)
    <section class="mt-6 rounded border border-outline-variant/30 bg-white p-5 text-sm"><dl class="grid grid-cols-1 gap-3 md:grid-cols-3"><div><dt class="text-xs font-semibold uppercase text-on-surface-variant">Program</dt><dd>{{ $item->program ?: '—' }}</dd></div><div><dt class="text-xs font-semibold uppercase text-on-surface-variant">Description</dt><dd>{{ $item->description ?: '—' }}</dd></div><div><dt class="text-xs font-semibold uppercase text-on-surface-variant">Remarks</dt><dd>{{ $item->remarks ?: '—' }}</dd></div></dl></section>
@endif
@if(request('print'))@push('scripts')<script>window.addEventListener('load', () => window.print());</script>@endpush @endif
@endsection
