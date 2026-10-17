<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Disbursement Register · {{ $report['school']->name }}</title>
@include('partials.cash-report-styles')
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.print-pages')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $data = $report['data'];
    $school = $report['school'];
    $accounts = $data['accounts'];
    // Eight expense accounts fit beside the working fund; any more continue in a table below with the same rows.
    $accountGroups = array_chunk($accounts, 8, true);
    if ($accountGroups === []) {
        $accountGroups = [[]];
    }
    // Money received and the payments of the period in date order (money first on the same day), with the running balance of the working fund.
    $entries = $data['receipts']->map(fn ($receipt) => ['type' => 'receipt', 'date' => $receipt->received_at, 'receipt' => $receipt])
        ->concat($data['lines']->map(fn (array $line) => ['type' => 'line', 'date' => $line['date'], 'line' => $line]))
        ->sortBy(fn (array $entry) => $entry['date']->format('Ymd').($entry['type'] === 'receipt' ? '0' : '1'))
        ->values();
    $balance = $data['opening'];
    $rows = [];
    foreach ($entries as $entry) {
        if ($entry['type'] === 'receipt') {
            $balance = round($balance + (float) $entry['receipt']->amount, 2);
            $rows[] = ['date' => $entry['date'], 'reference' => $entry['receipt']->reference, 'particulars' => $entry['receipt']->kindLabel(), 'advance' => (float) $entry['receipt']->amount, 'payment' => null, 'balance' => $balance, 'code' => null];
        } else {
            $line = $entry['line'];
            $balance = round($balance - ($line['amount'] ?? 0), 2);
            $rows[] = ['date' => $entry['date'], 'reference' => $line['check_no'], 'particulars' => $line['payee'], 'advance' => null, 'payment' => $line['amount'], 'balance' => $balance, 'code' => $line['cancelled'] ? null : $line['uacs_code']];
        }
    }
    $accountTotals = collect($rows)->filter(fn (array $row) => $row['code'] !== null && $row['payment'] !== null)->groupBy('code')->map(fn ($group) => round($group->sum('payment'), 2));
    $totalPayments = collect($rows)->sum(fn (array $row) => $row['payment'] ?? 0);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $school->id]), 'context' => 'Cash Disbursement Register · '.$report['period']])

<main class="paper" data-official-page data-sheets data-doc="cash-register" data-paper="longbond" data-orientation="landscape" data-margin="10">
    @include('partials.cash-report-header', ['title' => 'CASH DISBURSEMENT REGISTER', 'showOfficer' => true])

    @foreach($accountGroups as $groupIndex => $group)
        @php $first = $groupIndex === 0; @endphp
        @if(! $first)<p style="margin:14px 0 4px;font-weight:bold">Breakdown of payments, continued</p>@endif
        <table class="report{{ $first ? '' : ' continued' }}" data-paginate>
            <thead>
                <tr>
                    <th rowspan="3" style="width:8%">Date</th><th rowspan="3" style="width:9%">Reference</th>
                    @if($first)<th rowspan="3" style="width:20%">Particulars</th><th colspan="3">Working Fund</th>@endif
                    <th colspan="{{ max(count($group), 1) }}">Breakdown of Payments</th>
                </tr>
                <tr>
                    @if($first)<th rowspan="2">Cash Advance</th><th rowspan="2">Payments</th><th rowspan="2">Balance</th>@endif
                    @forelse($group as $code => $title)<th>{{ $title }}</th>@empty<th>&nbsp;</th>@endforelse
                </tr>
                <tr>@forelse($group as $code => $title)<th>{{ $code }}</th>@empty<th>&nbsp;</th>@endforelse</tr>
            </thead>
            <tbody>
                @if($first)
                    <tr class="muted"><td></td><td></td><td>Beginning Balance</td><td class="num" data-col="advance">{{ $money($data['opening']) }}</td><td></td><td class="num" data-col="balance">{{ $money($data['opening']) }}</td><td colspan="{{ max(count($group), 1) }}"></td></tr>
                @endif
                @foreach($rows as $row)
                    <tr>
                        <td class="center">{{ $row['date']->format('M d, Y') }}</td>
                        <td class="center">{{ $row['reference'] }}</td>
                        @if($first)
                            <td>{{ $row['particulars'] }}</td>
                            <td class="num" @if($row['advance'] !== null) data-col="advance" @endif>{{ $row['advance'] === null ? '' : $money($row['advance']) }}</td>
                            <td class="num">{{ $row['payment'] === null ? '' : $money($row['payment']) }}</td>
                            <td class="num" data-col="balance">{{ $money($row['balance']) }}</td>
                        @endif
                        @forelse($group as $code => $title)
                            <td class="num" data-account="{{ $code }}">{{ $row['code'] === (string) $code && $row['payment'] !== null ? $money($row['payment']) : '' }}</td>
                        @empty
                            <td></td>
                        @endforelse
                    </tr>
                @endforeach
                @if($rows === [])
                    <tr class="muted"><td colspan="{{ ($first ? 6 : 2) + max(count($group), 1) }}" class="center">No payment was made in this period.</td></tr>
                @endif
                <tr class="total">
                    <td colspan="{{ $first ? 4 : 2 }}" class="num">TOTAL</td>
                    @if($first)<td class="num" data-col="total-payments">{{ $money($totalPayments) }}</td><td></td>@endif
                    @forelse($group as $code => $title)<td class="num" data-total-account="{{ $code }}">{{ $money($accountTotals->get((string) $code, 0)) }}</td>@empty<td></td>@endforelse
                </tr>
            </tbody>
        </table>
    @endforeach

    @if($report['remarks'] !== '')<p style="margin:10px 0 0"><strong>Remarks:</strong> {{ $report['remarks'] }}</p>@endif

    <div class="sign-row">
        <div class="box"><div style="text-align:left;font-weight:bold">CERTIFIED CORRECT:</div><div class="name">{{ mb_strtoupper($report['officer']?->name ?? '') }}&nbsp;</div><div>{{ $report['officer']?->position }}</div><div style="margin-top:8px;text-align:left">Date: ______________________</div></div>
        <div class="box"><div style="text-align:left;font-weight:bold">RECEIVED BY:</div><div class="name">{{ mb_strtoupper($report['receivedBy']?->name ?? '') }}&nbsp;</div><div>{{ $report['receivedBy']?->position ?: '' }}&nbsp;</div><div style="margin-top:8px;text-align:left">Date: ______________________</div></div>
    </div>
</main>
</body>
</html>
