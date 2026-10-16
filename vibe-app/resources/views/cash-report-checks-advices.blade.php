<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checks and Advices to Debit Account Disbursement Record · {{ $report['school']->name }}</title>
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
    // The receipts and the checks / ADAs of the period in date order (money received first on the same day), with the running balance.
    $entries = $data['receipts']->map(fn ($receipt) => ['type' => 'receipt', 'date' => $receipt->received_at, 'receipt' => $receipt])
        ->concat($data['lines']->map(fn (array $line) => ['type' => 'line', 'date' => $line['date'], 'line' => $line]))
        ->sortBy(fn (array $entry) => $entry['date']->format('Ymd').($entry['type'] === 'receipt' ? '0' : '1'))
        ->values();
    $balance = $data['opening'];
    $totalCheck = $data['lines']->where('mode', 'check')->sum(fn (array $line) => $line['amount'] ?? 0);
    $totalAda = $data['lines']->where('mode', 'ada')->sum(fn (array $line) => $line['amount'] ?? 0);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $school->id]), 'context' => 'Checks and Advices to Debit Account · '.$report['period']])

<main class="paper" data-official-page data-sheets data-doc="cash-checks-advices" data-paper="longbond" data-orientation="landscape" data-margin="10">
    @include('partials.cash-report-header', ['title' => 'CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD', 'showOfficer' => true])

    <table class="report" data-paginate>
        <colgroup><col style="width:9%"><col style="width:7%"><col style="width:8%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:14%"><col style="width:8%"><col style="width:10%"><col style="width:6%"><col style="width:6%"><col style="width:6%"><col style="width:7%"></colgroup>
        <thead>
            <tr><th colspan="2">NCA / DS / DV / Payroll</th><th colspan="4">Check / ADA</th><th rowspan="3">Payee</th><th rowspan="3">UACS Object Code</th><th rowspan="3">Nature of Payment</th><th colspan="4">Amount</th></tr>
            <tr><th rowspan="2">No.</th><th rowspan="2">Date</th><th colspan="2">Serial No.</th><th rowspan="2">Date</th><th rowspan="2">Date Released</th><th rowspan="2">NCA Received / Deposit Mode</th><th rowspan="2">Check Issued</th><th rowspan="2">ADA Issued</th><th rowspan="2">NCA / Bank Balance</th></tr>
            <tr><th>Check</th><th>ADA</th></tr>
        </thead>
        <tbody>
            <tr class="muted"><td colspan="12">Balance forwarded</td><td class="num" data-col="balance">{{ $money($balance) }}</td></tr>
        @foreach($entries as $entry)
            @if($entry['type'] === 'receipt')
                @php $balance += (float) $entry['receipt']->amount; @endphp
                <tr>
                    <td></td><td class="center">{{ $entry['date']->format('M d, Y') }}</td><td></td><td></td><td></td><td></td>
                    <td colspan="3">{{ $entry['receipt']->kindLabel() }}@if($entry['receipt']->reference) · {{ $entry['receipt']->reference }}@endif</td>
                    <td class="num" data-col="nca">{{ $money($entry['receipt']->amount) }}</td><td></td><td></td>
                    <td class="num" data-col="balance">{{ $money($balance) }}</td>
                </tr>
            @else
                @php $line = $entry['line']; $balance = round($balance - ($line['amount'] ?? 0), 2); @endphp
                <tr>
                    <td>{{ $line['dv_no'] }}</td>
                    <td class="center">{{ $line['dv_date']?->format('M d, Y') }}</td>
                    <td class="center">{{ $line['mode'] === 'check' ? $line['check_no'] : '' }}</td>
                    <td class="center">{{ $line['mode'] === 'ada' ? $line['check_no'] : '' }}</td>
                    <td class="center">{{ $line['date']->format('M d, Y') }}</td>
                    <td class="center">{{ $line['released']?->format('M d, Y') }}</td>
                    <td>{{ $line['payee'] }}</td>
                    <td class="center">{{ $line['uacs_code'] }}</td>
                    <td>{{ $line['nature'] }}</td>
                    <td></td>
                    <td class="num" data-col="check">{{ $line['mode'] === 'check' && $line['amount'] !== null ? $money($line['amount']) : '' }}</td>
                    <td class="num" data-col="ada">{{ $line['mode'] === 'ada' && $line['amount'] !== null ? $money($line['amount']) : '' }}</td>
                    <td class="num" data-col="balance">{{ $money($balance) }}</td>
                </tr>
            @endif
        @endforeach
        @if($data['lines']->isEmpty())
            <tr class="muted"><td colspan="13" class="center">No check or ADA was issued in this period.</td></tr>
        @endif
            <tr class="total"><td colspan="9" class="num">TOTAL</td><td class="num">{{ $money($data['total_received']) }}</td><td class="num">{{ $money($totalCheck) }}</td><td class="num">{{ $money($totalAda) }}</td><td class="num">{{ $money($balance) }}</td></tr>
        </tbody>
    </table>

    <div class="certify">
        <h2>C E R T I F I C A T I O N</h2>
        <p>I hereby certify on my official oath that the foregoing is a correct and complete record of all checks / ADAs issued by me in my capacity as {{ $report['officer']?->position ?: 'Disbursing Officer' }} of <strong>{{ strtoupper($school->name) }}</strong> during the period from <strong>{{ $report['from']->format('F j, Y') }}</strong> to <strong>{{ $report['to']->format('F j, Y') }}</strong> inclusive as indicated in the corresponding columns.</p>
        <div class="signature">
            <div class="name">{{ mb_strtoupper($report['officer']?->name ?? '') }}&nbsp;</div>
            <div class="role">{{ $report['officer']?->position }}</div>
            <div class="date">Date: ______________________</div>
        </div>
    </div>
</main>
</body>
</html>
