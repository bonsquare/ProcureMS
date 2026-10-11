<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APP-CSE FY {{ $plan->fiscal_year }} · {{ $plan->school?->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 8px; line-height: 1.25; }
        .paper { width: 100%; min-width: 1100px; margin: 0 auto; padding: 14px 18px 24px; background: #fff; }

        .form-title { text-align: center; font-size: 8px; font-weight: bold; }
        h1 { margin: 2px 0 8px; text-align: center; font-size: 11px; letter-spacing: .3px; }
        .instructions { margin: 0 0 8px; }
        .instr { margin: 0 0 1px; font-size: 7.4px; line-height: 1.3; white-space: pre-wrap; }
        .instr.b { font-weight: bold; }
        .instr.i { font-style: italic; }
        .instr.u { text-decoration: underline; }
        .instr b, .instr i { font-weight: bold; font-style: italic; }
        .head-grid { display: grid; grid-template-columns: 1.4fr 1fr 1.1fr; gap: 6px 24px; margin-bottom: 8px; font-size: 8.5px; }
        .head-grid b { font-weight: bold; }

        table.app { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.app th, table.app td { border: .5pt solid #222; padding: 1px 2px; vertical-align: middle; overflow-wrap: anywhere; hyphens: none; font-size: 7px; }
        table.app thead th { background: #e8edf4; font-size: 6.8px; text-align: center; }
        table.app td.c { text-align: center; }
        table.app td.n { text-align: right; white-space: nowrap; }
        table.app td.d { text-align: left; }
        table.app tr.part-row td { background: #d9e2ef; font-weight: bold; font-size: 8px; text-transform: uppercase; }
        table.app tr.group-row td { background: #eef2f7; font-weight: bold; }
        table.app tr.category-row td { background: #f6f6f6; font-weight: bold; }
        table.app tr.sub-total td, table.app tr.totals-row td { font-weight: bold; background: #f1f4f8; }
        table.app td.label { text-align: left; font-weight: bold; font-size: 7.6px; }
        table.app td.words { font-weight: normal; font-style: italic; }

        .footnote { margin: 6px 0 0 4px; font-size: 7.5px; font-style: italic; }
        .warranty { margin: 10px 0 0; font-size: 8.5px; line-height: 1.4; text-align: justify; }
        .signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; margin-top: 18px; font-size: 8.5px; break-inside: avoid; }
        .signatures .caption { font-weight: bold; min-height: 24px; }
        .signatures .name { margin-top: 26px; text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; min-height: 12px; }
        .signatures .role { text-align: center; }
        .prepared-date { margin-top: 10px; font-size: 8.5px; }

        @media print {
            .paper { min-width: 0; width: 100%; margin: 0; padding: 0; }
            table.app thead { display: table-header-group; }
            table.app tr { break-inside: avoid; }
            table.app thead th, table.app tr.part-row td, table.app tr.group-row td, table.app tr.category-row td, table.app tr.sub-total td, table.app tr.totals-row td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .signatures, .warranty { break-inside: avoid; }
        }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    // The workbook's column widths (A to AA), so the columns keep the proportions of the official form.
    $widths = [5.66, 18.66, 32, 11.44, 7.44, 7.44, 7.44, 7.44, 11.44, 7.44, 7.44, 7.44, 7.44, 11.44, 7.44, 8.33, 7.44, 7.44, 11.44, 7.44, 7.44, 7.44, 9.33, 11.44, 11, 12.11, 28.44];
    $widthSum = array_sum($widths);
    $monthLabels = [['Jan', 'Feb', 'Mar'], ['April', 'May', 'June'], ['July', 'Aug', 'Sept'], ['Oct', 'Nov', 'Dec']];
    $head = fn ($value) => $value !== null && trim((string) $value) !== '' ? $value : '';

    /** The cells of one catalog or Part II line: 12 months with the quarter quantity and amount after each three, then total quantity, price and total amount. */
    $cells = function ($item, $price) use ($money, $qty) {
        $months = $item ? $item->months() : array_fill(0, 12, 0.0);
        $html = '';
        foreach (range(1, 4) as $quarter) {
            foreach (array_slice($months, ($quarter - 1) * 3, 3) as $month) {
                $html .= '<td class="n">'.e($qty($month)).'</td>';
            }
            $html .= '<td class="n">'.e($qty($item ? $item->quarterQuantity($quarter) : 0)).'</td><td class="n">'.e($money($item ? $item->quarterAmount($quarter) : 0)).'</td>';
        }

        return $html.'<td class="n">'.e($qty($item ? $item->totalQuantity() : 0)).'</td><td class="n">'.($price !== null ? e($money($price)) : '').'</td><td class="n">'.e($money($item ? $item->totalAmount() : 0)).'</td>';
    };
    $number = 0;
@endphp

@include('partials.official-toolbar', ['closeUrl' => route('planning.app-cse.show', $plan), 'context' => 'APP-CSE · FY '.$plan->fiscal_year])

<main class="paper" data-official-page data-doc="app-cse" data-paper="longbond" data-orientation="landscape" data-margin="8">
    <div class="form-title">APP-CSE 2020 FORM - 10 September 2019</div>
    <h1>ANNUAL PROCUREMENT PLAN-COMMON SUPPLIES AND EQUIPMENT (APP-CSE) {{ $plan->fiscal_year }} FORM</h1>

    @include('partials.app-cse-instructions')

    <div class="head-grid">
        <div><div><b>Department/Bureau/Office:</b> {{ $plan->office_name }}</div><div><b>Region:</b> {{ $plan->region }}</div><div><b>Address:</b> {{ $plan->address }}</div></div>
        <div><div><b>Agency Account Code:</b> {{ $plan->account_code }}</div><div><b>Organization Type:</b> {{ $plan->organization_type }}</div></div>
        <div><div><b>Contact Person:</b> {{ $plan->contact_person }}</div><div><b>Position:</b> {{ $plan->contact_position }}</div><div><b>E-mail :</b> {{ $plan->contact_email }}</div><div><b>Telephone/Mobile Nos:</b> {{ $plan->contact_phone }}</div></div>
    </div>

    <table class="app">
        <colgroup>@foreach($widths as $width)<col style="width: {{ round($width / $widthSum * 100, 3) }}%">@endforeach</colgroup>
        <thead>
            <tr>
                <th colspan="3" rowspan="2">Item &amp; Specifications</th>
                <th rowspan="2">Unit of Measure</th>
                <th colspan="20">Monthly Quantity Requirement</th>
                <th rowspan="2">Total Quantity<br>for the year</th>
                <th rowspan="2">Price Catalogue</th>
                <th rowspan="2">Total Amount<br>for the year</th>
            </tr>
            <tr>
                @foreach($monthLabels as $index => $labels)
                    @foreach($labels as $label)<th>{{ $label }}</th>@endforeach
                    <th>Q{{ $index + 1 }}</th><th>Q{{ $index + 1 }} AMOUNT</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="part-row"><td colspan="27">PART I. AVAILABLE AT PROCUREMENT SERVICE STORES</td></tr>
            @php $lastGroup = null; $lastCategory = null; @endphp
            @foreach($catalog as $entry)
                @if($entry->group_heading !== $lastGroup && $entry->group_heading !== null)<tr class="group-row"><td colspan="27">{{ $entry->group_heading }}</td></tr>@endif
                @if($entry->category !== $lastCategory)<tr class="category-row"><td colspan="27">{{ $entry->category }}</td></tr>@endif
                @php
                    $lastGroup = $entry->group_heading;
                    $lastCategory = $entry->category;
                    $line = $byCatalog->get($entry->id);
                    $price = $line ? (float) $line->unit_price : ($entry->price !== null ? (float) $entry->price : null);
                @endphp
                <tr class="catalog-row"><td class="c">{{ $entry->sort_order }}</td><td>{{ $entry->code }}</td><td class="d">{{ $entry->description }}</td><td class="c">{{ $entry->unit }}</td>{!! $cells($line, $price) !!}</tr>
            @endforeach

            <tr class="part-row"><td colspan="27">PART II. OTHER ITEMS NOT AVALABLE AT PS BUT REGULARLY PURCHASED FROM OTHER SOURCES (Note: Please indicate price of items)</td></tr>
            @foreach($partTwo->groupBy('category') as $category => $group)
                <tr class="category-row"><td colspan="27">{{ $category }}</td></tr>
                @foreach($group as $line)
                    @php $number++; @endphp
                    <tr class="part-two-row"><td class="c">{{ $number }}</td><td></td><td class="d">{{ $line->description }}</td><td class="c">{{ $line->unit }}</td>{!! $cells($line, (float) $line->unit_price) !!}</tr>
                @endforeach
                <tr class="sub-total"><td colspan="4" class="d">sub total</td>
                    @foreach(range(1, 4) as $quarter)<td colspan="3"></td><td class="n">{{ $qty($group->sum(fn ($line) => $line->quarterQuantity($quarter))) }}</td><td class="n">{{ $money($group->sum(fn ($line) => $line->quarterAmount($quarter))) }}</td>@endforeach
                    <td class="n">{{ $qty($group->sum(fn ($line) => $line->totalQuantity())) }}</td><td></td><td class="n">{{ $money($group->sum(fn ($line) => $line->totalAmount())) }}</td></tr>
            @endforeach

            <tr class="totals-row">
                <td colspan="24" class="label">A. TOTAL </td>
                <td colspan="3" class="n">{{ $money($totals['a']) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="24" class="label">B. ADDITIONAL PROVISION FOR INFLATION (10% of TOTAL)</td>
                <td colspan="3" class="n">{{ $money($totals['b']) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="24" class="label">C. ADDITIONAL PROVISION FOR TRANSPORT AND FREIGHT COST </td>
                <td colspan="3" class="n">{{ $money($totals['c']) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="24" class="label">D. GRAND TOTAL (A + B+ C)</td>
                <td colspan="3" class="n">{{ $money($totals['d']) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="24" class="label">E. APPROVED BUDGET BY THE AGENCY HEAD<br>In Figures and Words: <span class="words">{{ $plan->approved_budget_words }}</span></td>
                <td colspan="3" class="n">{{ $totals['e'] !== null ? $money($totals['e']) : '' }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="27" class="label">F. MONTHLY CASH REQUIREMENTS</td>
            </tr>
            @foreach([['G.1 Available at Procurement Service Stores', $totals['g1']], ['G.2 Other Items not available at PS but regulary purchased from other sources', $totals['g2']], ['TOTAL MONTHLY CASH REQUIREMENTS', $totals['f']]] as [$label, $row])
                <tr class="totals-row">
                    <td colspan="7" class="label">{{ $label }}</td>
                    <td colspan="2" class="n">{{ $money($row['q1']) }}</td><td colspan="3"></td>
                    <td colspan="2" class="n">{{ $money($row['q2']) }}</td><td colspan="3"></td>
                    <td colspan="2" class="n">{{ $money($row['q3']) }}</td><td colspan="3"></td>
                    <td colspan="2" class="n">{{ $money($row['q4']) }}</td>
                    <td colspan="3" class="n">{{ $money($row['year']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footnote">*Agency must put the monthly requirement for air tickets both local and international.</p>
    <p class="warranty">We hereby warrant that the total amount reflected in this Annual Supplies/ Equipment Procurement Plan to procure the listed common-use supplies, materials and equipment has been included in or is within our approved budget for the year.</p>

    <div class="signatures">
        <div><div class="caption">{{ \App\Support\SignatoryCaption::of($plan->prepared_by_role, 'Prepared by:') }}</div><div class="name">{{ $plan->prepared_by_name ?: $preparedBy?->name }}</div><div class="role">{{ $plan->prepared_by_position ?: 'Property/Supply Officer' }}</div></div>
        <div><div class="caption">{{ \App\Support\SignatoryCaption::of($plan->noted_by_role, 'NOTED:') }}</div><div class="name">{{ $plan->noted_by_name ?: $notedBy?->name }}</div><div class="role">{{ $plan->noted_by_position ?: 'School Head' }}</div></div>
        <div><div class="caption">{{ \App\Support\SignatoryCaption::of($plan->certified_by_role, 'Certified Funds Available / Certified Appropriate Funds Available:') }}</div><div class="name">{{ $plan->certified_by_name ?: $certifiedBy?->name }}</div><div class="role">{{ $plan->certified_by_position ?: 'Accountant / Local Budget Officer' }}</div></div>
        <div><div class="caption">{{ \App\Support\SignatoryCaption::of($plan->agency_head_role, 'Approved by:') }}</div><div class="name">{{ $plan->agency_head_name }}</div><div class="role">{{ $plan->agency_head_position ?: 'Head of Office/Agency' }}</div></div>
    </div>
    <div class="prepared-date"><b>Date Prepared:</b> {{ $plan->date_prepared?->format('F d, Y') }}</div>
</main>
</body>
</html>
