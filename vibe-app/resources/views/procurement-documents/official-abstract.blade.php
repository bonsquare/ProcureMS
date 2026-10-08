@php
    $meta = $document->metadata ?? [];
    $legacyBidders = [
        ['name' => $meta['bidder_1_name'] ?? ($document->supplier_or_recipient ?: 'SUPPLIER 1'), 'prices' => $meta['bidder_1_prices'] ?? []],
        ['name' => $meta['bidder_2_name'] ?? ($meta['second_bidder'] ?? 'SUPPLIER 2'), 'prices' => $meta['bidder_2_prices'] ?? []],
        ['name' => $meta['bidder_3_name'] ?? ($meta['third_bidder'] ?? 'SUPPLIER 3'), 'prices' => $meta['bidder_3_prices'] ?? []],
    ];
    $bidders = collect($meta['bidders'] ?? $legacyBidders)->filter(fn ($bidder) => !empty($bidder['name']) || !empty($bidder['prices']))->values();
    if ($bidders->isEmpty()) $bidders = collect($legacyBidders);
    $quoteFor = function (array $bidder, $item): ?array {
        $value = data_get($bidder, 'prices.'.$item->id);
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        return [(float) $value, (float) $value * (float) $item->quantity];
    };
    $bidderTotals = array_fill(0, $bidders->count(), 0);
    $bidderQuoteCounts = array_fill(0, $bidders->count(), 0);
    foreach ($procurementRequest->items as $item) {
        foreach ($bidders as $index => $bidder) {
            if ($quote = $quoteFor($bidder, $item)) {
                $bidderTotals[$index] += $quote[1];
                $bidderQuoteCounts[$index]++;
            }
        }
    }
    $responsiveBids = array_filter($bidderTotals, fn ($total, $index) => $bidderQuoteCounts[$index] > 0, ARRAY_FILTER_USE_BOTH);
    asort($responsiveBids);
    $winnerBidder = array_key_first($responsiveBids);
    $winnerName = $winnerBidder !== null ? ($bidders->get($winnerBidder)['name'] ?: 'Unnamed bidder') : 'No responsive quotation recorded';
    $winnerTotal = $winnerBidder !== null ? $bidderTotals[$winnerBidder] : null;
    $bidderWidth = 62 / max($bidders->count(), 1);
@endphp
<style>
    .abstract-page{width:355.6mm;min-height:215.9mm;padding:8mm 9mm;font-size:7.7pt;line-height:1.15}.abstract-form-number{position:absolute;top:3mm;left:9mm;font-size:6.5pt;white-space:nowrap}.abstract-page .official-header{font-size:8pt;max-width:82mm;margin:0 auto}.abstract-page .official-header img{position:absolute;top:0;width:17mm;height:17mm}.abstract-page .official-header .left-logo{left:-120mm}.abstract-page .official-header .right-logo{right:-105mm}.abstract-page .official-header .school{font-size:10pt}.abstract-page .official-header .rule{position:absolute;left:-126mm;right:-126mm;margin-top:8px}.abstract-meta{display:flex;justify-content:space-between;margin-top:14px;font-weight:700}.abstract-title{text-align:center;margin:9px 0 10px;font-size:13pt}.abstract-table th,.abstract-table td{padding:4px 5px;height:22px;vertical-align:middle}.abstract-table th{font-size:7.5pt;line-height:1.1}.abstract-purpose td{height:22px}.abstract-cert{margin-top:16px;font-size:8.5pt}.abstract-signatures{display:grid;grid-template-columns:repeat(3,1fr);gap:16mm;margin-top:24px;text-align:center}.abstract-signatures.bottom{grid-template-columns:1fr 1fr;margin:28px 55mm 0}@media print{.abstract-page{margin:0;width:355.6mm;min-height:214.4mm;box-shadow:none}}
</style>

