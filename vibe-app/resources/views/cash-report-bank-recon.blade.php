<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Reconciliation · {{ $report['school']->name }}</title>
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
    // The bank book: money downloaded to the account (opening balance, NCA, cash advance) and the checks / ADAs paid out of it, in date order (money downloaded first on the same day), with the running balance. A payment in cash (mode Others) does not touch the bank, so it is left out.
    // A balance forwarded the user recorded is shown at the top with its fund, not as cash downloaded.
    $forwarded = $data['receipts']->where('kind', 'balance_forwarded')->values();
    $downloaded = $data['receipts']->where('kind', '!=', 'balance_forwarded')->values();
    $entries = $downloaded->map(fn ($receipt) => ['type' => 'receipt', 'date' => $receipt->received_at, 'receipt' => $receipt])
        ->concat($data['lines']->reject(fn (array $line) => $line['mode'] === 'other')->map(fn (array $line) => ['type' => 'line', 'date' => $line['date'], 'line' => $line]))
        ->sortBy(fn (array $entry) => $entry['date']->format('Ymd').($entry['type'] === 'receipt' ? '0' : '1'))
        ->values();
    // The balance carried forward from before the period is the cash downloaded less the checks and ADAs paid until then.
    // When the user recorded a balance forwarded, those rows are the beginning of the report and the automatic one is left out.
    $openingBank = $forwarded->isEmpty() ? (float) $data['opening_bank'] : 0.0;
    $balance = $openingBank;
    $fundOf = fn ($fund) => trim((string) $fund);
    $fundLabel = fn ($fund) => $fund === '' ? 'Not specified' : $fund;
    $funds = ($forwarded->isEmpty() ? $data['opening_bank_by_fund']->keys() : collect())
        ->merge($data['receipts']->map(fn ($receipt) => $fundOf($receipt->source_of_fund)))
        ->merge($data['lines']->reject(fn (array $line) => $line['mode'] === 'other' || $line['cancelled'])->map(fn (array $line) => $fundOf($line['fund'])))
        ->unique()->sort()->values();
    $totalDownloaded = (float) $downloaded->sum('amount');
    $totalForwarded = (float) $forwarded->sum('amount');
    $totalPaid = (float) $data['lines']->reject(fn (array $line) => $line['mode'] === 'other')->sum(fn (array $line) => $line['amount'] ?? 0);
    $bookBalance = round($openingBank + $totalForwarded + $totalDownloaded - $totalPaid, 2);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $school->id]), 'context' => 'Bank Reconciliation · '.$report['period']])

<main class="paper" data-official-page data-sheets data-doc="cash-bank-recon" data-paper="longbond" data-orientation="landscape" data-margin="10">
    @include('partials.cash-report-header', ['title' => 'BANK RECONCILIATION', 'minimal' => true])
    @if($funds->isNotEmpty())<p style="margin:0 0 8px;font-weight:bold">Fund: <span style="text-decoration:underline">{{ $funds->map($fundLabel)->implode(', ') }}</span></p>@endif

    <table class="report" data-paginate>
        <colgroup><col style="width:9%"><col style="width:11%"><col style="width:11%"><col style="width:9%"><col style="width:24%"><col style="width:12%"><col style="width:12%"><col style="width:12%"></colgroup>
        <thead>
            <tr><th>Date</th><th>Check / ADA No. / Reference</th><th>DV No.</th><th>Fund</th><th>Particulars</th><th>Cash Downloaded</th><th>Check / ADA Issued</th><th>Running Balance</th></tr>
        </thead>
        <tbody>
        @if($forwarded->isEmpty())
            <tr class="muted"><td class="center">{{ $report['from']->format('M d, Y') }}</td><td colspan="6">Balance forwarded</td><td class="num" data-col="balance">{{ $money($balance) }}</td></tr>
        @endif
        @foreach($forwarded as $receipt)
            @php $balance = round($balance + (float) $receipt->amount, 2); @endphp
            <tr class="muted"><td class="center">{{ $receipt->received_at->format('M d, Y') }}</td><td colspan="6">Balance forwarded{{ $fundOf($receipt->source_of_fund) !== '' ? ', '.$fundOf($receipt->source_of_fund) : '' }}{{ $receipt->note ? ' · '.$receipt->note : '' }}</td><td class="num" data-col="balance">{{ $money($balance) }}</td></tr>
        @endforeach
        @foreach($entries as $entry)
            @if($entry['type'] === 'receipt')
                @php $fund = $fundOf($entry['receipt']->source_of_fund); $balance = round($balance + (float) $entry['receipt']->amount, 2); @endphp
                <tr>
                    <td class="center">{{ $entry['date']->format('M d, Y') }}</td>
                    <td class="center">{{ $entry['receipt']->reference }}</td>
                    <td></td>
                    <td class="center">{{ $fundLabel($fund) }}</td>
                    <td>{{ $entry['receipt']->kindLabel() }}@if($entry['receipt']->note) · {{ $entry['receipt']->note }}@endif</td>
                    <td class="num" data-col="downloaded">{{ $money($entry['receipt']->amount) }}</td>
                    <td></td>
                    <td class="num" data-col="balance">{{ $money($balance) }}</td>
                </tr>
            @else
                @php $line = $entry['line']; $fund = $fundOf($line['fund']); $balance = round($balance - ($line['amount'] ?? 0), 2); @endphp
                <tr @if($line['cancelled']) class="muted" @endif>
                    <td class="center">{{ $line['date']->format('M d, Y') }}</td>
                    <td class="center">{{ $line['mode'] === 'ada' ? 'ADA ' : '' }}{{ $line['check_no'] }}</td>
                    <td>{{ $line['dv_no'] }}</td>
                    <td class="center">{{ $line['cancelled'] ? '' : $fundLabel($fund) }}</td>
                    <td>{{ $line['cancelled'] ? 'CANCELLED' : $line['payee'] }}@if($line['nature']) · {{ $line['nature'] }}@endif</td>
                    <td></td>
                    <td class="num" data-col="paid">{{ $line['amount'] === null ? '' : $money($line['amount']) }}</td>
                    <td class="num" data-col="balance">{{ $money($balance) }}</td>
                </tr>
            @endif
        @endforeach
        @if($entries->isEmpty() && $forwarded->isEmpty())
            <tr class="muted"><td colspan="8" class="center">No cash was downloaded and no check or ADA was issued in this period.</td></tr>
        @endif
            <tr class="total"><td colspan="5" class="num">TOTAL</td><td class="num">{{ $money($totalDownloaded) }}</td><td class="num">{{ $money($totalPaid) }}</td><td class="num">{{ $money($bookBalance) }}</td></tr>
        </tbody>
    </table>

</main>
</body>
</html>
