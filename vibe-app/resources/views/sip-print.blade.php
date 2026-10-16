<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIP {{ $startYear }}-{{ $startYear + 2 }} · {{ $school->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        /* Every size is in rem and 1rem is 1/117.1 of the page width (the script sets it from the paper on screen), so the table lays out
           the same on any paper, on screen and in print, and the same rows fit a page. */
        html { font-size: var(--sip-fs, 9px); }
        @media print { html { font-size: calc(100vw / 117.1); } }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: .9rem; line-height: 1.35; }
        .paper { width: 100%; margin: 0; padding: 0; background: #fff; }

        .masthead { text-align: center; margin-bottom: 1.2rem; line-height: 1.45; }
        .masthead img { height: 6rem; width: auto; display: block; margin: 0 auto .4rem; }
        .masthead .small, .masthead .region, .masthead .division { font-size: .95rem; }
        .masthead .dept { font-size: 1.4rem; font-weight: bold; margin: .2rem 0 .3rem; line-height: 1.3; }
        .masthead .region { font-weight: bold; }
        .masthead .division { font-weight: bold; text-transform: uppercase; }

        .doc-title { text-align: center; margin: .4rem 0 1.4rem; line-height: 1.45; }
        .doc-title h1 { margin: 0; font-size: 1.4rem; letter-spacing: .06rem; }
        .doc-title .fy { margin: .3rem 0; font-size: 1.05rem; letter-spacing: .04rem; font-weight: bold; }
        .doc-title .school { margin: .2rem 0 0; font-size: 1.1rem; font-weight: bold; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; text-rendering: geometricPrecision; }
        th, td { border: 1px solid #8d8d95; padding: .3rem .4rem; font-size: var(--sip-t, 1.2rem); text-align: center; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #f8cbad; color: #111; font-weight: bold; font-size: .85rem; padding: .9rem .3rem; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead th.physical { background: #ffe699; } thead th.financial { background: #c6e0b4; }
        thead th.physical-year { background: #ffe699; white-space: nowrap; padding: .9rem .2rem; } thead th.financial-year { background: #e2efda; white-space: nowrap; padding: .9rem .2rem; }
        .fund-note { margin-top: .3rem; font-size: .85rem; font-weight: normal; line-height: 1.25; }
        td.pillar { background: #8ea9db; font-weight: bold; font-size: var(--sip-t, 1.2rem); letter-spacing: .1rem; padding: .2rem; }
        td.pillar .pillar-lines { display: flex; justify-content: center; align-items: center; gap: .35rem; }
        td.pillar span { display: block; line-height: 1.25; }
        td.kra strong { font-weight: bold; }
        td.num { white-space: nowrap; }
        td.left { text-align: left; }
        .empty td { height: 2.2rem; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6rem; margin-top: 3.6rem; }
        .signatures .label, .signatures .position { font-size: 1.1rem; }
        .signatures .name { margin: 4.4rem 14% 0; padding-bottom: .3rem; border-bottom: 1px solid #111; text-align: center; font-weight: bold; font-size: 1.2rem; text-transform: uppercase; }
        .signatures .position { margin-top: .4rem; text-align: center; }

        @media print {
            thead th, td.pillar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
<script>
    /* Sets the size of 1rem from the width of the paper on screen (print sets it in CSS), and the size of the table text: the largest from 10pt down
       to 8pt (on the long bond paper) that keeps the pages the document needs at 8pt. This runs before the shared print-pages script measures the rows. */
    (function () {
        var MM = 96 / 25.4;
        var REM_PER_PT = 4 / 3 / 10.012;
        var fitting = false;
        var seen = '';
        var root = document.documentElement;
        function size() {
            var style = document.getElementById('official-page-style');
            var spec = style && /size:\s*([\d.]+)mm\s+([\d.]+)mm;\s*margin:\s*([\d.]+)mm\s+([\d.]+)mm/.exec(style.textContent);
            if (spec) { root.style.setProperty('--sip-fs', (((+spec[1] - 2 * spec[4]) * MM) / 117.1) + 'px'); }
        }
        function pagesAt(pt) {
            root.style.setProperty('--sip-t', (pt * REM_PER_PT) + 'rem');
            window.dispatchEvent(new Event('beforeprint'));
            return document.querySelectorAll('[data-official-page] > .doc-page').length;
        }
        function fit() {
            if (fitting) { size(); return; }
            fitting = true;
            try {
                size();
                seen = (document.getElementById('official-page-style') || {}).textContent;
                var steps = [];
                for (var pt = 8; pt <= 10; pt += 0.25) { steps.push(pt); }
                var needed = pagesAt(8);
                var low = 0, high = steps.length - 1;
                while (low < high) {
                    var middle = Math.ceil((low + high) / 2);
                    if (pagesAt(steps[middle]) <= needed) { low = middle; } else { high = middle - 1; }
                }
                pagesAt(steps[low]);
            } finally {
                fitting = false;
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var style = document.getElementById('official-page-style');
            // Only a change of paper fits again (the print engine may rewrite the same rule).
            if (style) { new MutationObserver(function () { if (style.textContent !== seen) { fit(); } }).observe(style, { childList: true, characterData: true, subtree: true }); }
            window.addEventListener('beforeprint', fit);
            window.addEventListener('load', fit);
            fit();
        });
    })();
</script>
@include('partials.print-pages')
</head>
<body>
@php
    $fmt = fn ($v) => (float) $v ? number_format((float) $v, 2) : '';
    $qty = fn ($v) => $v === null || (float) $v == 0 ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    [$leftLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    // The template has one pillar for Resiliency and Well-Being, whichever of the two names the program was saved with.
    $pillarName = fn ($name) => in_array(strtolower(trim((string) $name)), ['resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being'], true) ? 'Resiliency and Well-Being' : (string) $name;
    $pillarOrder = ['access', 'equity', 'quality', 'resiliency and well-being', 'enabling mechanism'];
    $projects = $projects->sortBy(fn ($p) => [array_search(strtolower(trim($pillarName($p->pillar))), $pillarOrder) === false ? 99 : array_search(strtolower(trim($pillarName($p->pillar))), $pillarOrder), $p->id])->values();

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
        $same = $r > 0 && strtolower(trim($pillarName($rows[$r - 1]['project']->pillar))) === strtolower(trim($pillarName($row['project']->pillar)));
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
        [\App\Support\SignatoryCaption::of($plan?->prepared_by_role, 'Prepared by:'), $plan?->prepared_by_name ?: $school->school_head, $plan?->prepared_by_position ?: 'School Head/Team Leader'],
        [\App\Support\SignatoryCaption::of($plan?->recommended_by_role, 'Recommending Approval:'), $plan?->recommended_by_name, $plan?->recommended_by_position ?: 'Chief, School Governance Operation Division'],
        [\App\Support\SignatoryCaption::of($plan?->approved_by_role, 'Approved by:'), $plan?->approved_by_name, $plan?->approved_by_position ?: 'Schools Division Superintendent'],
    ];
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning').'#sip', 'context' => 'School Improvement Plan'])

<main class="paper" data-official-page data-sheets data-page-footer="School Improvement Plan" data-doc="sip" data-paper="longbond" data-orientation="landscape" data-margin="10">
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

    <table data-paginate data-split-rows>
        <colgroup>
            <col style="width:4.5%"><col style="width:7%"><col style="width:8.5%"><col style="width:6.5%"><col style="width:8%"><col style="width:8%"><col style="width:10%">
            <col style="width:3.6%"><col style="width:3.6%"><col style="width:3.6%"><col style="width:5%"><col style="width:5%"><col style="width:5%">
            <col style="width:8%"><col style="width:8.7%"><col style="width:5%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2" style="font-size:.72rem; padding-left:.1rem; padding-right:.1rem">Pillar (Access, Equity, Quality, Resiliency and Well-Being) / Enabling Mechanism</th>
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
                        <td rowspan="{{ $pillarSpan[$r] }}" class="pillar {{ mb_strlen($pillarName($p->pillar)) > 20 ? 'small xsmall' : (mb_strlen($pillarName($p->pillar)) > 10 ? 'small' : '') }}">@php
                            // A name of two or more words is written in two lines (two columns of letters), split where the lines are the most even.
                            $pillarText = strtoupper($pillarName($p->pillar));
                            $words = preg_split('/\s+/', trim($pillarText));
                            $pillarLines = [$pillarText];
                            if (count($words) > 1) {
                                $best = null;
                                for ($cut = 1; $cut < count($words); $cut++) {
                                    $first = implode(' ', array_slice($words, 0, $cut));
                                    $second = implode(' ', array_slice($words, $cut));
                                    $longest = max(mb_strlen($first), mb_strlen($second));
                                    if ($best === null || $longest < $best[0]) { $best = [$longest, [$first, $second]]; }
                                }
                                $pillarLines = $best[1];
                            }
                        @endphp<div class="pillar-lines">@foreach($pillarLines as $line)<div>@foreach(mb_str_split($line) as $letter)<span>{{ $letter === ' ' ? "\u{00A0}" : $letter }}</span>@endforeach</div>@endforeach</div></td>
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
                <tr class="empty"><td colspan="16" style="padding:2.4rem;color:#666">No SIP programs recorded for FY {{ $startYear }}-{{ $startYear + 2 }}.</td></tr>
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
