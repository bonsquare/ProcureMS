<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOB {{ $plan->quarterLabel() }} FY {{ $plan->fiscal_year }} · {{ $school->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.3; }
        .paper { width: 100%; min-width: 760px; margin: 0 auto; padding: 22px 28px 30px; background: #fff; }

        .masthead { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; column-gap: 8px; text-align: center; line-height: 1.4; margin-bottom: 6px; }
        .masthead-logo { display: flex; align-items: center; min-height: 70px; }
        .masthead-logo-left { justify-content: flex-start; }
        .masthead-logo-right { justify-content: flex-end; }
        .masthead-logo img { height: 74px; width: auto; max-width: 90px; object-fit: contain; }
        .masthead .small { font-size: 10px; }
        .masthead .dept { font-size: 14px; font-weight: bold; }
        .masthead .line { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .masthead .school { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-top: 2px; }

        .school-id { margin-top: 3px; padding-bottom: 4px; border-bottom: 2px solid #111; font-size: 11px; }
        .doc-title { text-align: center; margin: 10px 0 8px; line-height: 1.5; }
        .doc-title h1 { margin: 0; font-size: 13px; letter-spacing: .4px; }
        .doc-title p { margin: 2px 0; font-size: 10.5px; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #222; padding: 3px 5px; vertical-align: top; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #00af50; font-size: 10px; text-align: center; vertical-align: middle; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tr.pillar-row td { background: #f3f3f3; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
        tr.pillar-access td { background: #ffff00; }
        tr.pillar-quality td { background: #2e75b5; color: #fff; }
        tr.pillar-equity td { background: #f4af84; }
        tr.pillar-resiliency td { background: #92d050; }
        tr.pillar-governance td { background: #548235; color: #fff; }
        tr.sub td { font-weight: bold; }
        tr.grand td { font-weight: bold; background: #92d050; }
        .summary { margin-top: 14px; width: 62%; }
        .summary caption { text-align: left; font-weight: bold; padding-bottom: 3px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 28px; margin-top: 22px; font-size: 10.5px; }
        .signatures .name { margin-top: 30px; text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signatures .position { text-align: center; }

        @media print {
            body { font-size: 9.5px; }
            .paper { min-width: 0; width: 100%; margin: 0; padding: 0; }
            thead th, tr.pillar-row td, tr.grand td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
            .signatures, .summary { break-inside: avoid; }
        }
        /* Page footer: the document title on the left and Page X of Y on the right (printed in the page margin). */
        @page { @bottom-left { content: "{{ $plan->documentTitle() }}"; font: 9px Arial, Helvetica, sans-serif; } @bottom-right { content: "Page " counter(page) " of " counter(pages); font: 9px Arial, Helvetica, sans-serif; } }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $pillarClass = ['Access' => 'access', 'Quality' => 'quality', 'Equity' => 'equity', 'Resiliency and Well-Being' => 'resiliency', 'Governance' => 'governance'];
    // Every pillar is printed, in the workbook's order, whether or not it has items.
    $byPillar = collect($grouped)->keyBy('pillar');
    $pillarRows = collect(\App\Services\SobService::PILLARS)->map(fn ($name) => $byPillar[$name] ?? ['pillar' => $name, 'total' => 0, 'programs' => []])
        ->merge(collect($grouped)->reject(fn ($row) => in_array($row['pillar'], \App\Services\SobService::PILLARS, true)));
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    $grandTotal = collect($grouped)->sum('total');
    $signatories = [
        ['Prepared by:', $plan->prepared_by_name, $plan->prepared_by_position],
        ['Recommending Approval:', $plan->recommended_by_name, $plan->recommended_by_position],
        ['APPROVED:', $plan->approved_by_name, $plan->approved_by_position],
    ];
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning.sob.show', $plan), 'context' => 'School Operating Budget · '.$plan->quarterLabel().' · FY '.$plan->fiscal_year])

<main class="paper" data-official-page data-doc="sob" data-paper="legal" data-orientation="portrait" data-margin="10">
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
    <div class="school-id">School ID: <strong>{{ $plan->school_id_number ?: $school->code }}</strong></div>

    <div class="doc-title">
        <h1>SCHOOL OPERATING BUDGET</h1>
        <p>FY {{ $plan->fiscal_year }} SCHOOL MAINTENANCE AND OTHER OPERATING EXPENSES ({{ strtoupper($plan->fund_source) }})</p>
        <p>{{ $plan->quarterLabel() }}</p>
    </div>

    <table>
        <colgroup><col style="width:21%"><col style="width:15%"><col style="width:18%"><col style="width:9.5%"><col style="width:8%"><col style="width:8%"><col style="width:9%"><col style="width:11.5%"></colgroup>
        <thead>
            <tr><th colspan="2">PPAs/Expenditures/Item</th><th>Particulars</th><th>Frequency</th><th>Quantity</th><th>Unit of Measure</th><th>Unit Cost</th><th>Amount</th></tr>
        </thead>
        <tbody>
        @foreach($pillarRows as $pillar)
            <tr class="pillar-row pillar-{{ $pillarClass[$pillar['pillar']] ?? 'other' }}"><td colspan="8">{{ strtoupper($pillar['pillar'] ?: 'Other') }}</td></tr>
            @foreach($pillar['programs'] as $program)
                @foreach($program['activities'] as $row)
                    @php
                        $activityRows = collect($row['accounts'])->sum(fn ($group) => $group['items']->count() + ($group['extras']->isNotEmpty() ? 1 + $group['extras']->count() : 0));
                        $firstOfActivity = true;
                    @endphp
                    @foreach($row['accounts'] as $group)
                        @php $groupRows = $group['items']->count() + ($group['extras']->isNotEmpty() ? 1 + $group['extras']->count() : 0); @endphp
                        @foreach($group['items'] as $index => $item)
                            <tr>
                                @if($firstOfActivity)<td rowspan="{{ $activityRows }}">{{ $program['program'] ?: 'No program' }}</td>@php $firstOfActivity = false; @endphp@endif
                                @if($index === 0)<td class="account" rowspan="{{ $groupRows }}">{{ $group['note'] ?: $group['account']?->title }}</td>@endif
                                <td>{{ $item->particulars }}</td>
                                <td class="center">{{ $number($item->frequency) }}</td>
                                <td class="center">{{ $number($item->quantity) }}</td>
                                <td class="center">{{ $item->unit }}</td>
                                <td class="num">{{ $money($item->unit_cost) }}</td>
                                <td class="num">{{ $money($item->amount) }}</td>
                            </tr>
                        @endforeach
                        @if($group['extras']->isNotEmpty())
                            <tr class="sub item-sub"><td colspan="5" style="text-align:right">Sub-Total</td><td class="num">{{ $money($group['items_total']) }}</td></tr>
                            @foreach($group['extras'] as $extra)
                                <tr class="extra"><td>{{ $extra->particulars }}</td><td></td><td></td><td class="center">{{ $extra->unit }}</td><td></td><td class="num">{{ $money($extra->amount) }}</td></tr>
                            @endforeach
                        @endif
                    @endforeach
                @endforeach
            @endforeach
            @if(count($pillar['programs']))<tr class="sub pillar-sub"><td colspan="7" style="text-align:right">Sub-Total</td><td class="num">{{ $money($pillar['total']) }}</td></tr>@endif
        @endforeach
            <tr class="grand"><td colspan="7">GRAND TOTAL</td><td class="num">{{ $money($grandTotal) }}</td></tr>
        </tbody>
    </table>

    <table class="summary">
        <caption>Summary per object of expenditure:</caption>
        <thead><tr><th style="text-align:left">Object of expenditure</th><th style="width:30%">Total Amount</th></tr></thead>
        <tbody>
            @foreach($summary as $row)<tr><td>{{ $row['title'] }}</td><td class="num">{{ $money($row['total']) }}</td></tr>@endforeach
            <tr class="grand"><td>GRAND TOTAL</td><td class="num">{{ $money($summary->sum('total')) }}</td></tr>
        </tbody>
    </table>

    <div class="signatures">
        @foreach($signatories as [$label, $name, $position])
            <div><div>{{ $label }}</div><div class="name">{{ mb_strtoupper((string) $name) }}</div><div class="position">{{ $position }}</div></div>
        @endforeach
    </div>
</main>
</body>
</html>
