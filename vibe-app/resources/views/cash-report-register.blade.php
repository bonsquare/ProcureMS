<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Disbursement Register · {{ $report['school']->name }}</title>
@include('partials.cash-report-styles')
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.print-pages')
<style>
    /* The Cash Disbursement Register follows the owner's workbook (CASH DISBURSEMENT REGISTER.xlsx, sheet CASH_DISBRSMNT): Calibri, no logos, boxed header,
       one grid for the working fund and the breakdown of payments, the recapitulation under it and the two signature boxes. */
    .cdr { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #000; }
    .cdr h1 { margin: 0 0 14px; text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 0; }
    .cdr table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .cdr table.cdr-info td { padding: 1px 3px; height: 14px; vertical-align: bottom; white-space: nowrap; overflow: hidden; }
    .cdr table.cdr-info td.value { border-bottom: 1px solid #000; font-weight: normal; text-transform: none; }
    .cdr table.report th, .cdr table.report td { border: 1px solid #555; padding: 1px 3px; vertical-align: middle; overflow-wrap: break-word; }
    .cdr table.report thead th { border: 1px solid #000; font-size: 10px; font-weight: bold; padding: 2px 3px; text-transform: uppercase; }
    .cdr table.report thead tr.acct-title th { font-size: 8px; text-transform: none; line-height: 1.15; }
    .cdr table.report thead tr.acct-code th { font-size: 7.5px; font-weight: normal; padding: 1px 2px; }
    .cdr table.report thead { border: 2px solid #000; }
    .cdr table.report tbody td { height: 15px; padding: 0 3px; font-size: 9.5px; }
    .cdr table.report tbody td.date { text-align: right; white-space: nowrap; }
    .cdr table.report tbody td.ref { text-align: right; white-space: nowrap; }
    .cdr table.report tbody td.part { font-size: 8.5px; text-align: left; }
    .cdr table.report tbody td.num { text-align: right; white-space: nowrap; }
    .cdr table.report tr.beginning td { font-size: 9.5px; }
    .cdr table.report tr.grand td { height: 17px; font-size: 10.5px; font-weight: normal; border-top: 1px solid #000; }
    .cdr table.report tr.grand td.label { font-size: 12px; text-align: left; border-right: 0; }
    .cdr .frame { border: 1px solid #000; border-top: 0; }
    .cdr table.recap td { padding: 0 3px; height: 14px; font-size: 8.5px; }
    .cdr table.recap td.head { font-size: 10.5px; }
    .cdr table.recap td.code { text-align: center; }
    .cdr table.recap td.amt { text-align: right; font-size: 9.5px; padding-right: 6px; }
    .cdr table.recap td.label { font-size: 12px; text-align: right; }
    .cdr table.recap-outer td { vertical-align: top; padding: 4px 0; }
    .cdr table.recap-outer td.recap-label { width: 52%; text-align: right; padding-right: 14px; font-size: 11px; }
    .cdr table.recap-outer td.recap-box { width: 48%; padding-right: 8px; }
    .cdr table.recap-grid { border: 1px solid #000; }
    .cdr table.recap-grid th, .cdr table.recap-grid td { border: 1px solid #000; padding: 2px 5px; font-size: 9px; height: 15px; vertical-align: middle; }
    .cdr table.recap-grid th { font-size: 10px; text-align: center; background: #eef1f4; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .cdr table.recap-grid td.code { text-align: center; }
    .cdr table.recap-grid td.amt { text-align: right; font-variant-numeric: tabular-nums; }
    .cdr table.recap-grid tr.recap-total td { font-size: 10.5px; font-weight: bold; text-align: right; border-top: 2px solid #000; }
    .cdr table.recap-grid tr.recap-total td.amt { text-align: right; }
    .cdr .note { padding: 3px 3px 2px 29%; font-size: 10.5px; line-height: 1.25; }
    .cdr .remarks { padding: 3px 6px; font-size: 10px; border-top: 1px solid #000; }
    .cdr table.sign { border-top: 1px solid #000; }
    .cdr table.sign td { width: 50%; height: 86px; padding: 2px 4px; vertical-align: top; position: relative; }
    .cdr table.sign td + td { border-left: 1px solid #000; }
    .cdr table.sign .caption { font-size: 8px; }
    .cdr table.sign td.right .caption { font-size: 11px; }
    .cdr table.sign .who { position: absolute; left: 20%; right: 20%; bottom: 20px; text-align: center; }
    .cdr table.sign .who .name { font-size: 11px; font-weight: bold; text-transform: uppercase; border: 0; margin: 0; padding: 0; }
    .cdr table.sign .who .role { font-size: 11px; border-top: 1px solid #000; }
    .cdr table.sign .date { position: absolute; left: 4px; bottom: 1px; font-size: 11px; }
</style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $data = $report['data'];
    $school = $report['school'];
    $agency = $report['agency'];
    $accounts = $data['accounts'];
    // Nine expense accounts fit beside the working fund (as in the workbook); any more continue in a table below with the same rows.
    $accountGroups = array_chunk($accounts, 9, true);
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
    $totalAdvance = round($data['opening'] + collect($rows)->sum(fn (array $row) => $row['advance'] ?? 0), 2);
    $blank = fn ($value) => ($value === null || (float) $value == 0.0) ? '' : $money($value);
    $officer = $report['officer'];
    $district = $school->district ?: ($agency?->district_name ?: ($agency?->division_name ?: $school->division));
    $registeredNo = $school->deped_school_id ?: $school->code;
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('cash', ['tab' => 'reports', 'school_id' => $school->id]), 'context' => 'Cash Disbursement Register · '.$report['period']])

<main class="paper cdr" data-official-page data-sheets data-doc="cash-register" data-paper="longbond" data-orientation="landscape" data-margin="8">
    <h1>CASH DISBURSEMENT REGISTER</h1>

    <table class="cdr-info">
        <colgroup><col style="width:9%"><col style="width:28%"><col style="width:17%"><col style="width:13.5%"><col style="width:26.5%"><col style="width:6%"></colgroup>
        <tr><td colspan="3"></td><td>Name of Accountable Officer:</td><td class="value">{{ $officer?->name ? mb_strtoupper($officer->name) : '' }}</td><td></td></tr>
        <tr><td>Entity Name:</td><td class="value" colspan="1">{{ mb_strtoupper($school->name) }}</td><td></td><td>Official Designation:</td><td class="value">{{ $officer?->position }}</td><td></td></tr>
        <tr><td colspan="1">Sub Office/ District/ Division:</td><td class="value">{{ $district ? mb_strtoupper($district) : '' }}</td><td></td><td>Station:</td><td class="value"></td><td></td></tr>
        <tr><td colspan="1">Municipality/ City/ Province:</td><td class="value">{{ $school->address ? mb_strtoupper($school->address) : '' }}</td><td></td><td>Registered No.:</td><td class="value" style="text-align:center">{{ $registeredNo }}</td><td></td></tr>
        <tr><td>Fund Cluster</td><td style="padding-left:12px">{{ $report['fundCluster'] }}</td><td></td><td>Sheet No.:</td><td style="text-align:center">@if($report['sheetNo'] !== ''){{ $report['sheetNo'] }}@else<span data-sheet-no>1 of 1</span>@endif</td><td></td></tr>
    </table>

    @foreach($accountGroups as $groupIndex => $group)
        @php $first = $groupIndex === 0; $count = 9; $codes = array_keys($group); $titles = array_values($group); @endphp
        @if(! $first)<p style="margin:14px 0 4px;font-weight:bold">BREAKDOWN OF PAYMENTS, CONTINUED</p>@endif
        <table class="report{{ $first ? '' : ' continued' }}" data-paginate>
            <colgroup>
                <col style="width:4.8%"><col style="width:5.6%">
                @if($first)<col style="width:24%"><col style="width:6.1%"><col style="width:5.6%"><col style="width:5.6%">@endif
                @for($i = 0; $i < $count; $i++)<col>@endfor
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="3">Date</th><th rowspan="3">Reference</th>
                    @if($first)<th rowspan="3">Particulars</th><th colspan="3">Working Fund</th>@endif
                    <th colspan="{{ $count }}">Breakdown of Payments</th>
                </tr>
                <tr class="acct-title">
                    @if($first)<th rowspan="2" style="font-size:10px;text-transform:uppercase">Cash Advance</th><th rowspan="2" style="font-size:10px;text-transform:uppercase">Payments</th><th rowspan="2" style="font-size:10px;text-transform:uppercase">Balance</th>@endif
                    @for($i = 0; $i < $count; $i++)<th>{{ $titles[$i] ?? '' }}&nbsp;</th>@endfor
                </tr>
                <tr class="acct-code">@for($i = 0; $i < $count; $i++)<th>{{ $codes[$i] ?? '' }}&nbsp;</th>@endfor</tr>
            </thead>
            <tbody>
                @if($first)
                    <tr class="beginning"><td></td><td colspan="2" style="padding-left:14px">Beginning Balance….</td><td class="num" data-col="advance">{{ $blank($data['opening']) }}</td><td></td><td class="num" data-col="balance">{{ $money($data['opening']) }}</td><td colspan="{{ $count }}"></td></tr>
                @endif
                @foreach($rows as $row)
                    <tr>
                        <td class="date">{{ $row['date']->format('m/d/Y') }}</td>
                        <td class="ref">{{ $row['reference'] }}</td>
                        @if($first)
                            <td class="part">{{ $row['particulars'] }}</td>
                            <td class="num" @if($row['advance'] !== null) data-col="advance" @endif>{{ $blank($row['advance']) }}</td>
                            <td class="num">{{ $blank($row['payment']) }}</td>
                            <td class="num" data-col="balance">{{ $money($row['balance']) }}</td>
                        @endif
                        @for($i = 0; $i < $count; $i++)
                            @if(isset($codes[$i]))
                                <td class="num" data-account="{{ $codes[$i] }}">{{ $row['code'] === (string) $codes[$i] && $row['payment'] !== null ? $money($row['payment']) : '' }}</td>
                            @else
                                <td></td>
                            @endif
                        @endfor
                    </tr>
                @endforeach
                @if($rows === [])
                    <tr class="muted"><td colspan="{{ ($first ? 6 : 2) + $count }}" style="text-align:center;font-style:italic">No payment was made in this period.</td></tr>
                @endif
                <tr class="grand">
                    <td class="label" colspan="{{ $first ? 3 : 2 }}">Grand Total - - - - -&gt;&gt;&gt;</td>
                    @if($first)<td class="num">{{ $blank($totalAdvance) }}</td><td class="num" data-col="total-payments">{{ $money($totalPayments) }}</td><td class="num">{{ $money($balance) }}</td>@endif
                    @for($i = 0; $i < $count; $i++)@if(isset($codes[$i]))<td class="num" data-total-account="{{ $codes[$i] }}">{{ $blank($accountTotals->get((string) $codes[$i], 0)) }}</td>@else<td></td>@endif @endfor
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="frame">
        <table class="recap-outer">
            <tr>
                <td class="recap-label">Recapitulation:</td>
                <td class="recap-box">
                    <table class="recap-grid">
                        <colgroup><col style="width:44%"><col style="width:30%"><col style="width:26%"></colgroup>
                        <thead><tr><th>Account Description</th><th>UACS Object Code</th><th>Amount</th></tr></thead>
                        <tbody>
                        @foreach($accounts as $code => $title)
                            <tr><td>{{ $title }}</td><td class="code">{{ $code }}</td><td class="amt" data-recap="{{ $code }}">{{ $accountTotals->get((string) $code, 0) > 0 ? $money($accountTotals->get((string) $code, 0)) : '-' }}</td></tr>
                        @endforeach
                        <tr class="recap-total"><td colspan="2">TOTAL - - - -&gt;&gt;&gt;</td><td class="amt">{{ $money($accountTotals->sum()) }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        <div class="note">The total of the ‘Advances for Operating Expenses – Payments’ column must always be equal to the sum of the totals of the ‘Breakdown of Payments’ columns.</div>
        @if($report['remarks'] !== '')<div class="remarks"><strong>Remarks:</strong> {{ $report['remarks'] }}</div>@endif
        <table class="sign">
            <tr>
                <td><div class="caption">CERTIFIED CORRECT:</div><div class="who"><div class="name">{{ mb_strtoupper($officer?->name ?? '') }}&nbsp;</div><div class="role">{{ $officer?->position }}&nbsp;</div></div><div class="date">Date:</div></td>
                <td class="right"><div class="caption">RECEIVED BY:</div><div class="who"><div class="name">{{ mb_strtoupper($report['receivedBy']?->name ?? '') }}&nbsp;</div><div class="role">{{ $report['receivedBy']?->position }}&nbsp;</div></div><div class="date">Date:</div></td>
            </tr>
        </table>
    </div>
</main>
<script>
    // Sheet No. is "1 of N", N being the number of printed pages the register runs to.
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
