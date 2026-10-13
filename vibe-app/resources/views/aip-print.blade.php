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

        .masthead { position: relative; text-align: center; min-height: 8.8rem; margin-bottom: 1.2rem; line-height: 1.45; }
        .masthead img { position: absolute; top: 0; height: 7rem; width: auto; }
        .masthead .left { left: 6%; } .masthead .right { right: 6%; }
        .masthead .small, .masthead .region, .masthead .division { font-size: 1.1rem; }
        .masthead .dept { font-size: 1.8rem; font-weight: bold; margin: .2rem 0 .3rem; line-height: 1.3; }
        .masthead .region { font-weight: bold; }
        .masthead .division { font-weight: bold; text-transform: uppercase; }

        .doc-title { text-align: center; margin: .4rem 0 1.4rem; line-height: 1.45; }
        .doc-title h1 { margin: 0; font-size: 1.8rem; letter-spacing: .08rem; }
        .doc-title .fy { margin: .3rem 0; font-size: 1.25rem; letter-spacing: .04rem; font-weight: bold; }
        .doc-title .school { margin: .2rem 0 0; font-size: 1.35rem; font-weight: bold; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; text-rendering: geometricPrecision; }
        th, td { border: 1px solid #8d8d95; padding: .3rem .4rem; font-size: .9rem; text-align: center; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; hyphens: none; }
        thead th { background: #f4e4d6; color: #3b2f26; font-weight: bold; font-size: .85rem; padding: .9rem .3rem; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .fund-note { margin-top: .3rem; font-size: .85rem; font-weight: normal; line-height: 1.25; }
        td.pillar { font-weight: bold; }
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

<main class="paper" data-official-page data-sheets data-doc="aip" data-paper="longbond" data-orientation="landscape" data-margin="10">
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
                // One row per activity, with the KRA-level columns repeated on every row.
                $lines = collect();
                // The template has one pillar for Resiliency and Well-Being.
                $pillarName = fn ($name) => in_array(strtolower(trim((string) $name)), ['resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being'], true) ? 'Resiliency and Well-Being' : (string) $name;
                foreach ($aip->kras->groupBy(fn ($k) => $pillarName($k->pillar))->flatten() as $kra) {
                    $acts = $kra->activities->isNotEmpty() ? $kra->activities : collect([null]);
                    foreach ($acts as $act) {
                        $lines->push(['kra' => $kra, 'activity' => $act, 'values' => [$pillarName($kra->pillar), $kra->kra, $kra->intermediate_outcome, $kra->strategy, $kra->five_point_agenda, $kra->program]]);
                    }
                }
                // Only the pillar merges, down each run of rows with the same pillar; every other column repeats its value on each row.
                $span = [];
                foreach ($lines as $r => $line) {
                    for ($c = 0; $c < 6; $c++) { $span[$c][$r] = 1; }
                }
                $pillarStart = 0;
                $pillarGroup = [];
                foreach ($lines as $r => $line) {
                    if ($r > 0 && $line['values'][0] === $lines[$r - 1]['values'][0]) { $span[0][$pillarStart]++; $span[0][$r] = 0; } else { $pillarStart = $r; }
                    $pillarGroup[$r] = $pillarStart;
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
                                <td rowspan="{{ $span[$c][$r] }}" class="pillar" data-rows="{{ $span[$c][$r] }}" style="background: {{ $pillarColor($value) }}">{{ $value }}</td>
                            @else
                                <td rowspan="{{ $span[$c][$r] }}" class="group">{{ $value }}</td>
                            @endif
                        @endif
                    @endforeach
                    <td>{{ $a?->activity }}</td>
                    <td>{{ $a?->physical_target }}</td>
                    <td>{{ $a?->timeline }}</td>
                    <td class="num">{{ $a ? $fmt($a->q1_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q2_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q3_amount) : '' }}</td><td class="num">{{ $a ? $fmt($a->q4_amount) : '' }}</td>
                    <td class="fund">{{ $a?->source_of_fund }}</td>
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
<script>
    /* The page is built page by page: the rows that fit one printed page go in one `.doc-page` block (the screen shows each block as its own
       sheet, the print breaks after each). The pillar cell is merged down its rows; when a pillar runs onto another page, the cell restarts at
       the top of that page. The shared print engine sets the paper (@page), so the rows are measured here at the size they will print. */
    (function () {
        var MM = 96 / 25.4;
        var busy = false;

        function paginate() {
            var style = document.getElementById('official-page-style');
            var page = document.querySelector('[data-official-page]');
            var table = page && page.querySelector('table');
            var spec = style && /size:\s*([\d.]+)mm\s+([\d.]+)mm;\s*margin:\s*([\d.]+)mm\s+([\d.]+)mm/.exec(style.textContent);
            if (!table || !table.tBodies[0] || !spec || busy) { return; }
            busy = true;
            var tbody = table.tBodies[0];
            var root = document.documentElement;

            // Back to the page as the server wrote it: no page blocks, one table with one merged cell per pillar.
            Array.prototype.forEach.call(page.querySelectorAll(':scope > .doc-page'), function (block) {
                while (block.firstChild) { page.insertBefore(block.firstChild, block); }
                block.remove();
            });
            Array.prototype.forEach.call(document.querySelectorAll('table[data-chunk]'), function (extra) {
                Array.prototype.slice.call(extra.tBodies[0].rows).forEach(function (row) { tbody.appendChild(row); });
                extra.remove();
            });
            Array.prototype.forEach.call(tbody.querySelectorAll('td[data-clone]'), function (cell) { cell.remove(); });
            Array.prototype.forEach.call(tbody.querySelectorAll('td.pillar[data-rows]'), function (cell) { cell.setAttribute('rowspan', cell.dataset.rows); });

            // Measure at the size the page will print: the width of the paper inside its margins, not the preview zoom.
            var kept = { zoom: page.style.zoom, width: page.style.width, preview: root.style.getPropertyValue('--preview-zoom') };
            var contentWidth = (+spec[1] - 2 * spec[4]) * MM;
            root.style.setProperty('--aip-fs', (contentWidth / 117.1) + 'px');
            root.style.setProperty('--preview-zoom', 1);
            page.style.zoom = 1;
            page.style.width = contentWidth + 'px';

            var rows = Array.prototype.slice.call(tbody.rows);
            var signatures = page.querySelector('.signatures');
            var signatureHeight = signatures ? signatures.getBoundingClientRect().height + parseFloat(getComputedStyle(signatures).marginTop) : 0;
            // 7% spare: print text wraps a little differently from the screen, and a page must never be fuller than the paper.
            var capacity = ((+spec[2] - 2 * spec[3]) * MM - 12) * 0.93;
            var headHeight = table.tHead ? table.tHead.getBoundingClientRect().height : 0;
            var position = table.getBoundingClientRect().top - page.getBoundingClientRect().top + headHeight;
            var starts = [];
            rows.forEach(function (row, index) {
                var height = row.getBoundingClientRect().height;
                if (index > 0 && position + height > capacity) { starts.push(index); position = headHeight; }
                position += height;
            });
            var lastUsed = position;

            page.style.zoom = kept.zoom;
            page.style.width = kept.width;
            if (kept.preview) { root.style.setProperty('--preview-zoom', kept.preview); } else { root.style.removeProperty('--preview-zoom'); }

            // A page that starts in the middle of a pillar gets its own copy of the pillar cell.
            starts.forEach(function (index) {
                var row = rows[index];
                var origin = rows.filter(function (other) { return other.dataset.pillarGroup === row.dataset.pillarGroup; })[0];
                if (origin === row) { return; }
                var cell = origin.querySelector('td.pillar[data-rows]');
                if (!cell) { return; }
                var copy = cell.cloneNode(true);
                copy.setAttribute('data-clone', '');
                copy.removeAttribute('data-rows');
                row.insertBefore(copy, row.firstElementChild);
            });
            rows.forEach(function (row, index) {
                var cell = row.querySelector('td.pillar');
                if (!cell || row.firstElementChild !== cell) { return; }
                var group = row.dataset.pillarGroup, length = 1;
                while (rows[index + length] && rows[index + length].dataset.pillarGroup === group && rows[index + length].firstElementChild.className.indexOf('pillar') === -1) { length++; }
                cell.setAttribute('rowspan', length);
            });

            // One block for each printed page: the first holds the heading and the first rows, every other one a table with its own heading row,
            // and the signatures go on the last page, or on a page of their own when they do not fit under the last rows.
            var blocks = [];
            var block = function () { var element = document.createElement('div'); element.className = 'doc-page'; blocks.push(element); return element; };
            var current = block();
            [page.querySelector('.masthead'), page.querySelector('.doc-title'), table].forEach(function (element) { if (element) { current.appendChild(element); } });
            starts.forEach(function (index, number) {
                var chunk = document.createElement('table');
                chunk.setAttribute('data-chunk', '');
                chunk.appendChild(table.querySelector('colgroup').cloneNode(true));
                chunk.appendChild(table.tHead.cloneNode(true));
                var body = document.createElement('tbody');
                rows.slice(index, starts[number + 1] === undefined ? rows.length : starts[number + 1]).forEach(function (row) { body.appendChild(row); });
                chunk.appendChild(body);
                current = block();
                current.appendChild(chunk);
            });
            if (signatures) {
                if (lastUsed + signatureHeight > capacity) { current = block(); }
                current.appendChild(signatures);
            }
            blocks.forEach(function (element) { page.appendChild(element); });
            busy = false;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var style = document.getElementById('official-page-style');
            if (style) { new MutationObserver(paginate).observe(style, { childList: true, characterData: true, subtree: true }); }
            window.addEventListener('beforeprint', paginate);
            window.addEventListener('load', paginate);
            paginate();
        });
    })();
</script>
</body>
</html>
