<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentDefinition['short'] }} · {{ $document->document_number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/procurems-favicon.svg') }}">
    <style>
        *{box-sizing:border-box}html,body{margin:0;color:#111;font-family:Arial,sans-serif}body{background:#e9e9e9}.toolbar{position:sticky;top:0;z-index:100;display:flex;align-items:center;gap:8px;padding:12px 20px;background:#fff;border-bottom:1px solid #bbb}.toolbar a,.toolbar button{border:1px solid #00236f;border-radius:4px;padding:9px 14px;background:#fff;color:#00236f;font-weight:700;cursor:pointer;text-decoration:none}.toolbar .primary{background:#00236f;color:#fff}.toolbar span{margin-left:auto;font-size:12px;color:#555}.page{position:relative;margin:16px auto;background:#fff;box-shadow:0 2px 12px #999;overflow:hidden}.official-header{position:relative;text-align:center;line-height:1.25}.official-header img{position:absolute;top:4px;width:74px;height:74px;object-fit:contain}.official-header .left-logo{left:0}.official-header .right-logo{right:0}.official-header .republic{font-size:11px}.official-header .department{font-size:12px;font-weight:700}.official-header .school{margin-top:3px;font-size:14px;font-weight:700}.official-header .rule{margin-top:9px;border-top:1px solid #111}.bac{margin-top:11px;font-size:12px}.u{text-decoration:underline}.center{text-align:center}.right{text-align:right}.bold{font-weight:700}.italic{font-style:italic}.upper{text-transform:uppercase}.nowrap{white-space:nowrap}.money{text-align:right}.signature-name{font-weight:700;text-decoration:underline}.signature-role{font-size:11px}.doc-table{width:100%;border-collapse:collapse;table-layout:fixed}.doc-table th,.doc-table td{border:1px solid #111;padding:4px;vertical-align:middle}.doc-table th{text-align:center;font-weight:700}.page-break{page-break-after:always}@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;box-shadow:none}.page-break{page-break-after:always}}
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@include('partials.official-toolbar', ['closeUrl' => route('procurement.documents', $procurementRequest)])
@php
    // Each official form keeps its normal paper; the toolbar lets the user choose another.
    $paperByDocument = [
        'request_for_quotation' => ['rfq', 'legal', 'portrait'], 'purchase_order' => ['po', 'longbond', 'portrait'],
        'notice_to_proceed' => ['ntp', 'a4', 'portrait'], 'notice_to_award' => ['noa', 'a4', 'portrait'],
        'abstract_of_bids_quotation' => ['abstract', 'legal', 'landscape'], 'inspection_acceptance_report' => ['iar', 'a4', 'portrait'],
        'requisition_issuance_slip' => ['ris', 'longbond', 'portrait'], 'inventory_acknowledgement_receipt_supplies' => ['iars', 'a4', 'landscape'],
        'inventory_custodian_slip' => ['ics', 'longbond', 'portrait'],
    ];
    [$docKey, $docPaper, $docOrientation] = $paperByDocument[$document->document_type] ?? ['generic', 'a4', 'portrait'];
@endphp
<div data-official-page data-doc="{{ $docKey }}" data-paper="{{ $docPaper }}" data-orientation="{{ $docOrientation }}">
@switch($document->document_type)
    @case('request_for_quotation') @include('procurement-documents.official-rfq') @break
    @case('purchase_order') @include('procurement-documents.official-po') @break
    @case('notice_to_proceed') @include('procurement-documents.official-ntp') @break
    @case('abstract_of_bids_quotation') @include('procurement-documents.official-abstract') @break
    @case('notice_to_award') @include('procurement-documents.official-noa') @break
    @case('inspection_acceptance_report') @include('procurement-documents.official-iar') @break
    @case('requisition_issuance_slip') @include('procurement-documents.official-ris') @break
    @case('inventory_acknowledgement_receipt_supplies') @include('procurement-documents.official-iars') @break
    @case('inventory_custodian_slip') @include('procurement-documents.official-ics') @break
    @default @include('procurement-documents.official-generic')
@endswitch
</div>
</body>
</html>
