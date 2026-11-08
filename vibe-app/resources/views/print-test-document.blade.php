<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print test document · {{ $scenarioLabel }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }
        .paper { width: 100%; margin: 0; padding: 0; background: #fff; }
        .doc-title { text-align: center; margin: 4px 0 10px; }
        .doc-title h1 { margin: 0; font-size: 15px; letter-spacing: .4px; }
        .doc-title p { margin: 2px 0 0; font-size: 11px; }
        h2 { margin: 14px 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.items th, table.items td { border: 1px solid #222; padding: 3px 6px; vertical-align: top; overflow-wrap: anywhere; }
        table.items th { background: #f0f0f0; text-align: center; }
        table.items td.num { text-align: right; white-space: nowrap; }
        table.items td.center { text-align: center; }
        table.items tr.total td { font-weight: bold; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 26px 30px; margin-top: 24px; }
        .signatures .name { margin-top: 34px; text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signatures .position { text-align: center; }
        @media print {
            thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
@include('partials.official-toolbar', ['closeUrl' => route('school-settings', ['tab' => 'printing']), 'context' => 'Print test document · '.$scenarioLabel])

<main class="paper" data-official-page data-sheets data-page-footer="Print test document · {{ $scenarioLabel }}" data-doc="test" data-paper="{{ $paper }}" data-orientation="{{ $orientation }}" data-margin="10">
    @if(count($tables) > 1)<div class="test-section">@endif
    <div class="doc-title">
        <h1>PRINT TEST DOCUMENT</h1>
        <p>{{ $scenarioLabel }} · {{ $rowCount }} rows · printed on the same engine as every official form</p>
    </div>

    @foreach($tables as $number => $table)
        @if(count($tables) > 1)<h2>Table {{ $number + 1 }}: {{ $table['title'] }}</h2>@endif
        <table class="items" data-paginate @if($table['split']) data-split-rows @endif>
            <colgroup><col style="width:7%"><col style="width:45%"><col style="width:10%"><col style="width:12%"><col style="width:12%"><col style="width:14%"></colgroup>
            <thead>
                <tr><th rowspan="2">No.</th><th rowspan="2">Description</th><th colspan="2">Quantity</th><th colspan="2">Amount</th></tr>
                <tr><th>Unit</th><th>Qty</th><th>Unit cost</th><th>Total</th></tr>
            </thead>
            <tbody>
                @foreach($table['rows'] as $row)
                    <tr><td class="center">{{ $row['no'] }}</td><td>[T{{ $number + 1 }}-R{{ $row['no'] }}] {{ $row['text'] }}</td><td class="center">{{ $row['unit'] }}</td><td class="num">{{ $row['qty'] }}</td><td class="num">{{ number_format($row['cost'], 2) }}</td><td class="num">{{ number_format($row['qty'] * $row['cost'], 2) }}</td></tr>
                @endforeach
                <tr class="total"><td colspan="5">Total</td><td class="num">{{ number_format(collect($table['rows'])->sum(fn ($row) => $row['qty'] * $row['cost']), 2) }}</td></tr>
            </tbody>
        </table>
    @endforeach

    @if($signatures)
        <div class="signatures">
            @foreach(['Prepared by', 'Checked by', 'Approved by'] as $role)
                <div><div>{{ $role }}:</div><div class="name">Sample Person</div><div class="position">Position</div></div>
            @endforeach
        </div>
    @endif
    @if(count($tables) > 1)</div>@endif
</main>
</body>
</html>
