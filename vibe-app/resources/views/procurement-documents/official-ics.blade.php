@php
    $meta = $document->metadata ?? [];
    $prices = $meta['awarded_item_prices'] ?? [];
    $icsItems = collect($meta['ics_items'] ?? [])->filter(fn ($row) => !empty($row['included']) && !empty($row['custodian_id']));
    $staffById = $staff->keyBy('id');
    $itemById = $procurementRequest->items->keyBy('id');
    $groups = $icsItems->groupBy('custodian_id');
    if ($groups->isEmpty()) {
        $groups = collect(['' => collect()]);
    }
    $entityName = $procurementRequest->department_name ?: $procurementRequest->school?->name;
    $fundCluster = ($meta['source_of_fund'] ?? null) ?: ($procurementRequest->source_of_fund ?: 'General Fund');
@endphp
<style>
    .ics-page{width:216mm;min-height:330mm;padding:16mm 14mm;font-family:Arial,sans-serif;font-size:10pt}.ics-title{text-align:center;font-size:16pt;font-weight:400;margin:0 0 8mm}.ics-table{width:100%;border-collapse:collapse;table-layout:fixed}.ics-table th,.ics-table td{border:1px solid #111;padding:3px 4px;vertical-align:top}.ics-table th{text-align:center;font-weight:400;vertical-align:middle}.ics-info td{border:0;padding:1px 4px}.ics-items .description{line-height:1.35;white-space:pre-line}.ics-items .item-row td{height:30mm}.ics-center{text-align:center;vertical-align:middle!important}.ics-sign td{height:20mm}.ics-sign .name{text-align:center;font-weight:700;text-decoration:underline;padding-top:13mm}.ics-sign .caption{text-align:center;border-top:1px solid #111}.ics-sign .role{text-align:center;font-weight:700}.ics-date{height:17mm!important;vertical-align:bottom!important}.ics-break{page-break-after:always}@media print{.ics-page{margin:0;width:216mm;min-height:330mm;box-shadow:none}.ics-break{page-break-after:always}}
</style>
@foreach($groups as $custodianId => $rows)
    @php
        $custodian = $staffById->get($custodianId);
        $sheetNumber = $groups->count() > 1 ? $document->document_number.'-'.str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT) : $document->document_number;
    @endphp
    <main class="page ics-page {{ !$loop->last ? 'ics-break' : '' }}">
        <table class="ics-table" style="border:1px solid #111"><tr><td>
            <h1 class="ics-title">INVENTORY CUSTODIAN SLIP</h1>
            <table class="ics-table ics-info">
                <tr><td style="width:15%">Entity Name:</td><td style="width:40%;border-bottom:1px solid #111">{{ strtoupper($entityName) }}</td><td style="width:18%">ICS No.:</td><td style="border-bottom:1px solid #111">{{ $sheetNumber }}</td></tr>
                <tr><td>Fund Cluster:</td><td style="border-bottom:1px solid #111">{{ $fundCluster }}</td><td>DIV ICS NO.:</td><td style="border-bottom:1px solid #111">{{ $meta['division_ics_number'] ?? '' }}</td></tr>
            </table>
            <table class="ics-table ics-items">
                <thead><tr><th style="width:8%">Quantity</th><th style="width:7%">Unit</th><th colspan="2" style="width:23%">Amount</th><th rowspan="2" style="width:34%">Description</th><th rowspan="2" style="width:16%">Inventory<br>Item No.</th><th rowspan="2" style="width:12%">Estimated<br>Useful Life</th></tr><tr><th></th><th></th><th>Unit<br>Cost</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($rows as $itemId => $icsRow)
                        @php
                            $item = $itemById->get($itemId);
                            $quantity = (float) ($icsRow['quantity'] ?? 0);
                            $cost = $item ? (float) ($prices[$item->id] ?? $item->unit_price) : 0;
                        @endphp
                        @if($item && $quantity > 0)
                            <tr class="item-row">
                                <td class="ics-center">{{ rtrim(rtrim(number_format($quantity, 2), '0'), '.') }}</td>
                                <td class="ics-center">{{ ucfirst(strtolower($item->unit)) }}</td>
                                <td class="ics-center">{{ number_format($cost, 2) }}</td>
                                <td class="ics-center">{{ number_format($cost * $quantity, 2) }}</td>
                                <td class="description"><strong>{{ $item->name }}</strong>@if($item->description)<br>{{ $item->description }}@endif</td>
                                <td class="ics-center">{{ $icsRow['inventory_item_number'] ?? '' }}</td>
                                <td class="ics-center">{{ $icsRow['useful_life'] ?? '' }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr class="item-row"><td colspan="7" class="ics-center">No accountable item selected for ICS.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <table class="ics-table ics-sign">
                <tr><td style="width:50%">Received from:<div class="name">{{ strtoupper($propertyOfficer?->name ?? $procurementOfficer?->name ?? $requestingOfficer?->name ?? '') }}</div></td><td>Received by:<div class="name">{{ strtoupper($custodian?->name ?? '') }}</div></td></tr>
                <tr><td class="caption">Signature Over Printed Name</td><td class="caption">Signature Over Printed Name</td></tr>
                <tr><td class="role">{{ $propertyOfficer?->position ?? $procurementOfficer?->position ?? '' }}</td><td class="role">{{ $custodian?->position ?? '' }}</td></tr>
                <tr><td class="caption">Position / Office</td><td class="caption">Position / Office</td></tr>
                <tr><td class="ics-date">Date</td><td class="ics-date">Date</td></tr>
            </table>
        </td></tr></table>
    </main>
@endforeach
