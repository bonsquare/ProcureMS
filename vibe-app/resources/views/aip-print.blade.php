<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIP FY {{ $aip->fiscal_year }} · {{ $aip->school?->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }

        /* Every size is in rem and 1rem is 1/117.1 of the page width (the script sets it from the paper on screen), so the table lays out
           the same on any paper, on screen and in print, and the same rows fit a page. */
        html { font-size: var(--aip-fs, 9px); }
        @media print { html { font-size: calc(100vw / 117.1); } }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: .9rem; line-height: 1.35; }

        .paper { width: 100%; margin: 0; padding: 0; background: #fff; }

        .masthead { position: relative; text-align: center; min-height: 7.2rem; margin-bottom: 1rem; line-height: 1.45; }
        .masthead img { position: absolute; top: 0; height: 6rem; width: auto; }
        .masthead .left { left: 6%; } .masthead .right { right: 6%; }
        .masthead .small, .masthead .region, .masthead .division { font-size: .95rem; }
        .masthead .dept { font-size: 1.4rem; font-weight: bold; margin: .2rem 0 .3rem; line-height: 1.3; }
        .masthead .region { font-weight: bold; }
        .masthead .division { font-weight: bold; text-transform: uppercase; }

        .doc-title { text-align: center; margin: .4rem 0 1.4rem; line-height: 1.45; }
        .doc-title h1 { margin: 0; font-size: 1.4rem; letter-spacing: .06rem; }
        .doc-title .fy { margin: .3rem 0; font-size: 1.05rem; letter-spacing: .04rem; font-weight: bold; }
        .doc-title .school { margin: .2rem 0 0; font-size: 1.1rem; font-weight: bold; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; text-rendering: geometricPrecision; }
        th, td { border: 1px solid #8d8d95; padding: .3rem .4rem; font-size: .9rem; text-align: center; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #f4e4d6; color: #3b2f26; font-weight: bold; font-size: .85rem; padding: .9rem .3rem; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .fund-note { margin-top: .3rem; font-size: .85rem; font-weight: normal; line-height: 1.25; }
        td.pillar { font-weight: bold; }
        td.pillar .pillar-lines { display: flex; justify-content: center; align-items: center; gap: .35rem; }
        td.pillar span { display: block; line-height: 1.25; }
        td.group { background: #fcfcfd; } td.fund { background: #fafbfc; }
        td.num { white-space: nowrap; }
        td.pillar, td.group, td.fund, thead th, tr { break-inside: avoid; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6rem; margin-top: 3.6rem; }
        .signatures .label, .signatures .position { font-size: 1.1rem; }
        .signatures .name { margin: 4.4rem 14% 0; padding-bottom: .3rem; border-bottom: 1px solid #111; text-align: center; font-weight: bold; font-size: 1.2rem; text-transform: uppercase; }
        .signatures .position { margin-top: .4rem; text-align: center; }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
<script>
    /* Sets the size of 1rem from the width of the paper on screen (print sets it in CSS), before the shared print-pages script measures the rows. */
    (function () {
        var MM = 96 / 25.4;
        function size() {
            var style = document.getElementById('official-page-style');
            var spec = style && /size:\s*([\d.]+)mm\s+([\d.]+)mm;\s*margin:\s*([\d.]+)mm\s+([\d.]+)mm/.exec(style.textContent);
            if (spec) { document.documentElement.style.setProperty('--aip-fs', (((+spec[1] - 2 * spec[4]) * MM) / 117.1) + 'px'); }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var style = document.getElementById('official-page-style');
            if (style) { new MutationObserver(size).observe(style, { childList: true, characterData: true, subtree: true }); }
            window.addEventListener('beforeprint', size);
            size();
        });
    })();
</script>
@include('partials.print-pages')
</head>
<body>
@php
    $fmt = fn ($v) => (float) $v ? number_format($v, 2) : '-';
    $school = $aip->school;
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $region = $school?->region ?: $agency?->region_name;
    $division = $school?->division ?: ($agency?->division_name ?: $agency?->division_office);
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('aip.show', $aip), 'context' => 'Annual Implementation Plan · FY '.$aip->fiscal_year])

<main class="paper" data-official-page data-sheets data-page-footer="Annual Implementation Plan" data-doc="aip" data-paper="longbond" data-orientation="landscape" data-margin="10">
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

    <table data-paginate data-split-rows>
        <colgroup>
            <col style="width:6.5%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7.5%"><col style="width:7.5%"><col style="width:4.5%"><col style="width:4.5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:9.5%"><col style="width:7%"><col style="width:5%">
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
                // One row per activity, with the KRA-level columns (merged where identical, below) on every row.
                $lines = collect();
                // The template has one pillar for Resiliency and Well-Being.
                $pillarName = fn ($name) => in_array(strtolower(trim((string) $name)), ['resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being'], true) ? 'Resiliency and Well-Being' : (string) $name;
                foreach ($aip->kras->groupBy(fn ($k) => $pillarName($k->pillar))->flatten() as $kra) {
                    $acts = $kra->activities->isNotEmpty() ? $kra->activities : collect([null]);
                    foreach ($acts as $act) {
                        $lines->push(['kra' => $kra, 'activity' => $act, 'values' => [$pillarName($kra->pillar), $kra->kra, $kra->intermediate_outcome, $kra->strategy, $kra->five_point_agenda, $kra->program]]);
                    }
                }
                // Identical data merges down the rows: each column from the pillar to the program merges a run of rows whose value (and every value to
                // its left) is the same; the source of fund merges the same fund within one KRA.
                $span = [];
                $pillarGroup = [];
                $start = array_fill(0, 6, 0);
                foreach ($lines as $r => $line) {
                    for ($c = 0; $c < 6; $c++) {
                        $same = $r > 0 && array_slice($line['values'], 0, $c + 1) === array_slice($lines[$r - 1]['values'], 0, $c + 1);
                        if ($same) { $span[$c][$start[$c]]++; $span[$c][$r] = 0; } else { $start[$c] = $r; $span[$c][$r] = 1; }
                    }
                    $pillarGroup[$r] = $start[0];
                }
                $fundSpan = [];
                $fundStart = 0;
                foreach ($lines as $r => $line) {
                    $fund = $line['activity']?->source_of_fund;
                    $same = $r > 0 && $fund && $line['kra']->is($lines[$r - 1]['kra']) && $fund === $lines[$r - 1]['activity']?->source_of_fund;
                    if ($same) { $fundSpan[$fundStart]++; $fundSpan[$r] = 0; } else { $fundStart = $r; $fundSpan[$r] = 1; }
                }
                $pillarColors = ['access' => '#fbf3d5', 'equity' => '#fae3d2', 'quality' => '#dcebf6', 'resiliency and well-being' => '#e5dff1', 'enabling mechanism' => '#dbead7'];
                $fallback = ['#dff0ee', '#f3e9d6', '#e3e8f3', '#e9f0dc'];
                $pillarColor = fn ($name) => $pillarColors[strtolower(trim((string) $name))] ?? $fallback[abs(crc32((string) $name)) % count($fallback)];
            @endphp
            @foreach($lines as $r => $line)
                @php $a = $line['activity']; @endphp
                <tr data-pillar-group="{{ $pillarGroup[$r] }}">
                    @foreach($line['values'] as $c => $value)
                        @if($span[$c][$r] > 0)
                            @if($c === 0)
                                <td rowspan="{{ $span[$c][$r] }}" class="pillar" data-rows="{{ $span[$c][$r] }}" style="background: {{ $pillarColor($value) }}">@php
                                    // The pillar is written downward, a letter on each line; two or more words go in two columns of letters, split where they are the most even.
                                    $pillarText = strtoupper((string) $value);
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
                                @endphp<div class="pillar-lines" aria-label="{{ $value }}">@foreach($pillarLines as $line)<div>@foreach(mb_str_split($line) as $letter)<span>{{ $letter === ' ' ? "\u{00A0}" : $letter }}</span>@endforeach</div>@endforeach</div></td>
                            @else
                                <td rowspan="{{ $span[$c][$r] }}" class="group">{{ $value }}</td>
                            @endif
                        @endif
                    @endforeach
                    <td>{{ $a?->activity }}</td>
                    <td>{{ $a?->physical_target }}</td>
                    <td>{{ $a ? (\App\Models\AipActivity::timelineFor([$a->q1_amount, $a->q2_amount, $a->q3_amount, $a->q4_amount]) ?: $a->timeline) : '' }}</td>
                    <td class="num">{{ $a ? $fmt($a->q1_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q2_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q3_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q4_amount) : '' }}</td>
                    @if($fundSpan[$r] > 0)<td rowspan="{{ $fundSpan[$r] }}" class="fund">{{ $a?->source_of_fund }}</td>@endif
                    <td>{!! $a ? implode('<br>', array_map('e', $a->responsible_persons ?? [])) : '' !!}</td>
                    <td>{!! $a ? implode('<br>', array_map('e', $a->remarks_list ?? [])) : '' !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        @foreach([['Prepared by:', 'prepared_by'], ['Noted by:', 'noted_by'], ['Approved by:', 'approved_by']] as [$label, $key])
            <div>
                <div class="label">{{ \App\Support\SignatoryCaption::of($aip->{$key . '_role'}, $label) }}</div>
                <div class="name">{{ $aip->{$key . '_name'} ?: ' ' }}</div>
                <div class="position">{{ $aip->{$key . '_position'} }}</div>
            </div>
        @endforeach
    </div>
</main>
</body>
</html>
