<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request {{ $procurementRequest->request_number }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; color: #111; }
        body { font-family: "Times New Roman", Times, serif; }
        .print-actions { width: 210mm; margin: 12px auto; display: flex; justify-content: space-between; align-items: center; font: 13px Arial, sans-serif; }
        .print-actions a, .print-actions button { border: 1px solid #00236f; background: #fff; color: #00236f; border-radius: 3px; padding: 8px 13px; text-decoration: none; cursor: pointer; }
        .print-actions button { background: #00236f; color: #fff; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto 12px; padding: 8.5mm 10mm; background: #fff; }
        .form { width: 100%; border: 1px solid #111; }
        .header { position: relative; min-height: 21mm; border-bottom: 1px solid #111; text-align: center; padding: 2mm 2mm 1mm; }
        .header img { position: absolute; top: 2mm; width: 17mm; height: 17mm; object-fit: contain; }
        .header .left-logo { left: 4mm; }
        .header .right-logo { right: 4mm; }
        .agency-header { font-size: 10px; line-height: 1.15; }
        .agency-header .school-name { margin-top: 0.6mm; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .header .title { font: bold 22px Arial, sans-serif; line-height: 1.18; }
        .header .subtitle { font: bold 19px Arial, sans-serif; line-height: 1.18; }
        .metadata { min-height: 35mm; border-bottom: 1px solid #111; display: grid; grid-template-columns: 20mm 58mm 15mm 30mm 20mm minmax(0, 1fr); grid-template-rows: minmax(11mm, auto) repeat(3, minmax(8mm, auto)); align-items: center; padding: 0 1mm; font-size: 13px; }
        .meta-label, .meta-value { min-width: 0; }
        .meta-label { white-space: nowrap; }
        .meta-value { min-height: 6.5mm; border-bottom: 1px solid #111; padding: 0.8mm 1mm; line-height: 1.2; overflow-wrap: anywhere; }
        .meta-label-wide { white-space: nowrap; }
        .meta-empty { height: 100%; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border-right: 1px solid #111; border-bottom: 1px solid #111; padding: 0 1mm; vertical-align: middle; }
        th:last-child, td:last-child { border-right: 0; }
        th { height: 7mm; font-size: 11px; font-weight: bold; text-align: center; }
        td { min-height: 10mm; height: 10mm; font-size: 12px; }
        .stock { width: 11%; text-align: center; }
        .unit { width: 8%; }
        .description { width: 39%; }
        .quantity { width: 11%; text-align: center; }
        .unit-cost { width: 13%; text-align: right; }
        .total-cost { width: 18%; text-align: right; }
        .nothing { height: 6.5mm; text-align: center; font-weight: bold; font-style: italic; }
        .blank td { height: 6.7mm; }
        .total-row td { height: 8mm; }
        .total-label { font-size: 12px; text-align: left; font-weight: bold; }
        .total-row .total-cost { font-weight: bold; }
        .purpose { height: 14mm; border-bottom: 1px solid #111; display: flex; align-items: flex-start; padding: 2mm 1mm; font-size: 12px; }
        .purpose-label { min-width: 22mm; }
        .signature { height: 23mm; display: grid; grid-template-columns: 20% 40% 40%; font-size: 12px; }
        .signature-cell { border-right: 1px solid #111; padding: 1mm 1mm 0; position: relative; }
        .signature-cell:last-child { border-right: 0; }
        .signature-title { text-align: left; }
        .signature-cell:first-child { text-align: center; }
        .signature-cell:first-child .signature-title { position: absolute; top: 2mm; left: 1mm; right: 1mm; text-align: center !important; }
        .signature-printed-label, .signature-designation-label { position: absolute; left: 2mm; right: 2mm; text-align: center; }
        .signature-printed-label { bottom: 6.2mm; }
        .signature-designation-label { bottom: 1.2mm; }
        .signature-title.center { text-align: center; }
        .signature-name { position: absolute; left: 2mm; right: 2mm; bottom: 6.2mm; text-align: center; font-weight: bold; text-decoration: underline; }
        .signature-role { position: absolute; left: 2mm; right: 2mm; bottom: 1.2mm; text-align: center; }
        @media print { html, body { background: #fff; } .print-actions { display: none; } .page { margin: 0; } }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
    @include('partials.official-toolbar', ['closeUrl' => route('procurement')])
    @php
        [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($procurementRequest->school, $agency);
    @endphp
    <div class="page" data-official-page data-doc="pr" data-paper="a4">
        <div class="form">
            <div class="header">
                @if($leftLogo)<img class="left-logo official-logo" src="{{ $leftLogo }}" alt="Department logo" onerror="this.remove()">@endif
                @if($rightLogo)<img class="right-logo official-logo" src="{{ $rightLogo }}" alt="School logo" onerror="this.remove()">@endif
                <div class="title">PURCHASE REQUEST</div><div class="subtitle">{{ $agency->department_name ?: 'Department of Education' }}</div>
            </div>
            <div class="metadata">
                <span class="meta-label">Entity Name:</span><span class="meta-value">{{ $procurementRequest->entity_name ?: ($procurementRequest->school?->division ?: ($agency->department_name ?: 'Department of Education')) }}</span><span class="meta-empty"></span><span class="meta-empty"></span><span class="meta-label">Fund Cluster:</span><span class="meta-value">{{ $procurementRequest->source_of_fund }}</span>
                <span class="meta-label">Department :</span><span class="meta-value">{{ $procurementRequest->department_name ?: $school_name }}</span><span class="meta-label">PR No.:</span><span class="meta-value">{{ $procurementRequest->request_number }}</span><span class="meta-label">Date:</span><span class="meta-value">{{ $date }}</span>
                <span class="meta-label">Section:</span><span class="meta-value">{{ $procurementRequest->section }}</span><span class="meta-label">SAI No. :</span><span class="meta-value">{{ $procurementRequest->sai_number }}</span><span class="meta-label">Date:</span><span class="meta-value">{{ $procurementRequest->sai_date?->format('F d, Y') }}</span>
                <span class="meta-label meta-label-wide">Responsibility Center Code:</span><span class="meta-value">{{ $procurementRequest->responsibility_center_code }}</span><span class="meta-empty"></span><span class="meta-empty"></span><span class="meta-empty"></span><span class="meta-empty"></span>
            </div>
            <table>
                <thead><tr><th class="stock">Stock No.</th><th class="unit">Unit</th><th class="description">Item Description</th><th class="quantity">Quantity</th><th class="unit-cost">Unit Cost</th><th class="total-cost">Total Cost</th></tr></thead>
                <tbody>
                    @foreach ($procurementRequest->items as $item)
                        <tr><td class="stock">{{ $loop->iteration }}</td><td class="unit">{{ strtoupper($item->unit) }}</td><td class="description">{{ $item->name }}</td><td class="quantity">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td><td class="unit-cost">{{ number_format((float) $item->unit_price, 2) }}</td><td class="total-cost">{{ number_format((float) $item->total, 2) }}</td></tr>
                    @endforeach
                    <tr><td colspan="6" class="nothing">**** NOTHING FOLLOWS ****</td></tr>
                    @for ($row = 0; $row < (int) $procurementRequest->extra_blank_rows; $row++)<tr class="blank"><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
                    <tr class="total-row"><td colspan="5" class="total-label">Total</td><td class="total-cost">{{ $amount }}</td></tr>
                </tbody>
            </table>
            <div class="purpose"><span class="purpose-label">Purpose:</span><span>{{ $purpose }}</span></div>
            <div class="signature">
                <div class="signature-cell"><div class="signature-title">Signature:</div><div class="signature-printed-label">Printed Name:</div><div class="signature-designation-label">Designation:</div></div>
                <div class="signature-cell"><div class="signature-title center">Requested by:</div><div class="signature-name">{{ $prepared_by }}</div><div class="signature-role">{{ $prepared_by_role }}</div></div>
                <div class="signature-cell"><div class="signature-title center">Approved by:</div><div class="signature-name">{{ $approved_by }}</div><div class="signature-role">{{ $approved_by_role }}</div></div>
            </div>
        </div>
    </div>
</body>
</html>
