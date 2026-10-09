<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIP FY {{ $aip->fiscal_year }} · {{ $aip->school?->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }

        .paper { width: 100%; min-width: 1180px; margin: 0 auto; padding: 30px 34px 40px; background: #fff; }

        .masthead { position: relative; text-align: center; min-height: 112px; margin-bottom: 26px; line-height: 1.7; }
        .masthead img { position: absolute; top: 0; height: 84px; width: auto; }
        .masthead .left { left: 6%; } .masthead .right { right: 6%; }
        .masthead .small { font-size: 11px; }
        .masthead .dept { font-size: 18px; font-weight: bold; margin: 4px 0 6px; line-height: 1.4; }
        .masthead .region { font-weight: bold; font-size: 11px; }
        .masthead .division { font-weight: bold; font-size: 11px; text-transform: uppercase; }

        .doc-title { text-align: center; margin: 6px 0 26px; line-height: 1.6; }
        .doc-title h1 { margin: 0; font-size: 18px; letter-spacing: .8px; }
        .doc-title .fy { margin: 6px 0; font-size: 12.5px; letter-spacing: .4px; font-weight: bold; }
        .doc-title .school { margin: 4px 0 0; font-size: 13.5px; font-weight: bold; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #8d8d95; padding: 6px 6px; text-align: center; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #f4e4d6; color: #3b2f26; font-weight: bold; font-size: 10.5px; padding: 9px 8px; }
        .fund-note { margin-top: 3px; font-size: 8.5px; font-weight: normal; line-height: 1.25; }
        td.pillar { font-weight: bold; }
        td.group { background: #fcfcfd; } td.fund { background: #fafbfc; }
        td.num { white-space: nowrap; }

        .summary-wrap { margin-top: 22px; display: flex; justify-content: flex-start; }
        .summary { width: 62%; min-width: 520px; }
        .summary th { background: #f4e4d6; color: #3b2f26; }
        .summary td:first-child { text-align: left; font-weight: bold; }
        .summary tr.total td { font-weight: bold; background: #f4f4f6; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 60px; margin-top: 36px; }
        .signatures .label { font-size: 11px; }
        .signatures .name { margin: 44px 14% 0; padding-bottom: 3px; border-bottom: 1px solid #111; text-align: center; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .signatures .position { margin-top: 4px; text-align: center; font-size: 11px; }

        @media print {
            body { background: #fff; font-size: 9px; } .paper { min-width: 0; }
            .toolbar-unused { display: none; }
            .paper { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            thead th { font-size: 8.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th, td { padding: 3px 4px; }
            .masthead img { height: 70px; }
            td.pillar, td.group, td.fund, thead th, .summary th, .summary tr.total td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
        }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $summary = $aip->fundSummary();
    $fmt = fn ($v) => (float) $v ? number_format($v, 2) : '-';
    $school = $aip->school;
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $region = $school?->region ?: $agency?->region_name;
    $division = $school?->division ?: ($agency?->division_name ?: $agency?->division_office);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('aip.show', $aip)])

<main class="paper" data-official-page data-doc="aip" data-paper="longbond" data-orientation="landscape" data-margin="10">
    <header class="masthead">
        @if($leftLogo)<img class="left official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif
        @if($rightLogo)<img class="right official-logo" src="{{ $rightLogo }}" alt="" onerror="this.remove()">@endif
        <div class="small">Republic of the Philippines</div>
        <div class="dept">Department of Education</div>
        @if($region)<div class="region">{{ strtoupper($region) }}</div>@endif
        @if($division)<div class="division">{{ $division }}</div>@endif
    </header>

    <div class="doc-title">
        <h1>ANNUAL IMPLEMENTATION PLAN</h1>
        <p class="fy">FISCAL YEAR {{ $aip->fiscal_year }}</p>
        <p class="school">{{ $school?->name }}</p>
    </div>

    <table>
        <colgroup>
            <col style="width:6%"><col style="width:7%"><col style="width:7%"><col style="width:6%"><col style="width:7%"><col style="width:7%"><col style="width:8%">
            <col style="width:4%"><col style="width:5%"><col style="width:4.5%"><col style="width:4.5%"><col style="width:4.5%"><col style="width:4.5%">
            <col style="width:13%"><col style="width:7%"><col style="width:5%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">Pillar / Enabling Mechanism</th><th rowspan="2">KRA</th><th rowspan="2">Intermediate Outcome</th><th rowspan="2">Strategy</th><th rowspan="2">5-Point Agenda</th><th rowspan="2">Specific Program / Project</th><th rowspan="2">Activity</th>
                <th rowspan="2">Physical Targets</th><th rowspan="2">Specific Timeline</th>
                <th colspan="4">Financial Target (Monthly Disbursement Program)</th>
                <th rowspan="2">Source of Fund<div class="fund-note">(School: MOOE, SEF, IGP, others; Division Office Proper: MOOE-GASS, MOOE-HRTD, MOOE-Sub-ARO, School MOOE, SEF, others.)</div></th><th rowspan="2">Responsible Person</th><th rowspan="2">Remarks</th>
            </tr>
            <tr><th>1st Quarter</th><th>2nd Quarter</th><th>3rd Quarter</th><th>4th Quarter</th></tr>
        </thead>
        <tbody>
            @php
                // One row per activity, with the KRA-level columns repeated on every row.
                $lines = collect();
                foreach ($aip->kras->groupBy(fn ($k) => $k->pillar ?: '')->flatten() as $kra) {
                    $acts = $kra->activities->isNotEmpty() ? $kra->activities : collect([null]);
                    foreach ($acts as $act) {
                        $lines->push(['kra' => $kra, 'activity' => $act, 'values' => [$kra->pillar, $kra->kra, $kra->intermediate_outcome, $kra->strategy, $kra->five_point_agenda, $kra->program]]);
                    }
                }
                // Only the source of fund merges; every other column repeats its value on each row.
                $span = [];
                foreach ($lines as $r => $line) {
                    for ($c = 0; $c < 6; $c++) { $span[$c][$r] = 1; }
                }
                // Consecutive rows with the same source of fund share one merged cell.
                $fundSpan = [];
                $fundStart = 0;
                foreach ($lines as $r => $line) {
                    $fund = $line['activity']?->source_of_fund;
                    $sameFund = $r > 0 && $fund && $fund === $lines[$r - 1]['activity']?->source_of_fund;
                    if (!$sameFund) { $fundStart = $r; $fundSpan[$r] = 1; } else { $fundSpan[$fundStart]++; $fundSpan[$r] = 0; }
                }
                $pillarColors = ['access' => '#fbf3d5', 'equity' => '#fae3d2', 'quality' => '#dcebf6', 'resiliency' => '#f5dfe5', 'well-being' => '#e5dff1', 'enabling mechanism' => '#dbead7'];
                $fallback = ['#dff0ee', '#f3e9d6', '#e3e8f3', '#e9f0dc'];
                $pillarColor = fn ($name) => $pillarColors[strtolower(trim((string) $name))] ?? $fallback[abs(crc32((string) $name)) % count($fallback)];
            @endphp
            @foreach($lines as $r => $line)
                @php $a = $line['activity']; @endphp
                <tr>
                    @foreach($line['values'] as $c => $value)
                        @if($span[$c][$r] > 0)
                            @if($c === 0)
                                <td rowspan="{{ $span[$c][$r] }}" class="pillar" style="background: {{ $pillarColor($value) }}">{{ $value }}</td>
                            @else
                                <td rowspan="{{ $span[$c][$r] }}" class="group">{{ $value }}</td>
                            @endif
                        @endif
                    @endforeach
                    <td>{{ $a?->activity }}</td>
                    <td>{{ $a?->physical_target }}</td>
                    <td>{{ $a?->timeline }}</td>
                    <td class="num">{{ $a ? $fmt($a->q1_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q2_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q3_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q4_amount) : '' }}</td>
                    @if($fundSpan[$r] > 0)<td rowspan="{{ $fundSpan[$r] }}" class="fund">{{ $a?->source_of_fund }}</td>@endif
                    <td>{!! $a ? implode('<br>', array_map('e', $a->responsible_persons ?? [])) : '' !!}</td>
                    <td>{!! $a ? implode('<br>', array_map('e', $a->remarks_list ?? [])) : '' !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary">
            <colgroup><col style="width:24%"><col style="width:15%"><col style="width:15%"><col style="width:15%"><col style="width:15%"><col style="width:16%"></colgroup>
            <thead><tr><th>Source of Fund</th><th>1st Quarter</th><th>2nd Quarter</th><th>3rd Quarter</th><th>4th Quarter</th><th>TOTAL</th></tr></thead>
            <tbody>
                @foreach($summary as $fund => $row)
                    <tr><td>{{ $fund }}</td><td>{{ $fmt($row['q1']) }}</td><td>{{ $fmt($row['q2']) }}</td><td>{{ $fmt($row['q3']) }}</td><td>{{ $fmt($row['q4']) }}</td><td><strong>{{ number_format($row['total'], 2) }}</strong></td></tr>
                @endforeach
                <tr class="total"><td>TOTAL</td><td>{{ $fmt($summary->sum('q1')) }}</td><td>{{ $fmt($summary->sum('q2')) }}</td><td>{{ $fmt($summary->sum('q3')) }}</td><td>{{ $fmt($summary->sum('q4')) }}</td><td>{{ number_format($summary->sum('total'), 2) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="signatures">
        @foreach([['Prepared by:', 'prepared_by'], ['Noted by:', 'noted_by'], ['Approved by:', 'approved_by']] as [$label, $key])
            <div>
                <div class="label">{{ $label }}</div>
                <div class="name">{{ $aip->{$key . '_name'} ?: ' ' }}</div>
                <div class="position">{{ $aip->{$key . '_position'} }}</div>
            </div>
        @endforeach
    </div>
</main>
</body>
</html>
