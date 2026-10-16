<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report of Checks Issued · {{ $report['school']->name }}</title>
@include('partials.cash-report-styles')
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.print-pages')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $lines = $report['data']['lines']->where('mode', 'check')->values();
    $total = $lines->sum(fn (array $line) => $line['amount'] ?? 0);
    $checkNos = $lines->pluck('check_no')->filter()->sort()->values();
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $report['school']->id]), 'context' => 'Report of Checks Issued · '.$report['period']])

<main class="paper" data-official-page data-sheets data-doc="cash-roci" data-paper="longbond" data-orientation="landscape" data-margin="10">
    @include('partials.cash-report-header', ['title' => 'REPORT OF CHECKS ISSUED', 'appendix' => 'Appendix 35'])

    <table class="report" data-paginate>
        <colgroup><col style="width:8%"><col style="width:10%"><col style="width:11%"><col style="width:11%"><col style="width:10%"><col style="width:17%"><col style="width:10%"><col style="width:13%"><col style="width:10%"></colgroup>
        <thead>
            <tr><th colspan="2">Check</th><th rowspan="2">DV / Payroll No.</th><th rowspan="2">ORS / BURS No.</th><th rowspan="2">Responsibility Center Code</th><th rowspan="2">Payee</th><th rowspan="2">UACS Object Code</th><th rowspan="2">Nature of Payment</th><th rowspan="2">Amount</th></tr>
            <tr><th>Date</th><th>Serial No.</th></tr>
        </thead>
        <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="center">{{ $line['date']->format('M d, Y') }}</td>
                <td class="center">{{ $line['check_no'] }}</td>
                <td>{{ $line['dv_no'] }}</td>
                <td>{{ $line['ors_no'] }}</td>
                <td class="center">{{ $line['responsibility_center'] }}</td>
                <td>{{ $line['payee'] }}</td>
                <td class="center">{{ $line['uacs_code'] }}</td>
                <td>{{ $line['nature'] }}</td>
                <td class="num">{{ $line['amount'] === null ? '' : $money($line['amount']) }}</td>
            </tr>
        @empty
            <tr class="muted"><td colspan="9" class="center">No check was issued in this period.</td></tr>
        @endforelse
            <tr class="total"><td colspan="8" class="num">TOTAL</td><td class="num">{{ $money($total) }}</td></tr>
        </tbody>
    </table>

    <div class="certify">
        <h2>C E R T I F I C A T I O N</h2>
        <p>I hereby certify on my official oath that this Report of Checks Issued in ____ sheet(s) is a full, true and correct statement of all checks issued by me during the period stated above for which Check Nos. <strong>{{ $checkNos->first() ?? '__________' }}</strong> to <strong>{{ $checkNos->last() ?? '__________' }}</strong> inclusive, were actually issued by me in payment for obligation shown in the attached disbursement vouchers / payroll.</p>
        <div class="signature">
            <div class="name">{{ mb_strtoupper($report['officer']?->name ?? '') }}&nbsp;</div>
            <div class="role">Name and Signature of Disbursing Officer / Cashier</div>
            <div class="date">Date: ______________________</div>
        </div>
    </div>
</main>
</body>
</html>
