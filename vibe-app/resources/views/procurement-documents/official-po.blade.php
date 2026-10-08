@php
    $meta = $document->metadata ?? [];
    $supplier = $document->supplier_or_recipient ?: '____________________________';
    $awardedPrices = $meta['awarded_item_prices'] ?? [];
    $awardAmount = (float) ($meta['winning_bid_amount'] ?? $procurementRequest->amount);
    $departmentName = $agency?->department_name ?: 'Department of Education';
@endphp
<style>
    .po-page{width:215.9mm;min-height:330.2mm;padding:9.5mm 10.5mm;font-size:9pt;line-height:1.08}.po-heading{text-align:center;font-size:15pt;font-weight:700;font-style:italic}.po-subheading{text-align:center;font-size:11pt;font-weight:700;font-style:italic;margin:2px 0 9px}.po-table td,.po-table th{padding:5px 6px;line-height:1.2}.po-table tr{height:8mm}.po-table .label{font-weight:700}.po-items td,.po-items th{height:22px;padding:3px 5px}.po-items th{font-size:9pt}.po-footer{border:1px solid #111;border-top:0}.po-penalty{padding:9px 6px 10px;font-size:10pt;font-style:italic;font-weight:700;line-height:1.32}.po-signatures{display:grid;grid-template-columns:1fr 1fr;min-height:36mm;padding:6px 5px 6px}.po-sig-block{display:flex;min-height:32mm;flex-direction:column;align-items:center}.po-sig-heading{align-self:flex-start;font-size:10pt;font-style:italic;font-weight:700}.po-sig{width:100%;margin-top:13mm;text-align:center;line-height:1.12}.po-sig .line{display:inline-block;min-width:76mm;border-top:1px solid #111;padding-top:2px}.po-authorized .po-sig{margin-top:11mm}.po-authorized .signature-name{margin-bottom:1px}.po-sign-date{align-self:flex-start;margin-top:5mm;margin-left:18mm;text-align:left;font-style:italic}.po-sign-date .date-line{display:inline-block;width:35mm;border-bottom:1px solid #111;vertical-align:baseline}.po-accounting{display:grid;grid-template-columns:1fr 1fr;border-top:2px solid #111;min-height:44mm;padding:4px 5px 5px}.po-account-left,.po-account-right{position:relative}.po-account-lines{font-style:italic;line-height:1.45}.po-disbursing{text-align:center;position:absolute;left:0;right:0;bottom:0}.po-disbursing .signature-name{font-size:10.5pt}.po-account-right{padding-left:24mm}.po-account-right .amount-line{display:grid;grid-template-columns:20mm 1fr;max-width:55mm}.po-account-right .amount-value{text-align:right;font-weight:700}@media print{.po-page{margin:0;width:215.9mm;min-height:330.2mm;box-shadow:none}}
</style>
<main class="page po-page">
    <div class="po-heading">PURCHASE ORDER</div><div class="po-subheading">{{ $departmentName }}</div>
    <table class="doc-table po-table"><tr><td class="label" style="width:17%">Supplier :</td><td class="bold" style="width:31%">{{ strtoupper($supplier) }}</td><td class="label" style="width:22%">P.O. No. :</td><td>{{ $document->document_number }}</td></tr><tr><td class="label">Address :</td><td>{{ $meta['supplier_address'] ?? '' }}</td><td class="label">Date :</td><td>{{ $document->document_date->format('F d, Y') }}</td></tr><tr><td class="label">TIN :</td><td>{{ $meta['supplier_tin'] ?? $meta['tin'] ?? '' }}</td><td class="label">Mode of Procurement :</td><td class="bold">{{ $meta['mode_of_procurement'] ?? 'SVP' }}</td></tr><tr><td colspan="4">Sir/Madame:</td></tr><tr><td colspan="4" class="italic">Please furnish this Office the following articles subject to the terms and conditions contained herein:</td></tr><tr><td class="label">Place of Delivery :</td><td class="bold">{{ $meta['place_of_delivery'] ?? $procurementRequest->school?->name }}</td><td class="label">Delivery Term:</td><td class="bold">{{ $meta['delivery_term'] ?? 'Pick-Up' }}</td></tr><tr><td class="label">Date of Delivery :</td><td>{{ $meta['delivery_schedule'] ?: 'Within '.($meta['delivery_days'] ?? 30).' Calendar Days from receipt of the Notice to Proceed' }}</td><td class="label">Payment term:</td><td class="bold">{{ $meta['payment_term'] ?? '30 days' }}</td></tr></table>
    <table class="doc-table po-items" style="border-top:0"><thead><tr><th style="width:16%">Stock/<br>Property No.</th><th style="width:9%">Unit</th><th>Description</th><th style="width:12%">Quantity</th><th style="width:13%">Unit Cost</th><th style="width:13%">Amount</th></tr></thead><tbody>@foreach($procurementRequest->items as $item)@php $unitCost = is_numeric(data_get($awardedPrices, $item->id)) ? (float) data_get($awardedPrices, $item->id) : (float) $item->unit_price; $itemAmount = $unitCost * (float) $item->quantity; @endphp<tr><td class="center">{{ $loop->iteration }}</td><td>{{ strtoupper($item->unit) }}</td><td>{{ $item->name }}@if($item->description) ({{ $item->description }})@endif</td><td class="center">{{ number_format((float)$item->quantity, 0) }}</td><td class="money">{{ number_format($unitCost, 2) }}</td><td class="money">{{ number_format($itemAmount, 2) }}</td></tr>@endforeach<tr><td colspan="6" class="center italic">**** NOTHING FOLLOWS ****</td></tr>@for($blankRow = 0; $blankRow < (int) ($meta['extra_blank_rows'] ?? 0); $blankRow++)<tr aria-label="Blank purchase order row"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>@endfor</tbody></table>
    <table class="doc-table" style="border-top:0"><tr><td class="italic" style="width:18%">(Total Amount in Words)</td><td class="bold italic">{{ $awardAmountInWords }}</td><td class="money bold" style="width:14%">{{ number_format($awardAmount, 2) }}</td></tr></table>
    <div class="po-footer">
        <div class="po-penalty">In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed on the undelivered item/s.</div>
        <div class="po-signatures">
            <div class="po-sig-block po-authorized">
                <div class="po-sig-heading">Conforme:</div>
                <div class="po-sig"><div class="line">Signature over Printed Name of Supplier</div></div>
                <div class="po-sign-date">Date: <span class="date-line"></span></div>
            </div>
            <div class="po-sig-block">
                <div class="po-sig-heading">Very truly yours,</div>
                <div class="po-sig">
                    <div class="signature-name">{{ strtoupper($schoolHead?->name ?? $procurementRequest->school?->school_head ?? 'AUTHORIZED OFFICIAL') }}</div>
                    <div>Signature over Printed Name of Authorized Official</div>
                    <div>School Head</div>
                </div>
                <div class="po-sign-date">Date: <span class="date-line"></span></div>
            </div>
        </div>
        <div class="po-accounting">
            <div class="po-account-left">
                <div class="po-account-lines">
                    Fund Cluster : {{ ($meta['source_of_fund'] ?? null) ?: ($procurementRequest->source_of_fund ?: 'General Fund') }}<br>
                    Funds Available :
                </div>
                <div class="po-disbursing">
                    <div class="signature-name">{{ strtoupper($disbursingOfficer?->name ?? 'DISBURSING OFFICER') }}</div>
                    <div><strong><em>Disbursing Officer</em></strong></div>
                    <div><strong><em>Designation</em></strong></div>
                </div>
            </div>
            <div class="po-account-right">
                <div class="po-account-lines">
                    ORS/BURS No. :<br>
                    BURS :<br>
                    BUDRP/BUDRP:<br>
                    <span class="amount-line"><span>Amount :</span><span class="amount-value">{{ number_format($awardAmount, 2) }}</span></span>
                </div>
            </div>
        </div>
    </div>
</main>
