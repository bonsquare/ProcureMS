@php
    $meta = $document->metadata ?? [];
    $poMetadata = $purchaseOrder?->metadata ?? [];
    $blankRows = min(6, (int) ($meta['extra_blank_rows'] ?? 0));
    $receivedItems = $meta['received_items'] ?? [];
    $entityName = $procurementRequest->entity_name
        ?: ($procurementRequest->school?->division ?: ($agency?->department_name ?: null))
        ?: ($procurementRequest->school?->name ?? '');
    $fundCluster = ($meta['source_of_fund'] ?? null) ?: ($procurementRequest->source_of_fund ?: 'General Fund');
    $inspectionName = $meta['inspection_officer_name'] ?? $inspectionOfficer?->name ?? '';
    $propertyName = $propertyOfficer?->name ?? '';
    $isPartialDelivery = false;
    foreach ($procurementRequest->items as $item) {
        $poQuantity = (float) $item->quantity;
        $receivedQuantity = $receivedItems[$item->id] ?? $poQuantity;
        $receivedQuantity = $receivedQuantity === '' || $receivedQuantity === null ? $poQuantity : (float) $receivedQuantity;
        if ($receivedQuantity < $poQuantity) {
            $isPartialDelivery = true;
            break;
        }
    }
@endphp

<style>
    .iar-page{width:210mm;min-height:297mm;padding:10mm 12mm;font-family:"Times New Roman",serif;color:#111;font-size:9pt;line-height:1.04}
    .iar-appendix{text-align:right;font-size:8.5pt;font-style:italic;margin-bottom:2mm}
    .iar-title{text-align:center;font-size:12pt;font-weight:700;margin:0 0 4mm}
    .iar-topline{display:grid;grid-template-columns:36mm 1fr 23mm 31mm;align-items:end;margin:0 1mm 1mm;font-weight:700;font-size:8.5pt}
    .iar-topline .fund-label{grid-column:3;white-space:nowrap}.iar-topline .fund-value{font-weight:400;text-decoration:underline;white-space:nowrap}
    .iar-table{width:100%;border-collapse:collapse;table-layout:fixed}.iar-table td,.iar-table th{border:1px solid #111;padding:2px 3px;vertical-align:middle}.iar-table .label{font-size:9pt}.iar-table .data{font-weight:700}.iar-table .details-row td{height:7mm}.iar-table .header-row th{height:9mm;text-align:center;font-size:9pt;font-style:italic}.iar-table .item-row td{height:7mm;font-size:9pt}.iar-table .empty-row td{height:7mm}.iar-table .nothing-follows td{height:5mm;text-align:center;font-size:8.5pt;font-style:italic;font-weight:700}.iar-table .center{text-align:center}.iar-table .item-description{padding-left:3px}.iar-section-title{height:7mm;padding-top:2px!important;padding-bottom:2px!important;font-weight:700;font-size:9.5pt;font-style:italic;text-align:center}.iar-footer td{border:1px solid #111}.iar-footer .date-row td{height:8mm;padding-top:2px;padding-bottom:2px;font-size:9pt;font-weight:700}.iar-footer .verification{text-align:center;font-size:8.5pt;line-height:1.2}.iar-footer .acceptance-check{width:35%;text-align:center}.iar-footer .acceptance-row{height:12mm;text-align:center}.iar-footer .signature-area{height:18mm;text-align:center;vertical-align:bottom;padding-bottom:2px}.iar-footer .signature-name{font-weight:700;font-size:9pt}.iar-footer .signature-role{border-top:1px solid #111;text-align:center;font-size:8.5pt;padding-top:2px}.iar-check{font-family:Arial,sans-serif;font-size:16pt;font-weight:700;line-height:1}@media print{.iar-page{margin:0;box-shadow:none}}
</style>

<main class="page iar-page">
    <div class="iar-appendix">Appendix 62</div>
    <h1 class="iar-title">INSPECTION AND ACCEPTANCE REPORT</h1>
    <div class="iar-topline">
        <div>Entity Name :</div>
        <div>{{ $entityName }}</div>
        <div class="fund-label">Fund Cluster :</div>
        <div class="fund-value">{{ $fundCluster }}</div>
    </div>

    <table class="iar-table">
        <colgroup><col style="width:36mm"><col style="width:85mm"><col style="width:20mm"><col style="width:29mm"></colgroup>
        <tbody>
            <tr class="details-row"><td class="label">Supplier :</td><td class="data">{{ strtoupper($document->supplier_or_recipient ?: $purchaseOrder?->supplier_or_recipient ?: '') }}</td><td class="label">IAR No. :</td><td>{{ $document->document_number }}</td></tr>
            <tr class="details-row"><td class="label">PO No./Date :</td><td class="data">{{ ($meta['purchase_order_number'] ?? $purchaseOrder?->document_number ?? '') }}@php($poDate = $meta['purchase_order_date'] ?? optional($purchaseOrder?->document_date)->format('F d, Y'))@if($poDate) / {{ $poDate }}@endif</td><td class="label">Date :</td><td>{{ $document->document_date->format('m/d/Y') }}</td></tr>
            <tr class="details-row"><td class="label">Requisitioning<br>Office/Dept.:</td><td>{{ $procurementRequest->department_name ?: $procurementRequest->school?->name }}</td><td class="label">Inv. No.</td><td></td></tr>
            <tr class="details-row"><td class="label center">Responsibility Center</td><td>{{ $procurementRequest->responsibility_center_code }}</td><td class="label">Date :</td><td></td></tr>
            <tr class="header-row"><th>Stock/<br>Property No.</th><th>Description</th><th>Unit</th><th>Quantity</th></tr>
            @foreach($procurementRequest->items as $item)
                <tr class="item-row"><td class="center">{{ $loop->iteration }}</td><td class="item-description">{{ $item->name }}@if($item->description) ({{ $item->description }})@endif</td><td class="center">{{ strtoupper($item->unit) }}</td><td class="center">{{ number_format((float) ($receivedItems[$item->id] ?? $item->quantity), 0) }}</td></tr>
            @endforeach
            <tr class="nothing-follows"><td colspan="4">**** NOTHING FOLLOWS ****</td></tr>
            @for($blank = 0; $blank < $blankRows; $blank++)
                <tr class="empty-row"><td>&nbsp;</td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table class="iar-table iar-footer" style="border-top:0">
        <colgroup><col style="width:36mm"><col style="width:49mm"><col style="width:27mm"><col style="width:58mm"></colgroup>
        <tbody>
            <tr><td colspan="2" class="iar-section-title">INSPECTION</td><td colspan="2" class="iar-section-title">ACCEPTANCE</td></tr>
            <tr class="date-row"><td class="center">Date Inspected :</td><td>{{ $document->document_date->format('m/d/Y') }}</td><td class="center">Date Received :</td><td>{{ $document->document_date->format('m/d/Y') }}</td></tr>
            <tr><td rowspan="2" class="verification center"><span class="iar-check">✓</span></td><td rowspan="2" class="verification">Inspected, verified and found in order as to quantity and specifications.</td><td class="acceptance-check">@unless($isPartialDelivery)<span class="iar-check">✓</span>@endunless</td><td class="acceptance-row">Complete</td></tr>
            <tr><td class="acceptance-check">@if($isPartialDelivery)<span class="iar-check">✓</span>@endif</td><td class="acceptance-row">Partial<br>(pls. specify quantity)</td></tr>
            <tr><td colspan="2" class="signature-area"><div class="signature-name">{{ strtoupper($inspectionName) }}</div></td><td colspan="2" class="signature-area"><div class="signature-name">{{ strtoupper($propertyName) }}</div></td></tr>
            <tr><td colspan="2" class="signature-role">Inspection Officer/Inspection Committee</td><td colspan="2" class="signature-role">Property Officer</td></tr>
        </tbody>
    </table>
</main>
