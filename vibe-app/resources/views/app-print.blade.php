<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APP FY {{ $plan->fiscal_year }} · {{ $school->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.3; }
        .paper { width: 100%; min-width: 1080px; margin: 0 auto; padding: 22px 28px 30px; background: #fff; }

        .masthead { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; column-gap: 8px; text-align: center; line-height: 1.4; margin-bottom: 6px; }
        .masthead-logo { display: flex; align-items: center; min-height: 70px; }
        .masthead-logo-left { justify-content: flex-start; }
        .masthead-logo-right { justify-content: flex-end; }
        .masthead-logo img { height: 74px; width: auto; max-width: 90px; object-fit: contain; }
        .masthead .small { font-size: 10px; }
        .masthead .dept { font-size: 14px; font-weight: bold; }
        .masthead .line { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .masthead .school { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-top: 2px; }
        .bar { margin-top: 3px; border-bottom: 2px solid #111; }

        .doc-title { text-align: center; margin: 10px 0 8px; line-height: 1.5; }
        .doc-title h1 { margin: 0; font-size: 13px; letter-spacing: .4px; }
        .doc-title p { margin: 2px 0; font-size: 10.5px; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #222; padding: 3px 5px; vertical-align: top; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #00af50; font-size: 10px; text-align: center; vertical-align: middle; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tr.category-row td { background: #e2efda; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
        tr.sub td { font-weight: bold; }
        tr.grand td { font-weight: bold; background: #92d050; }
        .spec { display: block; font-size: 9px; color: #333; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 28px; margin-top: 22px; font-size: 10.5px; }
        .signatures .name { margin-top: 30px; text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; min-height: 14px; }
        .signatures .position { text-align: center; }

        @media print {
            body { font-size: 9.5px; }
            .paper { min-width: 0; width: 100%; margin: 0; padding: 0; }
            thead th, tr.category-row td, tr.grand td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
            .signatures { break-inside: avoid; }
        }
        /* Page footer: the document title on the left and Page X of Y on the right (printed in the page margin). */
        @page { @bottom-left { content: "APP FY {{ $plan->fiscal_year }}"; font: 9px Arial, Helvetica, sans-serif; } @bottom-right { content: "Page " counter(page) " of " counter(pages); font: 9px Arial, Helvetica, sans-serif; } }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    $signatories = [
        [\App\Support\SignatoryCaption::of($plan->prepared_by_role, 'Prepared by:'), $plan->prepared_by_name, $plan->prepared_by_position],
        [\App\Support\SignatoryCaption::of($plan->recommended_by_role, 'Recommending Approval:'), $plan->recommended_by_name, $plan->recommended_by_position],
        [\App\Support\SignatoryCaption::of($plan->approved_by_role, 'Approved by:'), $plan->approved_by_name, $plan->approved_by_position],
    ];
    $lineNumber = 0;
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning').'#app', 'context' => 'Annual Procurement Plan · FY '.$plan->fiscal_year])

<main class="paper" data-official-page data-doc="app" data-paper="legal" data-orientation="landscape" data-margin="10">
    <header class="masthead">
        <div class="masthead-logo masthead-logo-left">@if($leftLogo)<img class="official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif</div>
        <div>
            <div class="small">Republic of the Philippines</div>
            <div class="dept">Department of Education</div>
            @if($region)<div class="line">{{ $region }}</div>@endif
            @if($division)<div class="line">{{ $division }}</div>@endif
            @if($school->district)<div class="line">{{ $school->district }}</div>@endif
            <div class="school">{{ $school->name }}</div>
        </div>
        <div class="masthead-logo masthead-logo-right">@if($rightLogo)<img class="official-logo" src="{{ $rightLogo }}" alt="" onerror="this.remove()">@endif</div>
    </header>
    <div class="bar"></div>

    <div class="doc-title">
        <h1>ANNUAL PROCUREMENT PLAN</h1>
        <p>FY {{ $plan->fiscal_year }}</p>
    </div>

    <table>
        <colgroup><col style="width:3.5%"><col style="width:24%"><col style="width:16%"><col style="width:5.5%"><col style="width:5.5%"><col style="width:8.5%"><col style="width:10%"><col style="width:9%"><col style="width:9%"><col style="width:9%"></colgroup>
        <thead>
            <tr><th>No.</th><th>Procurement Item</th><th>Specifications / PPA</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Estimated Total</th><th>Mode of Procurement</th><th>Schedule</th><th>Source of Fund</th></tr>
        </thead>
        <tbody>
        @foreach($groups as $group)
            <tr class="category-row"><td colspan="10">{{ $group['category'] }}</td></tr>
            @foreach($group['lines'] as $line)
                @php $lineNumber++; @endphp
                <tr>
                    <td class="center">{{ $lineNumber }}</td>
                    <td>{{ $line->procurement_item }}</td>
                    <td>{{ $line->specifications }}</td>
                    <td class="center">{{ $number($line->quantity) }}</td>
                    <td class="center">{{ $line->unit }}</td>
                    <td class="num">{{ $money($line->estimated_unit_cost) }}</td>
                    <td class="num">{{ $money($line->estimated_total_cost) }}</td>
                    <td>{{ $line->procurement_mode }}</td>
                    <td>{{ $line->procurement_schedule }}</td>
                    <td>{{ $line->fund_source }}</td>
                </tr>
            @endforeach
            <tr class="sub"><td colspan="6" style="text-align:right">Sub-Total</td><td class="num">{{ $money($group['total']) }}</td><td colspan="3"></td></tr>
        @endforeach
            <tr class="grand"><td colspan="6">GRAND TOTAL</td><td class="num">{{ $money($grandTotal) }}</td><td colspan="3"></td></tr>
        </tbody>
    </table>

    <div class="signatures">
        @foreach($signatories as [$label, $name, $position])
            <div><div>{{ $label }}</div><div class="name">{{ $name }}</div><div class="position">{{ $position }}</div></div>
        @endforeach
    </div>
</main>
</body>
</html>