<main class="page abstract-page">
    <div class="abstract-form-number">Standard Form Number: SF-42</div>
    @include('procurement-documents._header')
    <div class="abstract-meta"><span>Bids and Awards Committee</span><span class="right">Invitation to Bid No.: {{ $document->document_number }}<br>Date: {{ $document->document_date->format('F d, Y') }}</span></div>
    <h1 class="abstract-title">ABSTRACT OF BIDS OR QUOTATION</h1>
    <table class="doc-table abstract-table"><thead><tr><th rowspan="3" style="width:9%">Stock/<br>Property No.</th><th rowspan="3" style="width:5%">UNIT</th><th rowspan="3" style="width:4%">QTY</th><th rowspan="3" style="width:20%">ITEM DESCRIPTION</th><th colspan="{{ $bidders->count() * 2 }}">Name of Bidders</th></tr><tr>@foreach($bidders as $index => $bidder)<th colspan="2" style="width:{{ $bidderWidth }}%;{{ $index === $winnerBidder ? 'background:#d9f1d0' : '' }}">{{ strtoupper($bidder['name'] ?: 'BIDDER '.($index + 1)) }}</th>@endforeach</tr><tr>@foreach($bidders as $bidder)<th>UNIT COST</th><th>TOTAL COST</th>@endforeach</tr></thead><tbody>@foreach($procurementRequest->items as $item)@php $quotes=$bidders->map(fn ($bidder) => $quoteFor($bidder, $item)); @endphp<tr><td class="center">{{ $loop->iteration }}</td><td class="center">{{ strtoupper($item->unit) }}</td><td class="center">{{ number_format((float)$item->quantity, 0) }}</td><td>{{ $item->name }}</td>@foreach($quotes as $quote)<td class="money">{{ $quote ? number_format($quote[0], 2) : '—' }}</td><td class="money">{{ $quote ? number_format($quote[1], 2) : '—' }}</td>@endforeach</tr>@endforeach<tr class="bold"><td colspan="4" class="center" style="color:#aaa">TOTAL BID QUOTATION</td>@foreach($bidderTotals as $total)<td></td><td class="money">{{ number_format($total, 2) }}</td>@endforeach</tr><tr class="abstract-purpose"><td colspan="2" class="bold">Purpose:</td><td colspan="{{ 2 + ($bidders->count() * 2) }}">{{ $procurementRequest->title }}</td></tr><tr class="abstract-purpose"><td colspan="2" class="bold">Approved Budget for the Contract (ABC):</td><td colspan="{{ 2 + ($bidders->count() * 2) }}" class="bold">₱{{ number_format((float) $procurementRequest->amount, 2) }}</td></tr><tr class="abstract-purpose"><td colspan="2" class="bold">Lowest Bid Winner:</td><td colspan="{{ 2 + ($bidders->count() * 2) }}" class="bold">{{ strtoupper($winnerName) }}@if($winnerTotal !== null) — ₱{{ number_format($winnerTotal, 2) }}@endif</td></tr><tr class="abstract-purpose"><td colspan="2" class="bold" style="color:#aaa">Source of Fund:</td><td colspan="{{ 2 + ($bidders->count() * 2) }}">{{ $meta['source_of_fund'] ?? 'MOOE' }}</td></tr></tbody></table>
    <div class="abstract-cert">WE HEREBY CERTIFY THAT THE ABSTRACT OF BID OR QUOTATION IS TRUE AND CORRECT:</div>
    <div class="abstract-signatures">@for($i=0;$i<3;$i++)<div><div class="signature-name">{{ strtoupper($bacMembers->get($i)?->name ?? 'BAC MEMBER') }}</div><div>BAC Member</div></div>@endfor</div>
    <div class="abstract-signatures bottom"><div><div class="signature-name">{{ strtoupper($bacViceChair?->name ?? 'BAC VICE CHAIRPERSON') }}</div><div>BAC Vice Chairperson</div></div><div><div class="signature-name">{{ strtoupper($bacChair?->name ?? 'BAC CHAIRPERSON') }}</div><div>BAC Chairperson</div></div></div>
</main>
