<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checks and Advices to Debit Account Disbursement Record · {{ $report['school']->name }}</title>
@include('partials.cash-report-styles')
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.print-pages')
<style>
    /* The Checks and Advices to Debit Account Disbursement Record follows the owner's workbook (sheet chck_advice): Calibri, no logos, a boxed record
       that starts with the accountable officer, the one grid of checks and ADAs with the running bank balance, then the certification. */
    .chk { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #000; }
    .chk h1 { margin: 0 0 10px; text-align: center; font-size: 17px; font-weight: bold; }
    .chk table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .chk table.chk-info td { padding: 1px 3px; height: 15px; vertical-align: bottom; white-space: nowrap; overflow: hidden; font-size: 10.5px; }
    .chk table.chk-info td.value { border-bottom: 1px solid #000; }
    .chk table.chk-officer { border: 2px solid #000; border-bottom: 1px solid #000; }
    .chk table.chk-officer td { text-align: center; padding: 3px 4px 1px; vertical-align: bottom; font-size: 10.5px; }
    .chk table.chk-officer td + td { border-left: 1px solid #000; }
    .chk table.chk-officer .name { text-decoration: underline; font-size: 11px; text-transform: uppercase; }
    .chk table.report { border-left: 2px solid #000; border-right: 2px solid #000; }
    .chk table.report th, .chk table.report td { border: 1px solid #555; padding: 1px 3px; vertical-align: middle; overflow-wrap: break-word; }
    .chk table.report thead th { border-color: #000; font-size: 9px; font-weight: bold; padding: 2px 3px; text-align: center; }
    .chk table.report thead th.big { font-size: 11px; }
    .chk table.report thead th.low { vertical-align: bottom; text-align: left; }
    .chk table.report tbody td { height: 15px; padding: 0 3px; font-size: 9px; }
    .chk table.report tbody td.num { text-align: right; white-space: nowrap; font-size: 9.5px; }
    .chk table.report tbody td.center { text-align: center; white-space: nowrap; }
    .chk table.report tbody td.payee { font-size: 8.5px; }
    .chk table.report tbody td.nature { font-size: 9px; }
    .chk table.report tbody td.code { text-align: right; white-space: nowrap; }
    .chk .certification { border: 2px solid #000; border-top: 1px solid #000; padding: 4px 8px 6px; }
    .chk .certification h2 { margin: 2px 0 6px; text-align: center; font-size: 15px; font-weight: normal; letter-spacing: 0; }
    .chk .certification p { margin: 0; text-align: center; font-size: 11.5px; line-height: 1.45; }
    .chk .certification p u { text-underline-offset: 2px; }
    .chk .certification .remarks { margin-top: 6px; font-size: 10px; text-align: left; }
    .chk .sign { width: 36%; margin: 26px 0 8px 41%; text-align: center; }
    .chk .sign .name { font-size: 15px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; }
    .chk .sign .role { font-size: 11.5px; }
    .chk .sign .gap { height: 22px; border-bottom: 1px solid #000; }
    .chk .sign .date { font-size: 11.5px; }
</style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $data = $report['data'];
    $school = $report['school'];
    // The receipts and the checks / ADAs of the period in date order (money received first on the same day), with the running balance.
    $entries = $data['receipts']->map(fn ($receipt) => ['type' => 'receipt', 'date' => $receipt->received_at, 'receipt' => $receipt])
        ->concat($data['lines']->reject(fn (array $line) => $line['mode'] === 'other')->map(fn (array $line) => ['type' => 'line', 'date' => $line['date'], 'line' => $line]))
        ->sortBy(fn (array $entry) => $entry['date']->format('Ymd').($entry['type'] === 'receipt' ? '0' : '1'))
        ->values();
    // The balance is the bank balance: a payment in cash (mode Others) does not touch it, so it is not listed here (the register has it).
    $balance = $data['opening_bank'];
    $dash = fn ($value) => ($value === null || round((float) $value, 2) == 0.0) ? '-' : $money($value);
    $officer = $report['officer'];
    $periodFrom = $report['from']->format('F j, Y');
    $periodTo = $report['to']->format('F j, Y');
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $school->id]), 'context' => 'Checks and Advices to Debit Account · '.$report['period']])

<main class="paper chk" data-official-page data-sheets data-doc="cash-checks-advices" data-paper="longbond" data-orientation="landscape" data-margin="8">
    <h1>CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD</h1>

    <table class="chk-info">
        <colgroup><col style="width:17%"><col style="width:28%"><col style="width:22%"><col style="width:10%"><col style="width:18%"><col style="width:5%"></colgroup>
        <tr><td>Entity Name:</td><td class="value">{{ mb_strtoupper($report['entityName']) }}</td><td></td><td>Fund Cluster</td><td class="value">{{ $report['fundCluster'] }}</td><td></td></tr>
        <tr><td>Bank Name/ Bank Account No.:</td><td style="padding-left:14px">{{ $report['bank'] }}</td><td></td><td>Sheet No.</td><td style="padding-left:3px">@if($report['sheetNo'] !== ''){{ $report['sheetNo'] }}@else<span data-sheet-no>1 of 1</span>@endif</td><td></td></tr>
    </table>

    <table class="chk-officer">
        <colgroup><col style="width:56%"><col style="width:22%"><col style="width:22%"></colgroup>
        <tr><td class="name">{{ mb_strtoupper($report['officerName']) }}</td><td>{{ $report['officerPosition'] }}</td><td>{{ mb_strtoupper($report['station'] !== '' ? $report['station'] : $school->name) }}</td></tr>
        <tr><td>Accountable Officer</td><td>Official Designation</td><td>Station</td></tr>
    </table>

    <table class="report" data-paginate>
        <colgroup><col style="width:6.8%"><col style="width:5.4%"><col style="width:5.5%"><col style="width:3.2%"><col style="width:6%"><col style="width:4.7%"><col style="width:23.8%"><col style="width:6.1%"><col style="width:16.8%"><col style="width:6%"><col style="width:4.7%"><col style="width:4.3%"><col style="width:6.7%"></colgroup>
        <thead>
            <tr><th colspan="2" class="big" style="text-align:left">NCA/DS/DV/Payroll</th><th colspan="4" class="big">Check/ ADA</th><th rowspan="3" class="big">Payee</th><th rowspan="3">UACs Object Code</th><th rowspan="3" class="big">Nature of Payment</th><th colspan="4">Amount</th></tr>
            <tr><th rowspan="2" class="low">No.</th><th rowspan="2" class="low">Date</th><th colspan="2">Serial No.</th><th rowspan="2">Date</th><th rowspan="2">Date Released</th><th rowspan="2">NCA Received Deposit Mode</th><th rowspan="2">Check Issued</th><th rowspan="2">ADA Issued</th><th rowspan="2">NCA / Bank Balance</th></tr>
            <tr><th style="text-align:left">Check</th><th>ADA</th></tr>
        </thead>
        <tbody>
            <tr><td colspan="12"></td><td class="num" data-col="balance">{{ $dash($balance) }}</td></tr>
        @foreach($entries as $entry)
            @if($entry['type'] === 'receipt')
                @php $balance = round($balance + (float) $entry['receipt']->amount, 2); @endphp
                <tr>
                    <td></td><td class="center">{{ $entry['date']->format('m/d/Y') }}</td><td></td><td></td><td></td><td></td>
                    <td class="payee" colspan="3">{{ $entry['receipt']->kindLabel() }}@if($entry['receipt']->reference) · {{ $entry['receipt']->reference }}@endif</td>
                    <td class="num" data-col="nca">{{ $money($entry['receipt']->amount) }}</td><td></td><td></td>
                    <td class="num" data-col="balance">{{ $dash($balance) }}</td>
                </tr>
            @else
                @php $line = $entry['line']; $balance = round($balance - ($line['amount'] ?? 0), 2); @endphp
                <tr>
                    <td style="white-space:nowrap;font-size:8.5px">{{ $line['dv_no'] }}</td>
                    <td class="center">{{ $line['dv_date']?->format('m/d/Y') }}</td>
                    <td class="center">{{ $line['mode'] === 'check' ? $line['check_no'] : '' }}</td>
                    <td class="center">{{ $line['mode'] === 'ada' ? $line['check_no'] : '' }}</td>
                    <td class="center">{{ $line['date']->format('m/d/Y') }}</td>
                    <td class="center">{{ $line['released']?->format('m/d/Y') }}</td>
                    <td class="payee">{{ $line['payee'] }}</td>
                    <td class="code">{{ $line['uacs_code'] !== '' ? $line['uacs_code'] : '0' }}</td>
                    <td class="nature">{{ $line['nature'] }}</td>
                    <td></td>
                    <td class="num" data-col="check">{{ $line['mode'] === 'check' ? $dash($line['amount']) : '' }}</td>
                    <td class="num" data-col="ada">{{ $line['mode'] === 'ada' && $line['amount'] !== null ? $money($line['amount']) : '' }}</td>
                    <td class="num" data-col="balance">{{ $dash($balance) }}</td>
                </tr>
            @endif
        @endforeach
        @if($entries->where('type', 'line')->isEmpty())
            <tr><td colspan="13" style="text-align:center;font-style:italic">No check or ADA was issued in this period.</td></tr>
        @endif
        </tbody>
    </table>

    <div class="certification">
        <h2>CERTIFICATION</h2>
        <p>I hereby certify on my official oath that the foregoing is a correct and complete record of all checks/ ADAs issued by me in my capacity as {{ $report['officerPosition'] !== '' ? $report['officerPosition'] : 'Disbursing Officer' }} of <u>{{ mb_strtoupper($report['entityName']) }}</u> during the period from <u>{{ $periodFrom }}</u> to <u>{{ $periodTo }}</u> inclusive as indicated in the corresponding columns.</p>
        @if($report['remarks'] !== '')<p class="remarks"><strong>Remarks:</strong> {{ $report['remarks'] }}</p>@endif
        <div class="sign">
            <div class="name">{{ mb_strtoupper($officer?->name ?? '') }}&nbsp;</div>
            <div class="role">{{ $officer?->position }}&nbsp;</div>
            <div class="gap"></div>
            <div class="date">DATE</div>
        </div>
    </div>
</main>
<script>
    // Sheet No. is "1 of N", N being the number of printed pages the record runs to.
    (function () {
        function update() {
            var page = document.querySelector('[data-official-page]');
            var total = page ? Math.max(1, page.querySelectorAll(':scope > .doc-page').length) : 1;
            document.querySelectorAll('[data-sheet-no]').forEach(function (element) { element.textContent = '1 of ' + total; });
        }
        document.addEventListener('DOMContentLoaded', function () {
            var page = document.querySelector('[data-official-page]');
            if (page) { new MutationObserver(update).observe(page, { childList: true }); }
            window.addEventListener('beforeprint', update);
            window.addEventListener('load', function () { setTimeout(update, 50); });
            update();
        });
    })();
</script>
</body>
</html>
