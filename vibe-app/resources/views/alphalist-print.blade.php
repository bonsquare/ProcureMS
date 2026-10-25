<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alphalist - Tax Remittance · {{ $school->name }}</title>
@include('partials.input-fixes')
@include('partials.print-clean')
<style>
    body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
    .map-head { text-align: center; line-height: 1.45; }
    .map-head .title { margin-top: 10px; font-weight: bold; font-size: 13px; }
    .map-head .tin { text-align: left; margin: 6px 0 4px 22%; font-weight: bold; }
    table.map-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.map-table th, table.map-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
    table.map-table th { text-align: center; font-weight: bold; }
    table.map-table td.num { text-align: right; white-space: nowrap; }
    table.map-table td.center { text-align: center; }
    table.map-table tr.total td { font-weight: bold; }
    .declaration { margin-top: 10px; text-align: justify; line-height: 1.4; }
    .sign { margin: 36px 0 0 62%; text-align: center; }
    .sign .name { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; }
    .missing { margin: 12px 0; padding: 8px 10px; border: 2px solid #b00020; color: #b00020; font-weight: bold; }
</style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $rate = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
    $blocks = collect($report['blocks'])->filter(fn ($rows) => $rows->isNotEmpty());
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('liquidation'), 'context' => 'Alphalist - Tax Remittance · '.$period])

@if($report['missing'] !== [])
    <div class="missing no-print">Left out: the payee has no supplier in the Supplier Manager, or its 1600 and 0619E rates are not entered yet: {{ implode('; ', $report['missing']) }}.</div>
@endif
{{-- One sheet of paper per BIR form. --}}
<main class="paper" data-official-page data-sheets data-paper="longbond" data-orientation="landscape" data-margin="10">

    @forelse($blocks as $form => $rows)
        <section class="doc-page" data-form="{{ $form }}">
            <div class="map-head">
                <div>{{ $department }}</div>
                <div>{{ $division }}</div>
                <div><strong>{{ mb_strtoupper($school->name) }}</strong></div>
                <div class="title">MONTHLY ALPHALIST OF PAYEES (MAP)</div>
                <div>RETURNED PERIOD</div>
                <div>{{ mb_strtoupper($fund) }}</div>
                <div class="tin">TIN: {{ $schoolTin }}</div>
            </div>
            <table class="map-table">
                <colgroup><col style="width:3%"><col style="width:10%"><col style="width:24.5%"><col style="width:8%"><col style="width:6%"><col style="width:23%"><col style="width:10.5%"><col style="width:6.5%"><col style="width:8.5%"></colgroup>
                <thead>
                    <tr><th rowspan="3">Req. No.</th><th rowspan="3">TIN including branch code</th><th rowspan="3">Registered<br>(Alphalist)</th><th>Returned</th><th rowspan="3">ATC</th><th rowspan="3">Name of Income Payment</th><th>AMOUNT</th><th rowspan="3">Tax Rate</th><th rowspan="3">Tax Withheld</th></tr>
                    <tr><th>Period</th><th rowspan="2">Tax Base</th></tr>
                    <tr><th>mm/yy</th></tr>
                </thead>
                <tbody>
                    @foreach($rows as $number => $row)
                        <tr><td class="center">{{ $number + 1 }}</td><td>{{ $row['tin'] }}</td><td>{{ $row['payee'] }}</td><td class="center">{{ $period }}</td><td class="center">{{ $row['atc'] }}</td><td>{{ $row['nature'] }}</td><td class="num">{{ $money($row['base']) }}</td><td class="center">{{ $rate($row['rate']) }}</td><td class="num">{{ $money($row['withheld']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td colspan="6"></td><td class="num">{{ $money($rows->sum('base')) }}</td><td></td><td class="num">{{ $money($rows->sum('withheld')) }}</td></tr>
                </tbody>
            </table>
            <p class="declaration">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I declare under the penalties of perjury; that this has been made in good faith, verified by me, and to the best of my knowledge and beliefs is true and correct pursuant to the provision of the NIRC, and the regulation issued under the authority thereof, that the information contained herein completely reflects all income payments with the corresponding taxes withheld from payee; are duly remitted to the BIR and proper Certificate of Creditable Withholding Tax at source (BIR form No. 2307) have been issued to payees; that the information appearing herein shall be consistent with the total amount remitted and that, inconsistent information shall result to denial of the claims for expenses.</p>
            <div class="sign"><div class="name">{{ $signatoryName ?: ' ' }}&nbsp;</div><div>{{ $signatoryPosition }}</div></div>
        </section>
    @empty
        <div class="doc-page"><p>No withholding tax was paid in {{ mb_strtoupper($month->copy()->subMonthNoOverflow()->format('F Y')) }} (returned period {{ $period }}){{ $fund !== '' ? ' for '.$fund : '' }}.</p></div>
    @endforelse
</main>
</body>
</html>
