@extends('layouts.budget')
@section('title', 'Cash Summaries')
@section('crumb', 'Cash Summaries')
@section('content')
@php
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $num = fn ($value) => number_format((float) $value, 2);
    $box = 'mt-1.5 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm text-on-surface shadow-sm';
@endphp
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Cash Summaries</h1>
        <p class="mt-1 text-[15px] text-on-surface-variant">Cash position, tax withheld and remitted, advances, and paid DVs by payee, from the journals and the DVs. Read-only. <a href="{{ route('cash', ['tab' => 'reports']) }}" class="font-semibold text-primary hover:underline">Back to Cash Disbursement Reports</a></p>
    </div>
</div>
<form method="GET" action="{{ route('cash.summaries') }}" class="mb-6 grid grid-cols-1 gap-3 rounded border border-outline-variant/30 bg-white p-4 md:grid-cols-4">
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">School<select name="school_id" class="{{ $box }}">@foreach($schools as $option)<option value="{{ $option->id }}" @selected($option->id === $school->id)>{{ $option->name }}</option>@endforeach</select></label>
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">From<input type="date" name="from" value="{{ $from->toDateString() }}" class="{{ $box }}"></label>
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">To<input type="date" name="to" value="{{ $to->toDateString() }}" class="{{ $box }}"></label>
    <div class="flex items-end"><button class="w-full rounded bg-primary px-4 py-2 text-sm font-semibold text-white">Show</button></div>
</form>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white" aria-labelledby="sum-position">
    <h2 id="sum-position" class="border-b border-outline-variant/30 px-5 py-3 text-lg font-semibold">Cash position · Cash-MDS 1010404000</h2>
    <dl class="grid grid-cols-2 gap-4 p-5 md:grid-cols-4">
        @foreach([['Beginning balance', $position['opening']], ['Cash received (advances, NCA)', $position['received']], ['Cash paid out', $position['paid']], ['Ending balance', $position['ending']]] as [$label, $value])
            <div><dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</dt><dd class="mt-2 text-xl font-semibold tabular-nums">₱{{ $num($value) }}</dd></div>
        @endforeach
    </dl>
</section>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white" aria-labelledby="sum-tax">
    <h2 id="sum-tax" class="border-b border-outline-variant/30 px-5 py-3 text-lg font-semibold">Tax withheld and remitted · as of {{ $to->format('M d, Y') }}</h2>
    <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm">
        <thead class="bg-surface-low"><tr><th class="{{ $th }}">DV</th><th class="{{ $th }}">DV date</th><th class="{{ $th }}">Payee</th><th class="{{ $th }} text-right">Withheld</th><th class="{{ $th }} text-right">Remitted</th><th class="{{ $th }} text-right">Still to remit</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/30">
            @forelse($tax as $row)<tr><td class="px-4 py-3 font-semibold">{{ $row['dv'] }}</td><td class="px-4 py-3">{{ $row['date']->format('M d, Y') }}</td><td class="px-4 py-3">{{ $row['payee'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['withheld']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['remitted']) }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $num($row['unremitted']) }}</td></tr>
            @empty<tr><td colspan="6" class="px-4 py-6 text-center text-on-surface-variant">No DV withheld tax up to this date.</td></tr>@endforelse
        </tbody>
        @if($tax->isNotEmpty())<tfoot class="border-t-2 border-outline-variant/40 bg-surface-low font-semibold"><tr><td class="px-4 py-3" colspan="3">Total</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($tax->sum('withheld')) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($tax->sum('remitted')) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($tax->sum('unremitted')) }}</td></tr></tfoot>@endif
    </table></div>
</section>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white" aria-labelledby="sum-advances">
    <h2 id="sum-advances" class="border-b border-outline-variant/30 px-5 py-3 text-lg font-semibold">Advances · Advances for Operating Expenses 1990101000 · as of {{ $to->format('M d, Y') }}</h2>
    <dl class="grid grid-cols-1 gap-4 border-b border-outline-variant/30 p-5 md:grid-cols-3">
        @foreach([['Received', $advances['received']], ['Liquidated (cleared from the books)', $advances['liquidated']], ['Still owed (unliquidated)', $advances['outstanding']]] as [$label, $value])
            <div><dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</dt><dd class="mt-2 text-xl font-semibold tabular-nums">₱{{ $num($value) }}</dd></div>
        @endforeach
    </dl>
    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm">
        <thead class="bg-surface-low"><tr><th class="{{ $th }}">DV</th><th class="{{ $th }}">DV date</th><th class="{{ $th }}">Particulars</th><th class="{{ $th }} text-right">Received</th><th class="{{ $th }} text-right">Liquidated</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/30">
            @forelse($advances['rows'] as $row)<tr><td class="px-4 py-3 font-semibold">{{ $row['dv'] }}</td><td class="px-4 py-3">{{ $row['date']->format('M d, Y') }}</td><td class="px-4 py-3">{{ $row['particulars'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['received']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['liquidated']) }}</td></tr>
            @empty<tr><td colspan="5" class="px-4 py-6 text-center text-on-surface-variant">No advance recorded by a non-cash DV up to this date.</td></tr>@endforelse
        </tbody>
    </table></div>
</section>

<section class="mb-6 overflow-hidden rounded border border-outline-variant/30 bg-white" aria-labelledby="sum-payees">
    <h2 id="sum-payees" class="border-b border-outline-variant/30 px-5 py-3 text-lg font-semibold">Paid DVs by payee · {{ $from->format('M d, Y') }} to {{ $to->format('M d, Y') }}</h2>
    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm">
        <thead class="bg-surface-low"><tr><th class="{{ $th }}">Payee</th><th class="{{ $th }} text-right">DVs</th><th class="{{ $th }} text-right">Gross</th><th class="{{ $th }} text-right">Tax withheld</th><th class="{{ $th }} text-right">Cash paid</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/30">
            @forelse($payees as $row)<tr><td class="px-4 py-3 font-semibold">{{ $row['payee'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $row['count'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['gross']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($row['tax']) }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $num($row['net']) }}</td></tr>
            @empty<tr><td colspan="5" class="px-4 py-6 text-center text-on-surface-variant">No DV paid in this period.</td></tr>@endforelse
        </tbody>
        @if($payees->isNotEmpty())<tfoot class="border-t-2 border-outline-variant/40 bg-surface-low font-semibold"><tr><td class="px-4 py-3">Total</td><td class="px-4 py-3 text-right tabular-nums">{{ $payeeTotals['count'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($payeeTotals['gross']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($payeeTotals['tax']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($payeeTotals['net']) }}</td></tr></tfoot>@endif
    </table></div>
</section>
@endsection
