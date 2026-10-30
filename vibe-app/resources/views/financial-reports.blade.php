@extends('layouts.budget')
@section('title', 'Financial Reports')
@section('crumb', 'Financial Reports')
@section('content')
@php
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $num = fn ($value) => number_format((float) $value, 2);
    // A balance is positive on the account's own side (assets and expenses debit; liabilities, equity and revenue credit) and negative on the other.
    $side = fn ($code) => in_array(substr((string) $code, 0, 1), ['2', '3', '4'], true) ? -1 : 1;
    $bal = fn ($value) => round((float) $value, 2) < 0 ? '('.number_format(abs((float) $value), 2).')' : number_format((float) $value, 2);
    $query = fn (array $extra = []) => array_merge(['report' => $report, 'school_id' => $schoolId, 'fund_cluster' => $fund, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'account' => $account], $extra);
    $needsAccount = in_array($report, ['gl', 'sl'], true);
@endphp
<style>@media print { aside, header, nav, .no-print { display: none !important; } main { margin: 0 !important; padding: 0 !important; } }</style>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Financial Reports</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Built from approved disbursement vouchers, cash fund receipts and the opening balances below.</p>
    </div>
    <button type="button" onclick="window.print()" class="no-print flex items-center gap-2 rounded-lg border border-outline-variant/60 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]">print</span>Print</button>
</div>

@if(session('success'))<div class="no-print mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="no-print mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<div class="no-print mb-4 flex flex-wrap gap-2">
    @foreach($reports as $key => $label)
        <a href="{{ route('financial-reports', $query(['report' => $key])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $report === $key ? 'bg-primary text-white' : 'border border-outline-variant/60 bg-white hover:bg-surface-low' }}">{{ $label }}</a>
    @endforeach
</div>

<form method="GET" action="{{ route('financial-reports') }}" class="no-print mb-6 grid items-end gap-3 rounded border border-outline-variant/30 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
    <input type="hidden" name="report" value="{{ $report }}">
    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">School
        <select name="school_id" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal normal-case tracking-normal outline-none focus:border-primary">@foreach($schools as $school)<option value="{{ $school->id }}" @selected($school->id === $schoolId)>{{ $school->name }}</option>@endforeach</select></label>
    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Fund cluster
        <select name="fund_cluster" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal normal-case tracking-normal outline-none focus:border-primary"><option value="">All funds</option>@foreach($funds as $name)<option @selected($name === $fund)>{{ $name }}</option>@endforeach</select></label>
    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">From
        <input type="date" name="from" value="{{ $from->toDateString() }}" @disabled($report === 'bs') class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $report === 'bs' ? 'As of' : 'To' }}
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
    @if($needsAccount)
        <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Account
            <select name="account" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal normal-case tracking-normal outline-none focus:border-primary"><option value="">Choose an account</option>@foreach($accounts as $code => $title)<option value="{{ $code }}" @selected((string) $code === (string) $account)>{{ $code }} · {{ $title }}</option>@endforeach</select></label>
    @endif
    <button class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-container">Show</button>
</form>
@if($report !== 'bs')
    <div class="no-print -mt-3 mb-6 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
        <span>Quarter {{ $from->year }}</span>
        @foreach(['1st', '2nd', '3rd', '4th'] as $index => $name)
            @php($start = \Carbon\Carbon::create($from->year, $index * 3 + 1, 1))
            @php($isCurrent = $from->toDateString() === $start->toDateString() && $to->toDateString() === $start->copy()->addMonths(3)->subDay()->toDateString())
            <a href="{{ route('financial-reports', $query(['from' => $start->toDateString(), 'to' => $start->copy()->addMonths(3)->subDay()->toDateString()])) }}" class="rounded-lg px-3 py-1.5 normal-case tracking-normal {{ $isCurrent ? 'bg-primary text-white' : 'border border-outline-variant/60 bg-white hover:bg-surface-low' }}">{{ $name }} qrtr.</a>
        @endforeach
    </div>
@endif

<h2 class="mb-1 text-lg font-semibold">{{ $reports[$report] }}</h2>
<p class="mb-3 text-sm text-on-surface-variant">{{ $schools->firstWhere('id', $schoolId)?->name }} · {{ $fund ?: 'All funds' }} · {{ $report === 'bs' ? 'as of '.$to->format('F j, Y') : $from->format('F j, Y').' to '.$to->format('F j, Y') }}</p>

