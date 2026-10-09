@props(['document', 'definition'])
@php
    $request = $document->procurementRequest;
    $stage = match ($document->document_type) {
        'request_for_quotation' => 'Canvass',
        'abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed' => 'Award',
        'purchase_order' => 'Purchase Order',
        'inspection_acceptance_report', 'inventory_acknowledgement_receipt_supplies', 'requisition_issuance_slip', 'inventory_custodian_slip' => 'Receiving',
        default => 'Request',
    };
@endphp
<tr class="border-b border-outline-variant/50 align-top last:border-0">
    <td class="p-4"><a class="font-bold text-primary hover:underline" href="{{ route('procurement.show', $request) }}">{{ $request->request_number }}</a><p class="mt-1 text-on-surface-variant">{{ $request->title }}</p></td>
    <td class="p-4"><strong>{{ $document->document_number }}</strong><p class="mt-1 text-on-surface-variant">{{ $definition['label'] ?? str($document->document_type)->replace('_', ' ')->title() }}</p></td>
    <td class="p-4"><x-procurement.status-badge :label="$stage" tone="action" /></td>
    <td class="p-4"><x-procurement.status-badge :label="str($document->status)->replace('_', ' ')->title()" :tone="$document->status === 'prepared' ? 'verified' : 'neutral'" /></td>
    <td class="p-4"><span class="font-semibold">{{ $document->creator?->name ?? 'System' }}</span><p class="mt-1 text-on-surface-variant">Updated {{ $document->updated_at->format('M d, Y') }}</p></td>
    <td class="p-4 text-right"><div class="flex justify-end gap-2"><a href="{{ route('procurement.documents.print', [$request, $document]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-3 font-bold text-white">View &amp; Print</a><a href="{{ route('procurement.documents', $request) }}" class="inline-flex min-h-11 items-center rounded-lg border border-outline-variant px-3 font-bold text-primary">Edit</a></div></td>
</tr>
