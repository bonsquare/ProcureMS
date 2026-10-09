@props(['document', 'definition'])
@php
    $request = $document->procurementRequest;
    $workflow = $request->workflow;
    $delivered = $workflow['delivered'];
@endphp
<tr class="border-b border-outline-variant/50 align-top last:border-0">
    <td class="p-4"><a class="font-bold text-primary hover:underline" href="{{ route('procurement.show', $request) }}">{{ $request->request_number }}</a><p class="mt-1 text-on-surface-variant">{{ $request->title }}</p></td>
    <td class="p-4"><strong>{{ $document->document_number }}</strong><p class="mt-1 text-on-surface-variant">{{ $definition['label'] ?? str($document->document_type)->replace('_', ' ')->title() }}</p></td>
    <td class="p-4"><x-procurement.status-badge :label="$workflow['stage_label']" :tone="$delivered ? 'verified' : 'action'" /></td>
    <td class="p-4"><div class="flex items-center gap-2"><x-procurement.status-badge :label="$delivered ? 'Delivered' : 'On processing'" :tone="$delivered ? 'verified' : 'attention'" /><a href="{{ route('procurement.documents.print', [$request, $document]) }}" target="_blank" rel="noopener" class="inline-grid h-8 w-8 place-items-center rounded-lg border border-outline-variant text-primary transition hover:bg-primary hover:text-white" aria-label="View {{ $document->document_number }}" title="View {{ $definition['label'] ?? 'document' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">visibility</span></a></div><p class="mt-1 text-on-surface-variant">{{ $workflow['steps_done'] }} of {{ $workflow['steps_total'] }} steps</p></td>
    <td class="p-4"><span class="font-semibold">{{ $document->creator?->name ?? 'System' }}</span><p class="mt-1 text-on-surface-variant">Updated {{ $document->updated_at->format('M d, Y') }}</p></td>
    <td class="p-4 text-right"><div class="flex justify-end"><a href="{{ route('procurement.documents', array_filter(['procurementRequest' => $request, 'stage' => in_array($document->document_type, \App\Services\ProcurementWorkspaceService::RECEIVING_DOCUMENT_TYPES, true) ? 'receiving' : null])) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-bold text-white">Process</a></div></td>
</tr>
