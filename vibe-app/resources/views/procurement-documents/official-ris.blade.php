@php
    $blankRows = (int) (($document->metadata ?? [])['extra_blank_rows'] ?? 0);
    $requester = $requestingOfficer ?? $procurementRequest->requester;
    $head = $schoolHead;
    $property = $propertyOfficer;
    $staffById = $iarsStaff->keyBy('id');
    $iarsAssignments = collect(($inventoryAcknowledgementReceipt?->metadata ?? [])['iars_assignments'] ?? [])
        ->filter(fn ($row) => !empty($row['staff_id']))
        ->values();

    if ($iarsAssignments->isEmpty()) {
        $iarsAssignments = collect([[
            'staff_id' => $requester?->id,
            'items' => $procurementRequest->items->mapWithKeys(fn ($item) => [$item->id => $item->quantity])->all(),
        ]]);
    }

    $risBaseNumber = $document->document_number;
    if (preg_match('/^RIS-(\d{4})-(\d+)(?:-\d+)?$/', $risBaseNumber, $matches)) {
        $risBaseNumber = 'RIS-'.$matches[1].'-'.str_pad((string) ((int) $matches[2]), 4, '0', STR_PAD_LEFT);
    }
@endphp
<style>
.ris-page{width:215.9mm;min-height:330.2mm;padding:8mm 10mm;font-family:Arial,sans-serif;font-size:10pt;color:#111}.ris-wrap{border:1px solid #111}.ris-title{height:21mm;text-align:center;padding-top:8mm;font-size:14pt;font-weight:700}.ris-appendix{text-align:right;padding:3mm 2mm 0;font-size:9pt}.ris-table{width:100%;border-collapse:collapse;table-layout:fixed}.ris-table td,.ris-table th{border:1px solid #111;padding:3px 4px;vertical-align:middle}.ris-table .center{text-align:center}.ris-lines{height:18mm;padding:2mm}.ris-item td{height:9mm}.ris-empty td{height:9mm}.ris-purpose td{height:12mm;vertical-align:top;padding-top:4px;line-height:1.3}.ris-sign td{height:7mm}.ris-signature td{height:22mm;vertical-align:bottom}.ris-name{font-weight:700;text-align:center}.ris-role{text-align:center}.ris-break{page-break-after:always}@media print{@page{size:8.5in 13in;margin:0}.ris-page{margin:0;width:215.9mm;min-height:330.2mm;box-shadow:none}.ris-break{page-break-after:always}}
</style>
@foreach($iarsAssignments as $assignmentIndex => $assignment)
    @php
        $recipient = $staffById->get($assignment['staff_id']) ?? $requester;
        $assignedItems = collect($assignment['items'] ?? []);
        $issuedItems = $procurementRequest->items
            ->map(function ($item) use ($assignedItems) {
                $quantity = (float) $assignedItems->get($item->id, 0);
                return ['item' => $item, 'quantity' => $quantity];
            })
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->values();
        $sheetRisNumber = $risBaseNumber.'-'.str_pad((string) ($assignmentIndex + 1), 3, '0', STR_PAD_LEFT);
    @endphp
    <main class="page ris-page {{ !$loop->last ? 'ris-break' : '' }}">
        <div class="ris-wrap">
            <div class="ris-appendix">Appendix 63</div>
            <div class="ris-title">REQUISITION AND ISSUANCE SLIP</div>
            <table class="ris-table">
                <tr>
                    <td style="width:15%">Entity Name</td>
                    <td style="width:51%;text-decoration:underline">{{ strtoupper($procurementRequest->school?->name) }}</td>
                    <td style="width:12%">Fund<br>Cluster:</td>
                    <td style="width:22%;text-decoration:underline">General Fund</td>
                </tr>
                <tr>
                    <td colspan="2" class="ris-lines">
                        Division: <span style="text-decoration:underline">{{ strtoupper($agency?->division_office ?? '') }}</span><br>
                        Office: <span style="text-decoration:underline">{{ strtoupper($procurementRequest->school?->name) }}</span>
                    </td>
                    <td colspan="2" class="ris-lines">
                        Responsibility Center Code: __________________________<br>
                        RIS No.: <span style="text-decoration:underline">{{ $sheetRisNumber }}</span>
                    </td>
                </tr>
            </table>
            <table class="ris-table" style="border-top:0">
                <colgroup><col style="width:11%"><col style="width:7%"><col style="width:35%"><col style="width:11%"><col style="width:9%"><col style="width:7%"><col style="width:10%"><col style="width:10%"></colgroup>
                <thead>
                    <tr><th colspan="4">Requisition</th><th colspan="2">Stock Available?</th><th colspan="2">Issue</th></tr>
                    <tr><th>Stock No.</th><th>Unit</th><th style="text-align:left">Description</th><th>Quantity</th><th>Yes</th><th>No</th><th>Quantity</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    @foreach($issuedItems as $row)
                        @php($item = $row['item'])
                        <tr class="ris-item">
                            <td class="center">{{ $loop->iteration }}</td>
                            <td class="center">{{ strtoupper($item->unit) }}</td>
                            <td>{{ $item->name }}</td>
                            <td class="center">{{ number_format($row['quantity'], 0) }}</td>
                            <td class="center">✓</td>
                            <td></td>
                            <td class="center">{{ number_format($row['quantity'], 0) }}</td>
                            <td>Issued</td>
                        </tr>
                    @endforeach
                    @for($i = 0; $i < $blankRows; $i++)
                        <tr class="ris-empty"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                    @endfor
                </tbody>
            </table>
            <table class="ris-table" style="border-top:0"><tr class="ris-purpose"><td style="width:9%">Purpose:</td><td>{{ $procurementRequest->title }}</td></tr></table>
            <table class="ris-table" style="border-top:0">
                <colgroup><col style="width:12%"><col style="width:22%"><col style="width:22%"><col style="width:22%"><col style="width:22%"></colgroup>
                <tr><td></td><td>Requested by:</td><td>Approved by:</td><td>Issued by:</td><td>Received by:</td></tr>
                <tr class="ris-signature"><td>Signature</td><td></td><td></td><td></td><td></td></tr>
                <tr class="ris-sign">
                    <td>Printed Name:</td>
                    <td class="ris-name">{{ strtoupper($requester?->name ?? '') }}</td>
                    <td class="ris-name">{{ strtoupper($head?->name ?? '') }}</td>
                    <td class="ris-name">{{ strtoupper($property?->name ?? '') }}</td>
                    <td class="ris-name">{{ strtoupper($recipient?->name ?? '') }}</td>
                </tr>
                <tr class="ris-sign">
                    <td>Designation</td>
                    <td class="ris-role">{{ $requester?->position }}</td>
                    <td class="ris-role">{{ $head?->position ?? 'School Head' }}</td>
                    <td class="ris-role">{{ $property?->position ?? 'Property Officer' }}</td>
                    <td class="ris-role">{{ $recipient?->position }}</td>
                </tr>
                <tr class="ris-sign"><td>Date:</td><td></td><td></td><td></td><td></td></tr>
            </table>
        </div>
    </main>
@endforeach
