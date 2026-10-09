<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIP {{ $startYear }}-{{ $startYear + 2 }} · {{ $school->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: "Times New Roman", Times, serif; font-size: 11px; line-height: 1.3; }
        .paper { width: 100%; min-width: 1180px; margin: 0 auto; padding: 24px 28px 36px; background: #fff; }

        .masthead { text-align: center; line-height: 1.45; margin-bottom: 4px; }
        .masthead img { height: 78px; width: auto; display: block; margin: 0 auto 4px; }
        .masthead .small { font-size: 9px; }
        .masthead .dept { font-size: 15px; font-weight: bold; }
        .masthead .region { font-size: 8px; font-weight: bold; }
        .masthead .division { font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; color: #1f3a8a; text-transform: uppercase; }

        .doc-title { text-align: center; margin: 6px 0 12px; line-height: 1.45; }
        .doc-title h1 { margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 11px; letter-spacing: .3px; }
        .doc-title .fy { margin: 2px 0; font-size: 11px; font-weight: bold; }
        .doc-title .school { margin: 2px 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #8d8d95; padding: 5px 5px; text-align: center; vertical-align: middle; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #f8cbad; color: #111; font-weight: bold; font-size: 10px; padding: 6px 5px; }
        thead th.physical { background: #ffe699; } thead th.financial { background: #c6e0b4; }
        thead th.physical-year { background: #ffe699; white-space: nowrap; padding: 6px 2px; } thead th.financial-year { background: #e2efda; white-space: nowrap; padding: 6px 2px; }
        .fund-note { font-size: 6.5pt; font-weight: normal; line-height: 1.2; }
        td.pillar { background: #8ea9db; font-weight: bold; font-size: 22px; letter-spacing: 2px; padding: 2px; }
        td.pillar span { display: block; line-height: 1.25; }
        td.pillar.small { font-size: 12px; }
        td.kra { font-size: 10px; } td.kra strong { font-weight: bold; }
        td.num { white-space: nowrap; font-size: 10px; }
        td.left { text-align: left; }
        .empty td { height: 22px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 40px; margin-top: 14px; font-size: 10px; }
        .signatures .label { font-size: 10px; }
        .signatures .name { margin-top: 28px; text-align: center; font-weight: bold; text-decoration: underline; font-size: 12px; text-transform: uppercase; }
        .signatures .position { text-align: center; font-size: 12px; }

        @media print {
            body { font-size: 9px; } .paper { min-width: 0; width: 100%; margin: 0; padding: 0; }
            thead th, td.pillar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th, td { padding: 3px 4px; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $fmt = fn ($v) => (float) $v ? number_format((float) $v, 2) : '';
    $qty = fn ($v) => $v === null || (float) $v == 0 ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    [$leftLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    $pillarOrder = ['access', 'equity', 'quality', 'resiliency', 'well-being', 'enabling mechanism'];
    $projects = $projects->sortBy(fn ($p) => [array_search(strtolower(trim((string) $p->pillar)), $pillarOrder) === false ? 99 : array_search(strtolower(trim((string) $p->pillar)), $pillarOrder), $p->id])->values();

    // One printed row per activity; a program without activities still prints one empty row.
    $rows = collect();
    foreach ($projects as $project) {
        $activities = $project->activities->isNotEmpty() ? $project->activities : collect([null]);
        foreach ($activities as $i => $activity) {
            $rows->push(['project' => $project, 'activity' => $activity, 'first' => $i === 0, 'span' => $activities->count()]);
        }
    }
    // Pillar cell spans every consecutive row of the same pillar.
    $pillarSpan = []; $start = 0;
    foreach ($rows as $r => $row) {
        $same = $r > 0 && strtolower(trim((string) $rows[$r - 1]['project']->pillar)) === strtolower(trim((string) $row['project']->pillar));
        if (! $same) { $start = $r; $pillarSpan[$r] = 1; } else { $pillarSpan[$start]++; $pillarSpan[$r] = 0; }
    }
    // Source of fund merges consecutive activities of one program that share it.
    $fundSpan = []; $start = 0;
    foreach ($rows as $r => $row) {
        $fund = $row['activity']?->source_of_fund;
        $same = $r > 0 && $fund && ! $row['first'] && $fund === $rows[$r - 1]['activity']?->source_of_fund;
        if (! $same) { $start = $r; $fundSpan[$r] = 1; } else { $fundSpan[$start]++; $fundSpan[$r] = 0; }
    }
    $signatories = [
        ['Prepared by:', $plan?->prepared_by_name ?: $school->school_head, $plan?->prepared_by_position ?: 'School Head/Team Leader'],
        ['Recommending Approval:', $plan?->recommended_by_name, $plan?->recommended_by_position ?: 'Chief, School Governance Operation Division'],
        ['Approved by:', $plan?->approved_by_name, $plan?->approved_by_position ?: 'Schools Division Superintendent'],
    ];
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning').'#sip'])

<main class="paper" data-official-page data-doc="sip" data-paper="longbond" data-orientation="landscape" data-margin="10">
    <header class="masthead">
        @if($leftLogo)<img class="official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif
        <div class="small">Republic of the Philippines</div>
        <div class="dept">Department of Education</div>
        @if($region)<div class="region">{{ strtoupper($region) }}</div>@endif
        @if($division)<div class="division">{{ $division }}</div>@endif
    </header>

    <div class="doc-title">
        <h1>SCHOOL IMPROVEMENT PLAN</h1>
        <p class="fy">FY {{ $startYear }}-{{ $startYear + 2 }}</p>
        <p class="school">{{ $school->name }}</p>
    </div>

    <table>
        <colgroup>
            <col style="width:4.5%"><col style="width:6%"><col style="width:9.5%"><col style="width:6.5%"><col style="width:8%"><col style="width:8%"><col style="width:10%">
            <col style="width:3.6%"><col style="width:3.6%"><col style="width:3.6%"><col style="width:5%"><col style="width:5%"><col style="width:5%">
            <col style="width:8%"><col style="width:8.7%"><col style="width:5%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2" style="font-size:6.5pt">Pillar (Access, Equity, Quality, Resiliency and Well-Being) / <u>Enabling Mechanism</u></th>
                <th rowspan="2">KRA</th><th rowspan="2" style="background:#f8cbad">DepEd Organizational Outcomes</th><th rowspan="2">Strategy (Processes)</th><th rowspan="2">5-Point Agenda</th><th rowspan="2">Specific Program / Project</th><th rowspan="2">Activity</th>
                <th colspan="3" class="physical">Physical Targets</th><th colspan="3" class="financial">Financial Target</th>
                <th rowspan="2">Source of Fund<div class="fund-note">(School: MOOE, SEF, IGP, others; Division Office Proper: MOOE-GASS, MOOE-HRTD, MOOE-Sub-ARO, School MOOE, SEF, others.)</div></th>
                <th rowspan="2">Responsible Person</th><th rowspan="2">Remarks<div class="fund-note">(Important Notes)</div></th>
            </tr>
            <tr>
                <th class="physical-year">YEAR 1</th><th class="physical-year">YEAR 2</th><th class="physical-year">YEAR 3</th>
                <th class="financial-year">YEAR 1</th><th class="financial-year">YEAR 2</th><th class="financial-year">YEAR 3</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r => $row)
                @php $p = $row['project']; $a = $row['activity']; @endphp
                <tr>
                    @if($pillarSpan[$r] > 0)
                        <td rowspan="{{ $pillarSpan[$r] }}" class="pillar {{ mb_strlen((string) $p->pillar) > 10 ? 'small' : '' }}">@foreach(mb_str_split(strtoupper((string) $p->pillar)) as $letter)<span>{{ $letter === ' ' ? ' ' : $letter }}</span>@endforeach</td>
                    @endif
                    @if($row['first'])
                        <td rowspan="{{ $row['span'] }}" class="kra">{{ $p->kra }}</td>
                        <td rowspan="{{ $row['span'] }}">{{ $p->organizational_outcome }}</td>
                        <td rowspan="{{ $row['span'] }}">{{ $p->strategy }}</td>
                        <td rowspan="{{ $row['span'] }}">{{ $p->five_point_agenda }}</td>
                        <td rowspan="{{ $row['span'] }}">{{ $p->project }}</td>
                    @endif
                    <td>{{ $a?->activity }}</td>
                    <td class="num">{{ $a ? $qty($a->physical_year1) : '' }}</td><td class="num">{{ $a ? $qty($a->physical_year2) : '' }}</td><td class="num">{{ $a ? $qty($a->physical_year3) : '' }}</td>
                    <td class="num">{{ $a ? $fmt($a->financial_year1) : '' }}</td><td class="num">{{ $a ? $fmt($a->financial_year2) : '' }}</td><td class="num">{{ $a ? $fmt($a->financial_year3) : '' }}</td>
                    @if($fundSpan[$r] > 0)<td rowspan="{{ $fundSpan[$r] }}">{{ $a?->source_of_fund }}</td>@endif
                    <td>{{ $a?->responsible_person }}</td>
                    <td>{{ $a?->remarks }}</td>
                </tr>
            @empty
                <tr class="empty"><td colspan="16" style="padding:24px;color:#666">No SIP programs recorded for FY {{ $startYear }}-{{ $startYear + 2 }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="signatures">
        @foreach($signatories as [$label, $name, $position])
            <div>
                <div class="label">{{ $label }}</div>
                <div class="name">{{ $name ?: ' ' }}</div>
                <div class="position">{{ $position }}</div>
            </div>
        @endforeach
    </div>
</main>
</body>
</html>