@if($report === 'tb')
    @unless($data['balanced'])<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">Out of balance: total debits {{ $num($data['totals']['debit']) }} and total credits {{ $num($data['totals']['credit']) }} differ. Check the DV journal entries.</div>@endunless
    <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">Code</th><th class="{{ $th }}">Account</th><th class="{{ $th }} text-right">B. Opening</th><th class="{{ $th }} text-right">Debit</th><th class="{{ $th }} text-right">Credit</th><th class="{{ $th }} text-right">Ending</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($data['rows'] as $row)
                    <tr><td class="px-4 py-2">{{ $row['account_code'] }}</td><td class="px-4 py-2">{{ $row['account_title'] }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $bal(($row['opening_debit'] - $row['opening_credit']) * $side($row['account_code'])) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['period_debit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['period_credit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $bal(($row['ending_debit'] - $row['ending_credit']) * $side($row['account_code'])) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-on-surface-variant">No entries for this period.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-surface-low font-semibold"><tr><td colspan="2" class="px-4 py-3 text-right">Total</td><td class="px-4 py-3"></td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['rows']->sum('period_debit')) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['rows']->sum('period_credit')) }}</td><td class="px-4 py-3"></td></tr></tfoot>
        </table>
    </section>

    <section class="no-print mt-8 rounded border border-outline-variant/30 bg-white p-4">
        <h3 class="text-base font-semibold">Opening balances</h3>
        <p class="mb-3 text-sm text-on-surface-variant">The balance of each account when the books start. The books must start balanced: total debits equal total credits.</p>
        @if($canManage)
            <form method="POST" action="{{ route('financial-reports.opening.store') }}" class="mb-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-6">
                @csrf
                <input type="hidden" name="school_id" value="{{ $schoolId }}">
                <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">As of<input type="date" name="as_of" value="{{ old('as_of', $from->toDateString()) }}" required class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Fund cluster<input name="fund_cluster" value="{{ old('fund_cluster', $fund) }}" placeholder="General Fund" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Account code<input name="account_code" value="{{ old('account_code') }}" required placeholder="1010202024" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Debit<input type="number" step="0.01" min="0" name="debit" value="{{ old('debit') }}" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Credit<input type="number" step="0.01" min="0" name="credit" value="{{ old('credit') }}" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <button class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-container">Add</button>
            </form>
        @endif
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">As of</th><th class="{{ $th }}">Fund</th><th class="{{ $th }}">Account</th><th class="{{ $th }} text-right">Debit</th><th class="{{ $th }} text-right">Credit</th><th class="{{ $th }}"></th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($openings as $opening)
                    <tr><td class="px-4 py-2">{{ $opening->as_of->format('Y-m-d') }}</td><td class="px-4 py-2">{{ $opening->fund_cluster }}</td><td class="px-4 py-2">{{ $opening->account_code }} · {{ $opening->account_title }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($opening->debit) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($opening->credit) }}</td>
                        <td class="px-4 py-2 text-right">@if($canManage)<form method="POST" action="{{ route('financial-reports.opening.destroy', $opening) }}" onsubmit="return confirm('Delete this opening balance?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-error hover:underline">Delete</button></form>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-on-surface-variant">No opening balances yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@elseif($report === 'gl')
    @if(! $data)
        <p class="rounded border border-outline-variant/30 bg-white px-4 py-6 text-center text-on-surface-variant">Choose an account above to see its ledger.</p>
    @else
        <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-low"><tr><th class="{{ $th }}">Date</th><th class="{{ $th }}">Reference</th><th class="{{ $th }}">Particulars</th><th class="{{ $th }} text-right">Debit</th><th class="{{ $th }} text-right">Credit</th><th class="{{ $th }} text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-outline-variant/20">
                    <tr class="bg-surface-low/60"><td colspan="5" class="px-4 py-2 font-semibold">Balance before {{ $from->format('Y-m-d') }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($data['opening']) }}</td></tr>
                    @foreach($data['rows'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['date'] }}</td><td class="px-4 py-2">{{ $row['ref'] }}</td><td class="px-4 py-2">{{ $row['particulars'] }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['debit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['credit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['balance']) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface-low font-semibold"><tr><td colspan="5" class="px-4 py-3 text-right">Closing balance</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['closing']) }}</td></tr></tfoot>
            </table>
        </section>
    @endif
@elseif($report === 'sl')
    @if($data === null)
        <p class="rounded border border-outline-variant/30 bg-white px-4 py-6 text-center text-on-surface-variant">Choose an account above to see its subsidiary ledger.</p>
    @else
        <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-low"><tr><th class="{{ $th }}">Sub-account (payee, fund or breakdown)</th><th class="{{ $th }} text-right">Debit</th><th class="{{ $th }} text-right">Credit</th><th class="{{ $th }} text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-outline-variant/20">
                    @forelse($data as $row)
                        <tr><td class="px-4 py-2">{{ $row['sub'] }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['debit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['credit']) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($row['balance']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">No entries for this account.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif
@elseif($report === 'is')
    <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
        <table class="w-full text-left text-sm">
            <tbody class="divide-y divide-outline-variant/20">
                @foreach(['Revenue' => ['revenue', 'totalRevenue'], 'Expenses' => ['expenses', 'totalExpenses']] as $title => [$groups, $total])
                    <tr class="bg-surface-low"><td colspan="2" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-primary">{{ $title }}</td></tr>
                    @foreach($data[$groups] as $category => $rows)
                        <tr><td colspan="2" class="px-4 py-2 font-semibold">{{ $category }}</td></tr>
                        @foreach($rows as $row)<tr><td class="px-4 py-1.5 pl-8">{{ $row['account_code'] }} · {{ $row['account_title'] }}</td><td class="px-4 py-1.5 text-right tabular-nums">{{ $num($row['amount']) }}</td></tr>@endforeach
                    @endforeach
                    <tr class="font-semibold"><td class="px-4 py-2 text-right">Total {{ strtolower($title) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($data[$total]) }}</td></tr>
                @endforeach
                <tr class="bg-surface-low font-bold"><td class="px-4 py-3 text-right">Surplus / (Deficit)</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['surplus']) }}</td></tr>
            </tbody>
        </table>
    </section>
