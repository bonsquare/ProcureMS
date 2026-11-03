<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POW {{ $pow->project_title }} · {{ $school->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }
        .paper { width: 100%; margin: 0; padding: 0; background: #fff; }

        .masthead { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; column-gap: 8px; text-align: center; line-height: 1.45; }
        .masthead-logo { display: flex; align-items: center; min-height: 70px; }
        .masthead-logo-left { justify-content: flex-start; }
        .masthead-logo-right { justify-content: flex-end; }
        .masthead-logo img { height: 74px; width: auto; max-width: 90px; object-fit: contain; }
        .masthead .dept { font-size: 14px; font-weight: bold; }
        .masthead .line { font-weight: bold; text-transform: uppercase; }
        .masthead .school { font-size: 13px; font-weight: bold; text-transform: uppercase; margin-top: 2px; }
        .doc-title { text-align: center; margin: 14px 0 10px; }
        .doc-title h1 { margin: 0; font-size: 15px; letter-spacing: .4px; }
        .doc-title p { margin: 2px 0 0; font-size: 12px; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.info td { border: 1px solid #222; padding: 3px 6px; vertical-align: top; overflow-wrap: break-word; }
        table.info td.label { width: 17%; font-weight: bold; }
        table.items th, table.items td { border: 1px solid #222; padding: 3px 6px; vertical-align: top; overflow-wrap: break-word; }
        table.items th { background: #f0f0f0; text-align: center; }
        table.items td.num { text-align: right; white-space: nowrap; }
        table.items td.center { text-align: center; }
        table.items tr.section td { font-weight: bold; }
        table.items tr.total td { font-weight: bold; }
        table.items tr.grand td { font-weight: bold; background: #f0f0f0; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 26px 40px; margin-top: 24px; font-size: 11px; }
        .signatures .name { margin-top: 34px; text-align: center; }
        .sig-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signatures .position { text-align: center; }
        .signatures .wide { grid-column: 1 / -1; width: 50%; margin: 0 auto; }

        @media print {
            thead th, tr.grand td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
            .signatures { break-inside: avoid; }
        }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.print-pages')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $day = fn ($value) => $value?->format('F j, Y');
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    $schoolId = $school->deped_school_id ?: $school->code;
    $address = $pow->school_address ?: $school->address;
    $sign = fn (string $slot) => $pow->signatories[$slot] ?? ['name' => '', 'position' => ''];
    $superintendent = $sign('division_superintendent');
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning.pow.show', $pow), 'context' => 'Program of Works · '.$pow->project_title])

<main class="paper" data-official-page data-sheets data-doc="pow" data-paper="folio" data-orientation="portrait" data-margin="10">
    <header class="masthead">
        <div class="masthead-logo masthead-logo-left">@if($leftLogo)<img class="official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif</div>
        <div>
            <div>Republic of the Philippines</div>
            <div class="dept">Department of Education</div>
            @if($region)<div class="line">{{ $region }}</div>@endif
            @if($division)<div class="line">{{ $division }}</div>@endif
            <div class="school">{{ $school->name }}</div>
            @if($address)<div>{{ $address }}</div>@endif
        </div>
        <div class="masthead-logo masthead-logo-right">@if($rightLogo)<img class="official-logo" src="{{ $rightLogo }}" alt="" onerror="this.remove()">@endif</div>
    </header>

    <div class="doc-title">
        <h1>PROGRAM OF WORKS</h1>
        <p>{{ strtoupper($plan->fund_source) }} {{ $plan->quarterLabel() }} {{ $plan->fiscal_year }}</p>
    </div>

    <table class="info">
        <tr><td class="label">School:</td><td>{{ $school->name }}</td><td class="label">Date:</td><td>{{ $day($pow->pow_date) }}</td></tr>
        <tr><td class="label">School Address:</td><td>{{ $address }}</td><td class="label">Budget Allocation:</td><td>{{ $money($totals['total']) }}</td></tr>
        <tr><td class="label">School ID:</td><td>{{ $schoolId }}</td><td class="label">Date Started:</td><td>{{ $day($pow->date_started) }}</td></tr>
        <tr><td class="label">Region:</td><td>{{ $region }}</td><td class="label">Date of Completion:</td><td>{{ $day($pow->date_completed) }}</td></tr>
        <tr><td class="label">Division:</td><td>{{ $division }}</td><td class="label">Minimum Required Manpower:</td><td>{{ $pow->manpower }}</td></tr>
        <tr><td class="label">Project Title:</td><td>{{ $pow->project_title }}</td><td class="label">Minimum Required Equipment:</td><td>{{ $pow->equipment }}</td></tr>
    </table>

    <table class="items" data-paginate style="margin-top:10px">
        <colgroup><col style="width:7%"><col style="width:41%"><col style="width:9%"><col style="width:11%"><col style="width:15%"><col style="width:17%"></colgroup>
        <thead><tr><th>Item No.</th><th>Item Description</th><th>Unit</th><th>Quantity</th><th>Unit Cost</th><th>Total</th></tr></thead>
        <tbody>
            <tr class="section"><td colspan="6">A. Materials:</td></tr>
            @foreach($pow->items as $item)
                <tr class="item-row"><td class="center">{{ $loop->iteration }}</td><td>{{ $item->description }}</td><td class="center">{{ $item->unit }}</td><td class="center">{{ $number($item->quantity) }}</td><td class="num">{{ $money($item->unit_cost) }}</td><td class="num">{{ $money($item->amount) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="5">TOTAL MATERIALS COST - - - -</td><td class="num">{{ $money($totals['materials']) }}</td></tr>
            <tr class="section"><td colspan="5">B. Labor Cost:</td><td class="num">{{ $money($totals['labor']) }}</td></tr>
            <tr class="grand"><td colspan="5">C. Total Project Cost</td><td class="num">{{ $money($totals['total']) }}</td></tr>
        </tbody>
    </table>

    <div class="signatures">
        <div><div>Prepared by:</div><div class="name"><span class="sig-name">{{ mb_strtoupper($sign('prepared_by')['name']) }}</span></div><div class="position">{{ $sign('prepared_by')['position'] }}</div></div>
        <div><div>Recommending Approval:</div><div class="name"><span class="sig-name">{{ mb_strtoupper($sign('recommended_by')['name']) }}</span></div><div class="position">{{ $sign('recommended_by')['position'] }}</div></div>
        <div class="wide"><div>APPROVED:</div><div class="name"><span class="sig-name">{{ mb_strtoupper($sign('approved_by')['name']) }}</span></div><div class="position">{{ $sign('approved_by')['position'] }}</div></div>
        @if(filled($superintendent['name'] ?? null))
            <div class="wide"><div>APPROVED:</div><div class="name"><span class="sig-name">{{ mb_strtoupper($superintendent['name']) }}</span></div><div class="position">{{ $superintendent['position'] }}</div></div>
        @endif
    </div>
</main>
</body>
</html>