@elseif($report === 'cf')
    @unless($data['balanced'])<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">Out of balance: the ending cash {{ $num($data['ending']) }} differs from the cash accounts in the books. Check the opening balances and DV journal entries.</div>@endunless
    <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
        <table class="w-full text-left text-sm">
            <tbody class="divide-y divide-outline-variant/20">
                @foreach($data['sections'] as $title => $rows)
                    <tr class="bg-surface-low"><td colspan="2" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-primary">{{ $title }}</td></tr>
                    @forelse($rows as $row)<tr><td class="px-4 py-1.5 pl-8">{{ $row['account_code'] }} · {{ $row['account_title'] }}</td><td class="px-4 py-1.5 text-right tabular-nums">{{ $num($row['amount']) }}</td></tr>@empty<tr><td colspan="2" class="px-4 py-1.5 pl-8 text-on-surface-variant">None in this period.</td></tr>@endforelse
                    <tr class="font-semibold"><td class="px-4 py-2 text-right">Total {{ strtolower($title) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($data['totals'][$title]) }}</td></tr>
                @endforeach
                <tr class="bg-surface-low font-bold"><td class="px-4 py-3 text-right">Net increase / (decrease) in cash</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['net']) }}</td></tr>
                <tr class="font-semibold"><td class="px-4 py-2 text-right">Cash at the beginning</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($data['beginning']) }}</td></tr>
                <tr class="bg-surface-low font-bold"><td class="px-4 py-3 text-right">Cash at the end</td><td class="px-4 py-3 text-right tabular-nums">{{ $num($data['ending']) }}</td></tr>
            </tbody>
        </table>
    </section>
@else
    @unless($data['balanced'])<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">Out of balance: assets {{ $num($data['totalAssets']) }} differ from liabilities plus equity {{ $num($data['totalLiabilities'] + $data['totalEquity']) }}. Check the opening balances and DV journal entries.</div>@endunless
    <section class="overflow-x-auto rounded border border-outline-variant/30 bg-white">
        <table class="w-full text-left text-sm">
            <tbody class="divide-y divide-outline-variant/20">
                @foreach(['Assets' => ['assets', 'totalAssets'], 'Liabilities' => ['liabilities', 'totalLiabilities'], 'Equity' => ['equity', 'totalEquity']] as $title => [$rowsKey, $total])
                    <tr class="bg-surface-low"><td colspan="2" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-primary">{{ $title }}</td></tr>
                    @foreach($data[$rowsKey] as $row)<tr><td class="px-4 py-1.5 pl-8">{{ $row['account_code'] }} {{ $row['account_code'] ? '·' : '' }} {{ $row['account_title'] }}</td><td class="px-4 py-1.5 text-right tabular-nums">{{ $num($row['amount']) }}</td></tr>@endforeach
                    <tr class="font-semibold"><td class="px-4 py-2 text-right">Total {{ strtolower($title) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ $num($data[$total]) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
@endsection
