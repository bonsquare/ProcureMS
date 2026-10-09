@extends('layouts.procurement')
@section('title', 'Documents · '.$procurementRequest->request_number)
@section('page-title', $procurementRequest->request_number.' Documents')
@section('header-actions')<a href="{{ route('procurement.show', ['procurementRequest' => $procurementRequest, 'section' => 'documents']) }}" class="inline-flex min-h-11 items-center rounded-lg border border-outline-variant px-3 text-xs font-bold text-primary">Request workspace</a>@endsection
@section('flash-handled', '1')
@section('content')
    <section class="mb-6 rounded border border-outline-variant/30 bg-white p-5"><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div><p class="text-xs font-semibold uppercase text-on-surface-variant">School</p><p class="mt-1 text-sm font-semibold">{{ $procurementRequest->school?->name }}</p></div><div><p class="text-xs font-semibold uppercase text-on-surface-variant">Purpose</p><p class="mt-1 text-sm font-semibold">{{ $procurementRequest->title }}</p></div><div><p class="text-xs font-semibold uppercase text-on-surface-variant">Amount</p><p class="mt-1 text-sm font-semibold">₱{{ number_format((float) $procurementRequest->amount, 2) }}</p></div><div><p class="text-xs font-semibold uppercase text-on-surface-variant">Status</p><p class="mt-1 text-sm font-semibold capitalize">{{ str($procurementRequest->status)->replace('_', ' ') }}</p></div></div></section>
    @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-semibold text-secondary">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
    <div class="mb-5"><h1 class="text-2xl font-semibold">Procurement Documents</h1><p class="mt-1 text-sm text-on-surface-variant">Prepare and print each document as the procurement progresses. All records remain linked to this purchase request.</p></div>
    <section class="mb-6 rounded-xl border border-outline-variant/60 bg-white p-5" aria-labelledby="document-checklist-title">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="document-checklist-title" class="font-bold">Document checklist</h2><p class="mt-1 text-xs text-on-surface-variant">{{ $workspace['documents']['completed'] }} of {{ $workspace['documents']['total'] }} required workflow documents prepared.</p></div><x-procurement.status-badge :label="$workspace['documents']['is_complete'] ? 'Complete' : 'Missing requirement'" :tone="$workspace['documents']['is_complete'] ? 'verified' : 'attention'" /></div>
        @if(!$workspace['documents']['is_complete'])<ul class="mt-4 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-3">@foreach($workspace['documents']['missing'] as $missing)<li class="rounded-lg bg-surface-low px-3 py-2"><span class="font-bold">Missing requirement:</span> {{ $missing['label'] }}</li>@endforeach</ul>@endif
    </section>
    @php
        $existingDocuments = $procurementRequest->documents->keyBy('document_type');
        $comparisonItems = $procurementRequest->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'quantity' => (float) $item->quantity, 'unit' => $item->unit, 'unit_price' => (float) $item->unit_price, 'total' => (float) $item->total])->values();
        $iarReceivedMeta = $existingDocuments->get('inspection_acceptance_report')?->metadata['received_items'] ?? [];
        $iarReceivedComparisonItems = $comparisonItems->map(function ($item) use ($iarReceivedMeta) {
            $receivedQuantity = $iarReceivedMeta[$item['id']] ?? $item['quantity'];
            $receivedQuantity = $receivedQuantity === '' || $receivedQuantity === null ? $item['quantity'] : (float) $receivedQuantity;
            return array_merge($item, ['quantity' => $receivedQuantity, 'po_quantity' => $item['quantity']]);
        })->values();
        $hasDeliveryReconciliationData = $existingDocuments->has('purchase_order') && $existingDocuments->has('inspection_acceptance_report');
    @endphp
    <nav class="mb-4 inline-flex rounded-xl border border-outline-variant/60 bg-white p-1 text-xs font-bold" aria-label="Document stage">
        <a href="{{ route('procurement.documents', $procurementRequest) }}" @if($stage !== 'receiving') aria-current="page" @endif class="flex items-center gap-1.5 rounded-lg px-4 py-2 {{ $stage !== 'receiving' ? 'bg-primary text-white' : 'text-primary hover:bg-surface-low' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">request_quote</span>Procurement documents</a>
        <a href="{{ route('procurement.documents', [$procurementRequest, 'stage' => 'receiving']) }}" @if($stage === 'receiving') aria-current="page" @endif class="flex items-center gap-1.5 rounded-lg px-4 py-2 {{ $stage === 'receiving' ? 'bg-primary text-white' : 'text-primary hover:bg-surface-low' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">inventory_2</span>Receiving documents</a>
    </nav>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @if($stage !== 'receiving')
        <article class="doc-card doc-card--saved flex min-h-52 flex-col justify-between rounded border border-outline-variant/40 bg-white p-5 hover:border-primary"><div><div class="mb-4 flex items-center justify-between"><span class="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-primary"><span class="material-symbols-outlined">description</span></span><span class="rounded-full bg-secondary/10 px-2 py-1 text-xs font-semibold text-secondary">Prepared</span></div><p class="text-xs font-semibold uppercase tracking-wider text-primary">PR</p><h3 class="mt-1 text-lg font-semibold">Purchase Request</h3><p class="mt-2 text-xs text-on-surface-variant">{{ $procurementRequest->request_number }} · {{ ($procurementRequest->requested_at ?? $procurementRequest->created_at)->format('M d, Y') }}</p></div><div class="mt-5 flex gap-2"><a href="{{ route('procurement.print', $procurementRequest) }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-1 rounded bg-primary px-3 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]">print</span>View &amp; Print</a><a href="{{ route('procurement.edit', $procurementRequest) }}" class="rounded border border-outline-variant/50 px-3 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Edit</a></div></article>
        @endif
        @foreach($documentTypes as $typeKey => $definition)
            @php $saved = $existingDocuments->get($typeKey); @endphp
            @php($comingSoon = $typeKey === 'property_acknowledgement_receipt')
            <article class="doc-card {{ $comingSoon ? 'doc-card--soon' : ($saved ? 'doc-card--saved' : 'doc-card--missing') }} flex min-h-52 flex-col justify-between rounded border p-5 {{ $comingSoon ? 'border-outline-variant/30 bg-surface-container/60 text-on-surface-variant grayscale' : 'border-outline-variant/40 bg-white hover:border-primary' }}"><div><div class="mb-4 flex items-center justify-between"><span class="flex h-10 w-10 items-center justify-center rounded {{ $comingSoon ? 'bg-outline-variant/30 text-on-surface-variant' : 'bg-primary/10 text-primary' }}"><span class="material-symbols-outlined">{{ $comingSoon ? 'lock' : $definition['icon'] }}</span></span><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $comingSoon ? 'bg-outline-variant/30 text-on-surface-variant' : ($saved ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error') }}">{{ $comingSoon ? 'Coming soon' : ($saved ? 'Prepared' : 'Not prepared') }}</span></div><p class="text-xs font-semibold uppercase tracking-wider {{ $comingSoon ? 'text-on-surface-variant' : 'text-primary' }}">{{ $definition['short'] }}</p><h3 class="mt-1 text-lg font-semibold">{{ $definition['label'] }}</h3>@if($comingSoon)<p class="mt-2 text-xs leading-5">Official template not yet available.</p>@elseif($saved)<p class="mt-2 text-xs text-on-surface-variant">{{ $saved->document_number }} · {{ $saved->document_date->format('M d, Y') }}</p>@else<p class="mt-2 text-xs leading-5 text-on-surface-variant">Create this document using the purchase request’s school, purpose, items, and amount.</p>@endif</div><div class="mt-5 flex gap-2">@if($comingSoon)<span class="flex w-full items-center justify-center gap-2 rounded border border-outline-variant/40 bg-surface-container px-3 py-2.5 text-xs font-semibold text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">lock</span>Template coming soon</span>@elseif($saved)<a href="{{ route('procurement.documents.print', [$procurementRequest, $saved]) }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-1 rounded bg-primary px-3 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]">print</span>View &amp; Print</a><button type="button" data-document-type="{{ $typeKey }}" data-document-label="{{ $definition['label'] }}" data-document-date="{{ $saved->document_date->format('Y-m-d') }}" data-recipient="{{ $saved->supplier_or_recipient }}" data-notes="{{ $saved->notes }}" class="rounded border border-outline-variant/50 px-3 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Edit</button>@elseif(! empty($locks[$typeKey]))<span class="flex w-full items-center justify-center gap-2 rounded border border-outline-variant/40 bg-surface-container px-3 py-2.5 text-center text-xs font-semibold text-on-surface-variant" title="{{ $locks[$typeKey] }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">lock</span>{{ $locks[$typeKey] }}</span>@else<button type="button" data-document-type="{{ $typeKey }}" data-document-label="{{ $definition['label'] }}" class="w-full rounded bg-primary px-3 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Create Document</button>@endif</div></article>
        @endforeach
    </div>
    <section class="mt-6 rounded border border-outline-variant/40 bg-white p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Internal Report</p>
                <h3 class="mt-1 text-lg font-semibold">Delivery Reconciliation Report</h3>
                <p class="mt-2 text-xs leading-5 text-on-surface-variant">Working report only. Lists completed and partial deliveries based on the Purchase Order and IAR received quantities.</p>
            </div>
            @if($hasDeliveryReconciliationData)
                <a href="{{ route('procurement.delivery-reconciliation', $procurementRequest) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]">fact_check</span>View Report</a>
            @else
                <span class="inline-flex items-center justify-center gap-2 rounded border border-outline-variant/40 bg-surface-container px-4 py-2.5 text-xs font-semibold text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">lock</span>Requires PO and IAR</span>
            @endif
        </div>
    </section>
<div id="document-modal" role="dialog" aria-modal="true" aria-labelledby="document-modal-title" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-3"><div id="document-modal-card" class="flex max-h-[96vh] w-full max-w-[1500px] flex-col overflow-hidden rounded bg-white shadow-2xl"><div class="z-10 flex shrink-0 items-center justify-between border-b border-outline-variant/30 bg-white px-5 py-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-primary">Prepare Official Document</p><h2 id="document-modal-title" class="mt-1 text-lg font-semibold"></h2></div><button id="close-document-modal" type="button" aria-label="Close document form" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
<div id="doc-split"><aside id="doc-preview-pane" aria-label="Document preview"><div id="doc-preview-bar"><span>Document (updates as you edit)</span><span class="flex items-center gap-3"><small id="doc-preview-status">Updates as you type</small><select id="doc-preview-zoom" aria-label="Preview zoom" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option></select></span></div><iframe id="doc-preview-frame" title="Official document preview"></iframe></aside><div id="doc-form-pane">
    <form id="document-form" method="POST" action="{{ route('procurement.documents.store', $procurementRequest) }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">@csrf<input id="document-type" type="hidden" name="document_type">
        <label id="document-date-field" class="block text-xs font-semibold text-on-surface-variant">Document Date<input id="document-date" type="date" name="document_date" required value="{{ now()->format('Y-m-d') }}" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-supplier-field data-hide-for-po data-hide-for-iar data-hide-for-ntp class="block text-xs font-semibold text-on-surface-variant">Supplier / Recipient / Custodian<input id="document-recipient" name="supplier_or_recipient" list="supplier-manager-list" placeholder="Select or enter the applicable name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <datalist id="supplier-manager-list">@foreach($suppliers as $supplier)<option value="{{ $supplier->business_name }}">{{ $supplier->addressee ? $supplier->addressee.' · ' : '' }}{{ $supplier->business_address }}</option>@endforeach</datalist>
        <div data-award-only data-hide-for-po class="hidden rounded border border-secondary/30 bg-secondary/10 px-3 py-3 text-xs text-secondary md:col-span-2"><span id="award-winner-label" class="font-semibold">Awardee from Abstract:</span> <span id="award-winner-name"></span><span id="award-winner-total" class="ml-2"></span></div>
        <section id="noa-winner-details" class="hidden rounded-lg border border-secondary/25 bg-secondary/5 p-4 md:col-span-2"><div class="mb-4 border-b border-secondary/20 pb-3"><p id="winner-details-title" class="text-sm font-semibold text-secondary">Winning bidder details</p><p id="winner-details-help" class="mt-1 text-xs text-on-surface-variant">Information below is filled automatically from the winning bid, Supplier Manager, and Purchase Request.</p></div><dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm md:grid-cols-2"><div><dt class="text-xs font-semibold text-on-surface-variant">Supplier</dt><dd id="noa-supplier-name" class="mt-1 font-semibold"></dd></div><div><dt class="text-xs font-semibold text-on-surface-variant">Letter Addressee</dt><dd id="noa-letter-addressee" class="mt-1"></dd></div><div><dt class="text-xs font-semibold text-on-surface-variant">Supplier Address</dt><dd id="noa-supplier-address" class="mt-1"></dd></div><div><dt class="text-xs font-semibold text-on-surface-variant">TIN</dt><dd id="noa-supplier-tin" class="mt-1"></dd></div><div data-noa-only><dt class="text-xs font-semibold text-on-surface-variant">Owner of the Company</dt><dd id="noa-supplier-owner" class="mt-1"></dd></div><div><dt class="text-xs font-semibold text-on-surface-variant">PR Number</dt><dd id="noa-pr-number" class="mt-1"></dd></div><div data-noa-only><dt class="text-xs font-semibold text-on-surface-variant">PR ABC Amount</dt><dd id="noa-pr-abc" class="mt-1 font-semibold text-primary"></dd></div><div data-noa-only><dt class="text-xs font-semibold text-on-surface-variant">Winning Bid Amount</dt><dd id="noa-bid-amount" class="mt-1 font-semibold text-secondary"></dd></div></dl></section>
        <datalist id="school-staff-list">@foreach($schoolStaff as $member)<option value="{{ $member->name }}">{{ $member->position }}</option>@endforeach</datalist>
        <div data-iar-received-only class="hidden rounded border border-primary/20 bg-primary/5 p-4 md:col-span-2"><p class="text-sm font-semibold text-primary">Items received</p><p class="mt-1 text-xs text-on-surface-variant">Enter the actual quantity received for each item from the Purchase Order.</p><div id="iar-received-items" class="mt-3 overflow-x-auto"></div></div>
        <section id="ics-setup-section" class="hidden rounded-lg border border-primary/20 bg-primary/5 p-4 md:col-span-2">
            <div class="mb-4 flex items-start gap-3 border-b border-primary/15 pb-3">
                <span class="material-symbols-outlined rounded bg-white p-2 text-primary">inventory_2</span>
                <div>
                    <p class="text-sm font-semibold text-primary">ICS accountable item setup</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Select only inventory/accountable items from the IAR, then assign each item to its custodian. Ordinary consumable supplies should stay in RIS/IARS.</p>
                </div>
            </div>
            <div id="ics-items-table" class="overflow-x-auto"></div>
        </section>
        <section id="document-number-section" class="rounded-lg border border-primary/20 bg-surface-low/50 p-4 md:col-span-2"><div class="mb-4 flex items-center justify-between gap-4 border-b border-outline-variant/30 pb-3"><p id="document-number-title" class="text-sm font-semibold text-primary">Document Number</p><label class="flex shrink-0 items-center gap-2 text-xs font-semibold text-on-surface-variant"><input id="manually-encode-document-number" type="checkbox" name="manually_encode_document_number" value="1" class="rounded border-outline-variant/50 text-primary focus:ring-primary">Manually encode</label></div><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label id="document-number-preview-label" class="block text-xs font-semibold text-on-surface-variant">Document No.<input id="document-number-preview" type="text" readonly class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal text-on-surface-variant outline-none"></label><label id="manual-document-number-field" class="hidden block text-xs font-semibold text-on-surface-variant">Manual Document No.<input id="manual-document-number" name="manual_document_number" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label></div></section>
        <section id="ris-summary-section" class="hidden rounded-lg border border-primary/20 bg-primary/5 p-4 md:col-span-2">
            <div class="mb-4 flex items-start gap-3 border-b border-primary/15 pb-3">
                <span class="material-symbols-outlined rounded bg-white p-2 text-primary">summarize</span>
                <div>
                    <p class="text-sm font-semibold text-primary">RIS generation summary</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Generated from the saved IARS distribution. Each staff member will print on a separate RIS sheet.</p>
                </div>
            </div>
            <div id="ris-summary-content" class="space-y-3"></div>
        </section>
        <section data-po-only class="hidden rounded-lg border border-secondary/25 bg-secondary/5 p-4 md:col-span-2"><p class="mb-4 border-b border-secondary/20 pb-3 text-sm font-semibold text-secondary">Supplier details</p><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label class="block text-xs font-semibold text-on-surface-variant md:col-span-2">Supplier<input id="po-supplier-name" type="text" readonly class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal text-on-surface-variant outline-none"></label><label class="block text-xs font-semibold text-on-surface-variant">Supplier Address<input data-meta name="supplier_address" placeholder="Supplier business address" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">TIN<input data-meta name="tin" placeholder="Supplier TIN" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label></div></section>
        <section data-po-only class="hidden rounded-lg border border-outline-variant/40 bg-white p-4 md:col-span-2"><p class="mb-4 border-b border-outline-variant/30 pb-3 text-sm font-semibold text-primary">Delivery and payment details</p><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label class="block text-xs font-semibold text-on-surface-variant">Mode of Procurement<input data-meta name="mode_of_procurement" value="SVP" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">Place of Delivery<input data-meta name="place_of_delivery" placeholder="Delivery location" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant md:col-span-2">Date of Delivery<input data-meta name="delivery_schedule" placeholder="e.g. Within 30 calendar days from receipt of the Notice to Proceed" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">Delivery Term<input data-meta name="delivery_term" value="Pick-Up" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label><label class="block text-xs font-semibold text-on-surface-variant">Payment Term<input data-meta name="payment_term" value="30 days" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label></div></section>
        <section id="rfq-details-panel" data-rfq-only class="hidden rounded-lg border border-primary/20 bg-surface-low/50 p-5 md:col-span-2"><div class="mb-5 flex items-start gap-3 border-b border-outline-variant/30 pb-4"><span class="material-symbols-outlined rounded bg-primary/10 p-2 text-primary">request_quote</span><div><p class="text-sm font-semibold text-primary">RFQ details</p><p class="mt-1 text-xs text-on-surface-variant">Supplier information is optional. Complete it only when preparing an RFQ for a specific supplier.</p></div></div><div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">Company / Business Name <span class="font-normal">(optional)</span><input data-meta name="business_name" placeholder="Leave blank for general canvassing" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">Address <span class="font-normal">(optional)</span><input data-meta name="business_address" placeholder="Leave blank for general canvassing" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">Business / Mayor's Permit No. <span class="font-normal">(optional)</span><input data-meta name="business_permit_no" placeholder="Enter permit number, if applicable" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">PhilGEPS Registration Number <span class="font-normal">(optional)</span><input data-meta name="philgeps_no" placeholder="Enter PhilGEPS registration number, if applicable" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <div class="hidden border-t border-outline-variant/30 pt-1 md:col-span-2" data-rfq-only></div>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant md:col-span-2">Transaction Details / Description of Transaction<input data-meta name="transaction_description" placeholder="e.g. Procurement of Office Supplies" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant md:col-span-2">Project Title<input data-meta name="project_title" placeholder="e.g. For 3rd Qtr MOOE 2026" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only data-hide-for-rfq class="hidden text-xs font-semibold text-on-surface-variant">Date Posted <span class="font-normal">(optional)</span><input data-meta type="date" name="date_posted" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">Date of Opening<input data-meta type="date" name="date_opening" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant">Time of Opening<input data-meta type="time" name="time_opening" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-rfq-only class="hidden text-xs font-semibold text-on-surface-variant md:col-span-2">Terms of Payment<input data-meta name="terms_of_payment" placeholder="Enter the applicable payment terms" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        </div></section>
        <div id="abstract-bidders" data-abstract-only class="hidden rounded border border-primary/20 bg-primary/5 p-4 md:order-1 md:col-span-2"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><p class="text-sm font-semibold text-primary">Supplier price comparison</p><p class="mt-1 text-xs text-on-surface-variant">Choose each company, then enter its quoted unit price. Blank values mean no response.</p></div><div class="rounded bg-white px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">Approved Budget (ABC)</p><p class="mt-1 text-sm font-bold text-primary">₱{{ number_format((float) $procurementRequest->amount, 2) }}</p></div></div><div id="bidder-company-row" class="mt-4 grid gap-3 md:grid-cols-3"></div><div class="mt-3 flex justify-end"><button id="add-bidder" type="button" class="rounded border border-primary/40 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined mr-1 align-middle text-[16px]">add</span>Add bidder</button></div><div class="mt-4 overflow-x-auto"><table id="bidder-price-table" class="w-full border-collapse text-xs"></table></div></div>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">TIN<input data-meta name="tin" placeholder="Supplier TIN" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">Mode of Procurement<input data-meta name="mode_of_procurement" value="SVP" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">Delivery Term<input data-meta name="delivery_term" value="Pick-Up" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">Payment Term<input data-meta name="payment_term" value="30 days" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">Delivery Days<input data-meta type="number" min="1" max="365" name="delivery_days" value="30" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-non-rfq data-hide-for-iar data-hide-for-ntp data-hide-for-po class="block text-xs font-semibold text-on-surface-variant">Source of Fund<input data-meta name="source_of_fund" value="MOOE" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <label data-ntp-only class="hidden block text-xs font-semibold text-on-surface-variant md:col-span-2">NTP Template<select data-meta name="template_variant" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><option value="mooe">MOOE - complete delivery within 30 calendar days</option><option value="sbfp">SBFP - follow scheduled delivery periods</option></select><span class="mt-1 block font-normal text-on-surface-variant">Select the official Notice to Proceed wording to print.</span></label>
        <div data-ntp-status class="hidden rounded border border-primary/20 bg-primary/5 px-3 py-3 text-xs text-on-surface-variant"><span class="font-semibold text-primary">PO delivery reference:</span> <span id="ntp-po-delivery-status"></span></div>
        <label data-extra-rows-only data-hide-for-po class="hidden block text-xs font-semibold text-on-surface-variant">Manual blank item rows<input data-meta type="number" min="0" max="6" name="extra_blank_rows" value="0" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><span class="mt-1 block font-normal text-on-surface-variant">Add up to six empty bordered rows to the printed IAR or RIS.</span></label>
        <label data-iar-only data-iar-input class="hidden block text-xs font-semibold text-on-surface-variant">Inspection Officer / Committee Name<input data-meta name="inspection_officer_name" list="school-staff-list" placeholder="Select or enter the inspection officer name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <div id="iars-assignments" class="hidden rounded-lg border border-primary/20 bg-[#f7f9fc] p-5 md:col-span-2"><div class="flex flex-col gap-4 border-b border-outline-variant/30 pb-4 lg:flex-row lg:items-center lg:justify-between"><div class="flex items-start gap-3"><span class="material-symbols-outlined rounded bg-primary/10 p-2 text-primary">inventory</span><div><p class="text-base font-semibold text-primary">Staff Item Distribution</p><p class="mt-1 text-xs text-on-surface-variant">Select staff members and encode the quantity issued for each item. Totals and balances update automatically.</p></div></div><div class="flex gap-2"><button id="fullscreen-iars" type="button" class="inline-flex items-center gap-1 rounded border border-primary/40 bg-white px-3 py-2 text-xs font-semibold text-primary shadow-sm hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[16px]">open_in_full</span>Full screen</button><button id="add-iars-staff" type="button" class="inline-flex items-center gap-1 rounded bg-primary px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]">person_add</span>Add staff</button></div></div><div id="iars-assignment-rows" class="mt-4 space-y-3"></div></div>
        <label id="document-notes-field" class="block text-xs font-semibold text-on-surface-variant md:col-span-2">Notes / Terms<textarea id="document-notes" name="notes" rows="3" placeholder="Additional official notes or terms" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></textarea></label>
        <label data-po-only class="hidden block text-xs font-semibold text-on-surface-variant md:col-span-2">Extra Blank Rows<input data-meta type="number" min="0" max="20" name="extra_blank_rows" value="0" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4 md:col-span-2"><button id="cancel-document-modal" type="button" class="rounded border border-outline-variant/50 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button type="submit" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Save Official Document</button></div>
    </form>
</div></div></div></div>
<style>
    @media(min-width:768px){#document-modal{left:15rem}#document-modal[data-iars-modal="true"]>div{width:100%!important}}
    #doc-split{display:flex;flex-direction:row-reverse;min-height:0;flex:1}#doc-preview-pane{display:flex;min-width:0;flex:1 1 55%;flex-direction:column;background:#d9dee5;border-left:1px solid #cbd7e1}#doc-preview-bar{display:flex;justify-content:space-between;align-items:center;padding:8px 14px;background:#fff;border-bottom:1px solid #cbd7e1;font-size:12px;font-weight:700;color:#103967}#doc-preview-bar small{font-weight:400;color:#536273}#doc-preview-frame{width:100%;flex:1;min-height:60vh;border:0;background:#d9dee5}#doc-form-pane{flex:0 0 min(40%,580px);min-width:0;overflow-y:auto}#document-form{zoom:.92;grid-template-columns:minmax(0,1fr)!important}#document-form>*{grid-column:1/-1!important}
    #document-modal[data-iars-modal="true"] #doc-form-pane{flex:0 0 55%}#document-modal[data-iars-modal="true"] #document-form{zoom:.75}
    @media(max-width:1023px){#doc-split{flex-direction:column-reverse;overflow-y:auto}#doc-preview-pane{flex:none}#doc-preview-frame{min-height:55vh}#doc-form-pane{flex:none;overflow:visible}}
</style>
<style>
    #document-modal[data-iars-modal="true"]{padding:2vh 2vw}#document-modal[data-iars-modal="true"]>div{width:96vw;max-width:none;max-height:96vh}#document-modal[data-iars-modal="true"]>div>div:first-child{padding:8px 14px}#document-modal[data-iars-modal="true"] #document-modal-title{font-size:16px;line-height:1.2}#document-modal[data-iars-modal="true"] form{gap:8px;padding:10px;grid-template-columns:260px minmax(0,1fr)}#document-modal[data-iars-modal="true"] #document-date{margin-top:4px;padding:6px 8px;font-size:12px}#document-modal[data-iars-modal="true"] #iars-assignments{padding:14px}#document-modal[data-iars-modal="true"] #iars-assignments p{line-height:1.25}#document-modal[data-iars-modal="true"] form>div:last-child button{padding:8px 12px;font-size:12px}.iars-control{padding:5px 8px}#document-form[data-rfq-active="true"] [data-non-rfq],#document-form[data-rfq-active="true"] [data-abstract-only],#document-form[data-rfq-active="true"] [data-award-only],#document-form[data-rfq-active="true"] [data-extra-rows-only],#document-form[data-rfq-active="true"] [data-ntp-only],#document-form[data-rfq-active="true"] [data-iar-only],#document-form[data-rfq-active="true"] [data-iar-received-only],#document-form[data-abstract-active="true"] [data-non-rfq],#document-form[data-abstract-active="true"] [data-supplier-field],#document-form[data-abstract-active="true"] [data-extra-rows-only],#document-form[data-abstract-active="true"] [data-ntp-only],#document-form[data-abstract-active="true"] [data-iar-only],#document-form[data-abstract-active="true"] [data-iar-received-only],#document-form[data-abstract-active="true"] #document-notes-field{display:none!important}
    .iars-distribution-table{width:max-content;min-width:100%;font-size:12px}.iars-distribution-table th{position:sticky;top:0;z-index:3;background:#f8fafc;border-bottom:1px solid #d9deea;color:#1f2937;line-height:1.15}.iars-distribution-table td{border-bottom:1px solid #e5e7eb}.iars-sticky-cell{position:sticky;left:0;z-index:4;min-width:230px;background:inherit}.iars-item-head{min-width:96px;max-width:112px;padding:10px 8px;text-align:center;white-space:normal;overflow-wrap:anywhere}.iars-distribution-table tfoot td{border-top:1px solid #d9deea}#iars-assignment-rows select,#iars-assignment-rows input{font-size:12px;line-height:1.2}#iars-assignments:fullscreen{width:100vw;height:100vh;max-width:none;overflow:auto;border:0;border-radius:0;padding:14px;background:#f7f9fc}#document-modal[data-iars-modal="true"] #iars-assignment-rows{max-height:calc(96vh - 210px);overflow:auto}#document-form[data-noa-active="true"] [data-non-rfq],#document-form[data-noa-active="true"] [data-supplier-field],#document-form[data-noa-active="true"] [data-extra-rows-only],#document-form[data-noa-active="true"] #document-notes-field,#document-form[data-ntp-active="true"] [data-non-rfq],#document-form[data-ntp-active="true"] [data-supplier-field],#document-form[data-ntp-active="true"] [data-extra-rows-only],#document-form[data-ntp-active="true"] #document-notes-field,#document-form[data-iar-active="true"] [data-non-rfq],#document-form[data-iar-active="true"] [data-supplier-field],#document-form[data-iar-active="true"] #document-notes-field,#document-form[data-ris-active="true"] [data-non-rfq],#document-form[data-ris-active="true"] [data-supplier-field],#document-form[data-ris-active="true"] [data-ntp-only],#document-form[data-ris-active="true"] [data-iar-only],#document-form[data-ris-active="true"] [data-iar-received-only],#document-form[data-ris-active="true"] #document-notes-field,#document-form[data-iars-active="true"] #document-date-field,#document-form[data-iars-active="true"] #document-number-section,#document-form[data-iars-active="true"] [data-non-rfq],#document-form[data-iars-active="true"] [data-supplier-field],#document-form[data-iars-active="true"] [data-extra-rows-only],#document-form[data-iars-active="true"] [data-ntp-only],#document-form[data-iars-active="true"] [data-iar-only],#document-form[data-iars-active="true"] [data-iar-received-only],#document-form[data-iars-active="true"] #document-notes-field{display:none!important}
</style>
<script>
    const modal = document.getElementById('document-modal');
    const savedMetadata = @json($procurementRequest->documents->mapWithKeys(fn ($document) => [$document->document_type => $document->metadata ?? []]));
    const savedDocumentDates = @json($procurementRequest->documents->mapWithKeys(fn ($document) => [$document->document_type => optional($document->document_date)->format('Y-m-d')]));
    const savedDocumentNumbers = @json($procurementRequest->documents->mapWithKeys(fn ($document) => [$document->document_type => $document->document_number]));
    const documentCodes = @json($documentCodes);
    const nextDocumentNumbers = @json($nextDocumentNumbers);
    const defaultMetadata = {mode_of_procurement:'SVP',delivery_term:'Pick-Up',payment_term:'30 days',delivery_days:'30',source_of_fund:@json($procurementRequest->source_of_fund ?: 'MOOE'),place_of_delivery:@json($procurementRequest->school?->name ?? ''),delivery_schedule:'',transaction_description:@json($procurementRequest->transaction_description ?? ''),project_title:@json($procurementRequest->title ?? ''),inspection_officer_name:@json($inspectionOfficerName),template_variant:'sbfp'};
    const rfqDefaults = {transaction_description:@json($procurementRequest->transaction_description ?? ''),project_title:@json($procurementRequest->title ?? ''),date_posted:@json(now()->format('Y-m-d'))};
    const abstractWinner = @json($abstractWinner);
    const purchaseOrder = @json($purchaseOrder);
    const supplierNames = @json($suppliers->pluck('business_name')->values());
    const supplierProfiles = @json($suppliers->keyBy('business_name'));
    const procurementSummary = @json(['number' => $procurementRequest->request_number, 'amount' => (float) $procurementRequest->amount]);
    const applySupplierManagerDetails = () => {
        const supplier = supplierProfiles[document.getElementById('document-recipient').value];
        if (!supplier) return;
        const addressField = document.querySelector('[name="supplier_address"]');
        const tinField = document.querySelector('[name="tin"]');
        if (addressField) addressField.value = supplier.business_address || '';
        if (tinField) tinField.value = supplier.tin || '';
    };
    const schoolStaff = @json($schoolStaff);
    const comparisonItems = @json($comparisonItems);
    const iarReceivedComparisonItems = @json($iarReceivedComparisonItems);
    const bidderCompanyRow = document.getElementById('bidder-company-row');
    const bidderPriceTable = document.getElementById('bidder-price-table');
    let activeBidders = [];
    let iarsAssignments = [];
    let iarReceivedItems = {};
    let icsItems = {};
    const distributionItems = () => iarReceivedComparisonItems;
    const renderIarReceivedItems = () => {
        const host = document.getElementById('iar-received-items');
        host.innerHTML = `<table class="min-w-full border-collapse text-xs"><thead><tr class="bg-white"><th class="border px-3 py-2 text-left">Item</th><th class="border px-3 py-2 text-center">PO quantity</th><th class="border px-3 py-2 text-center">Quantity received</th></tr></thead><tbody>${comparisonItems.map(item => `<tr><td class="border px-3 py-2">${escapeHtml(item.name)} <span class="text-on-surface-variant">(${escapeHtml(item.unit)})</span></td><td class="border px-3 py-2 text-center">${item.quantity}</td><td class="border p-1"><input data-iar-received-item="${item.id}" type="number" min="0" max="${item.quantity}" step="1" name="received_items[${item.id}]" value="${iarReceivedItems[item.id] ?? item.quantity}" class="w-28 rounded border px-2 py-1.5 text-center"></td></tr>`).join('')}</tbody></table>`;
    };
    const renderIcsItems = () => {
        const host = document.getElementById('ics-items-table');
        const staffOptions = (selectedId) => schoolStaff.map((staff) => `<option value="${staff.id}" ${String(selectedId || '') === String(staff.id) ? 'selected' : ''}>${escapeHtml(staff.name)} · ${escapeHtml(staff.position || 'No designation')}</option>`).join('');
        host.innerHTML = `<table class="min-w-full border-collapse text-xs">
            <thead><tr class="bg-white text-left"><th class="border px-3 py-2">Include</th><th class="border px-3 py-2">Item</th><th class="border px-3 py-2 text-center">IAR received</th><th class="border px-3 py-2">Custodian</th><th class="border px-3 py-2 text-center">ICS qty</th><th class="border px-3 py-2">Inventory Item No.</th><th class="border px-3 py-2">Useful Life</th></tr></thead>
            <tbody>${iarReceivedComparisonItems.map((item) => {
                const saved = icsItems[item.id] || {};
                const checked = saved.included ? 'checked' : '';
                const disabled = saved.included ? '' : 'disabled';
                return `<tr class="bg-white">
                    <td class="border px-3 py-2 text-center"><input data-ics-include="${item.id}" type="checkbox" name="ics_items[${item.id}][included]" value="1" ${checked} class="rounded border-outline-variant/50 text-primary"></td>
                    <td class="border px-3 py-2"><span class="font-semibold">${escapeHtml(item.name)}</span><span class="block text-[11px] text-on-surface-variant">${escapeHtml(item.unit)} · PO ${item.po_quantity}</span></td>
                    <td class="border px-3 py-2 text-center font-semibold text-primary">${item.quantity}</td>
                    <td class="border p-1"><select data-ics-control="${item.id}" name="ics_items[${item.id}][custodian_id]" ${disabled} class="min-w-48 rounded border border-outline-variant/50 bg-white px-2 py-1.5"><option value="">Select custodian</option>${staffOptions(saved.custodian_id)}</select></td>
                    <td class="border p-1 text-center"><input data-ics-control="${item.id}" type="number" min="0" max="${item.quantity}" step="1" name="ics_items[${item.id}][quantity]" value="${escapeHtml(saved.quantity ?? item.quantity)}" ${disabled} class="w-20 rounded border border-outline-variant/50 bg-white px-2 py-1.5 text-center"></td>
                    <td class="border p-1"><input data-ics-control="${item.id}" name="ics_items[${item.id}][inventory_item_number]" value="${escapeHtml(saved.inventory_item_number ?? '')}" ${disabled} placeholder="Optional" class="w-36 rounded border border-outline-variant/50 bg-white px-2 py-1.5"></td>
                    <td class="border p-1"><input data-ics-control="${item.id}" name="ics_items[${item.id}][useful_life]" value="${escapeHtml(saved.useful_life ?? '')}" ${disabled} placeholder="e.g. 3 years" class="w-32 rounded border border-outline-variant/50 bg-white px-2 py-1.5"></td>
                </tr>`;
            }).join('')}</tbody>
        </table>`;
    };
    const updateIarsBalances = () => distributionItems().forEach((item) => {
        const total = [...document.querySelectorAll(`[data-iars-item="${item.id}"]`)].reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        document.getElementById(`iars-total-${item.id}`).textContent = total;
        const balance = Number(item.quantity) - total;
        const balanceCell = document.getElementById(`iars-balance-${item.id}`);
        balanceCell.textContent = balance;
        balanceCell.classList.toggle('text-error', balance < 0);
        balanceCell.classList.toggle('text-secondary', balance >= 0);
    });
    const readIarsAssignments = () => [...document.querySelectorAll('#iars-assignment-rows tbody tr')].map((row) => ({
        staff_id: row.querySelector('[data-iars-staff]')?.value || '',
        items: Object.fromEntries(distributionItems().map((item) => [item.id, row.querySelector(`[data-iars-item="${item.id}"]`)?.value || ''])),
    }));
    const renderIarsAssignments = () => {
        const host = document.getElementById('iars-assignment-rows');
        const staffName = id => schoolStaff.find(s => String(s.id) === String(id));
        const selectedStaffIds = iarsAssignments.map(row => String(row.staff_id || '')).filter(Boolean);
        const staffOptions = (selectedId) => schoolStaff.map((staff) => {
            const selected = String(selectedId) === String(staff.id);
            const alreadyUsed = selectedStaffIds.includes(String(staff.id)) && !selected;
            return `<option value="${staff.id}" ${selected ? 'selected' : ''} ${alreadyUsed ? 'disabled' : ''}>${escapeHtml(staff.name)}${alreadyUsed ? ' (already added)' : ''}</option>`;
        }).join('');
        const iarsItems = distributionItems();
        const itemHeaders = iarsItems.map(item => `<th class="iars-item-head"><div class="font-semibold">${escapeHtml(item.name)}</div><div class="mt-0.5 text-[10px] font-normal text-on-surface-variant">${escapeHtml(item.unit)} · received ${item.quantity}</div></th>`).join('');
        const rows = iarsAssignments.map((assignment, index) => {
            const selected = staffName(assignment.staff_id);
            return `<tr class="bg-white transition-colors hover:bg-primary/5">
                <td class="iars-sticky-cell px-3 py-2">
                    <select data-iars-staff name="iars_assignments[${index}][staff_id]" class="w-full rounded-md border border-outline-variant/50 bg-white px-2.5 py-2 text-xs font-medium outline-none focus:border-primary">
                        <option value="">Select staff</option>${staffOptions(assignment.staff_id)}
                    </select>
                </td>
                <td data-iars-position class="px-3 py-2 text-xs text-on-surface-variant">${escapeHtml(selected?.position || '—')}</td>
                ${iarsItems.map(item => `<td class="px-2 py-2 text-center"><input data-iars-item="${item.id}" type="number" min="0" max="${item.quantity}" step="1" name="iars_assignments[${index}][items][${item.id}]" value="${escapeHtml(assignment.items?.[item.id] ?? '')}" class="w-20 rounded-md border border-outline-variant/50 bg-surface-low px-2 py-2 text-center text-xs font-semibold outline-none focus:border-primary focus:bg-white"></td>`).join('')}
                <td class="px-3 py-2 text-center"><button type="button" data-remove-iars="${index}" class="inline-flex items-center justify-center rounded-md bg-error/10 px-2.5 py-1.5 text-xs font-semibold text-error hover:bg-error hover:text-white">Remove</button></td>
            </tr>`;
        }).join('');
        host.innerHTML = `${schoolStaff.length ? '' : '<div class="rounded border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">No staff records have been added yet. Add staff under School Settings first.</div>'}
            <div class="overflow-hidden rounded-lg border border-outline-variant/40 bg-white shadow-sm">
                <div class="overflow-auto">
                    <table class="iars-distribution-table min-w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th class="iars-sticky-cell px-3 py-3 text-left">Name of Staff</th>
                                <th class="px-3 py-3 text-left">Designation</th>
                                ${itemHeaders}
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/30">${rows || `<tr><td colspan="${iarsItems.length + 3}" class="px-4 py-8 text-center text-sm text-on-surface-variant">No staff distribution yet. Click <strong>Add staff</strong> to begin.</td></tr>`}</tbody>
                        <tfoot>
                            <tr class="bg-primary/5 font-semibold text-primary"><td colspan="2" class="px-3 py-2 text-right">Total Distributed</td>${iarsItems.map(item => `<td id="iars-total-${item.id}" class="px-2 py-2 text-center">0</td>`).join('')}<td></td></tr>
                            <tr class="bg-white font-semibold"><td colspan="2" class="px-3 py-2 text-right">Remaining Balance from IAR Received Items</td>${iarsItems.map(item => `<td id="iars-balance-${item.id}" class="px-2 py-2 text-center text-secondary">${item.quantity}</td>`).join('')}<td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>`;
        updateIarsBalances();
    };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const risBaseNumber = (number) => {
        const match = String(number || '').match(/^RIS-(\d{4})-(\d+)(?:-\d+)?$/);
        return match ? `RIS-${match[1]}-${String(Number(match[2])).padStart(4, '0')}` : String(number || '');
    };
    const renderRisSummary = (baseNumber) => {
        const section = document.getElementById('ris-summary-section');
        const content = document.getElementById('ris-summary-content');
        const savedIars = savedMetadata.inventory_acknowledgement_receipt_supplies?.iars_assignments || [];
        section.classList.remove('hidden');
        if (!savedIars.length) {
            content.innerHTML = '<div class="rounded border border-error/30 bg-white px-3 py-3 text-xs font-semibold text-error">No saved IARS distribution found yet. Save IARS first to generate one RIS sheet per staff member.</div>';
            return;
        }
        const rows = savedIars.filter(row => row.staff_id).map((row, index) => {
            const staff = schoolStaff.find(item => String(item.id) === String(row.staff_id));
            const issuedItems = comparisonItems
                .map(item => ({...item, issued: Number(row.items?.[item.id] || 0)}))
                .filter(item => item.issued > 0);
            const itemBadges = issuedItems.length
                ? issuedItems.map(item => `<span class="rounded bg-white px-2 py-1 text-[11px] font-semibold text-on-surface shadow-sm">${escapeHtml(item.name)}: ${item.issued} ${escapeHtml(item.unit || '')}</span>`).join('')
                : '<span class="text-xs text-on-surface-variant">No issued items encoded for this staff member.</span>';
            return `<li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 px-3 py-2.5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary/10 text-[11px] font-bold text-primary">${index + 1}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-on-surface">${escapeHtml(staff?.name || 'Unnamed staff')}</p>
                        <p class="truncate text-xs text-on-surface-variant">${escapeHtml(staff?.position || 'No designation')}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">${itemBadges}</div>
                <span class="shrink-0 rounded bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">${escapeHtml(risBaseNumber(baseNumber))}-${String(index + 1).padStart(3, '0')}</span>
            </li>`;
        }).join('');
        content.innerHTML = `<ul class="divide-y divide-outline-variant/40 overflow-hidden rounded-lg border border-outline-variant/40 bg-white">${rows}</ul>`;
    };
    const readActiveBidders = () => activeBidders.map((bidder, index) => ({
        name: document.querySelector(`[name="bidders[${index}][name]"]`)?.value || '',
        prices: Object.fromEntries(comparisonItems.map((item) => [item.id, document.querySelector(`[name="bidders[${index}][prices][${item.id}]"]`)?.value || ''])),
    }));
    const legacyBidders = (metadata) => [1, 2, 3].map((number) => ({name: metadata[`bidder_${number}_name`] || '', prices: metadata[`bidder_${number}_prices`] || {}}));
    const normalizeBidderPrices = (bidder) => {
        const prices = bidder.prices || {};
        const hasCurrentItemPrice = comparisonItems.some((item) => prices[item.id] !== undefined);
        if (hasCurrentItemPrice || !Object.keys(prices).length) return bidder;
        const previousPrices = Object.values(prices);
        return {...bidder, prices: Object.fromEntries(comparisonItems.map((item, index) => [item.id, previousPrices[index] ?? '']))};
    };
    const renderBidderComparison = (disabled = false) => {
        bidderCompanyRow.style.gridTemplateColumns = 'repeat(auto-fit, minmax(190px, 1fr))';
        bidderCompanyRow.innerHTML = activeBidders.map((bidder, index) => `<div class="rounded border border-outline-variant/40 bg-white p-3"><div class="mb-2 flex items-center justify-between"><span class="text-xs font-bold text-primary">Bidder ${index + 1}</span>${activeBidders.length > 3 ? `<button type="button" data-remove-bidder="${index}" class="text-xs font-semibold text-error hover:underline">Remove</button>` : ''}</div><select data-bidder-name name="bidders[${index}][name]" ${disabled ? 'disabled' : ''} class="w-full rounded border border-outline-variant/50 bg-surface-low px-2 py-2 text-xs outline-none focus:border-primary"><option value="">Select company</option>${supplierNames.map((name) => `<option value="${escapeHtml(name)}" ${bidder.name === name ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('')}</select></div>`).join('');
        const header = `<thead class="bg-white text-left"><tr><th class="border-b border-outline-variant/40 px-2 py-2 text-xs font-semibold" style="width:24%">Item</th><th class="border-b border-outline-variant/40 px-2 py-2 text-xs font-semibold">PR Unit Cost</th><th class="border-b border-outline-variant/40 px-2 py-2 text-xs font-semibold">PR Item Total</th>${activeBidders.map((bidder, index) => `<th class="border-b border-outline-variant/40 px-2 py-2 text-xs font-semibold">${escapeHtml(bidder.name || `Bidder ${index + 1}`)}</th>`).join('')}</tr></thead>`;
        const body = `<tbody>${comparisonItems.map((item) => `<tr><td class="border-b border-outline-variant/20 px-2 py-3 text-xs font-medium">${escapeHtml(item.name)} <span class="font-normal text-on-surface-variant">(${item.quantity} ${escapeHtml(item.unit)})</span></td><td class="border-b border-outline-variant/20 px-2 py-3 text-right text-xs font-semibold text-on-surface-variant">₱${Number(item.unit_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td><td class="border-b border-outline-variant/20 px-2 py-3 text-right text-xs font-semibold text-primary">₱${Number(item.total).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>${activeBidders.map((bidder, index) => `<td class="border-b border-outline-variant/20 px-2 py-3"><input data-bidder-price data-bidder-index="${index}" data-quantity="${item.quantity}" type="number" min="0" step="0.01" ${disabled ? 'disabled' : ''} name="bidders[${index}][prices][${item.id}]" value="${escapeHtml(bidder.prices?.[item.id] ?? '')}" placeholder="₱ Unit price" class="w-full rounded border border-outline-variant/50 bg-white px-2 py-2 text-xs outline-none focus:border-primary"></td>`).join('')}</tr>`).join('')}</tbody>`;
        bidderPriceTable.innerHTML = header + body + '<tfoot id="bidder-total-row"></tfoot>';
        updateBidderTotals();
    };
    const updateBidderTotals = () => {
        const totals = activeBidders.map(() => 0);
        document.querySelectorAll('[data-bidder-price]').forEach((field) => { totals[Number(field.dataset.bidderIndex)] += (Number(field.value) || 0) * Number(field.dataset.quantity); });
        document.getElementById('bidder-total-row').innerHTML = `<tr class="bg-white font-semibold"><td colspan="3" class="border-t-2 border-primary/30 px-2 py-3 text-right text-xs">Total quotation</td>${totals.map((total) => `<td class="border-t-2 border-primary/30 px-2 py-3 text-right text-xs text-primary">₱${total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>`).join('')}</tr>`;
    };
    let modalTrigger = null;
    const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); modalTrigger?.focus(); };
    document.querySelectorAll('[data-document-type]').forEach((button) => button.addEventListener('click', () => {
        modalTrigger = button;
        const selectedDocumentType = button.dataset.documentType;
        document.getElementById('document-type').value = selectedDocumentType;
        document.getElementById('document-modal-title').textContent = button.dataset.documentLabel;
        const isRfq = selectedDocumentType === 'request_for_quotation';
        const isAbstract = selectedDocumentType === 'abstract_of_bids_quotation';
        const isNoa = selectedDocumentType === 'notice_to_award';
        const isPo = selectedDocumentType === 'purchase_order';
        const isNtp = selectedDocumentType === 'notice_to_proceed';
        const isIar = selectedDocumentType === 'inspection_acceptance_report';
        const isRis = selectedDocumentType === 'requisition_issuance_slip';
        const isIcs = selectedDocumentType === 'inventory_custodian_slip';
        const isIars = selectedDocumentType === 'inventory_acknowledgement_receipt_supplies';
        document.getElementById('ris-summary-section').classList.toggle('hidden', !isRis);
        document.getElementById('ics-setup-section').classList.toggle('hidden', !isIcs);
        document.getElementById('document-date').value = button.dataset.documentDate || savedDocumentDates[selectedDocumentType] || (isNtp ? (savedDocumentDates.notice_to_award || '') : '') || (isPo ? (savedMetadata.request_for_quotation?.date_opening || '') : '') || @json(now()->format('Y-m-d'));
        const isAwardDocument = isNoa || isPo || isNtp;
        const usesWinnerDetails = isNoa || isNtp;
        modal.dataset.iarsModal = isIars ? 'true' : 'false';
        document.getElementById('document-form').dataset.rfqActive = isRfq ? 'true' : 'false';
        document.getElementById('document-form').dataset.abstractActive = isAbstract ? 'true' : 'false';
        document.getElementById('document-form').dataset.noaActive = isNoa ? 'true' : 'false';
        document.getElementById('document-form').dataset.ntpActive = isNtp ? 'true' : 'false';
        document.getElementById('document-form').dataset.iarActive = isIar ? 'true' : 'false';
        document.getElementById('document-form').dataset.risActive = isRis ? 'true' : 'false';
        document.getElementById('document-form').dataset.icsActive = isIcs ? 'true' : 'false';
        document.getElementById('document-form').dataset.iarsActive = isIars ? 'true' : 'false';
        const documentForm = document.getElementById('document-form');
        const documentNumberSection = document.getElementById('document-number-section');
        const winnerDetailsSection = document.getElementById('noa-winner-details');
        if (isNtp) {
            document.getElementById('document-date-field').after(documentNumberSection);
        } else {
            winnerDetailsSection.after(documentNumberSection);
        }
        document.querySelectorAll('[data-rfq-only]').forEach((element) => {
            element.classList.toggle('hidden', !isRfq);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = !isRfq; });
        });
        document.querySelectorAll('[data-po-only]').forEach((element) => {
            element.classList.toggle('hidden', !isPo);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = !isPo; });
        });
        document.querySelectorAll('[data-hide-for-rfq]').forEach((element) => { element.classList.add('hidden'); element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = true; }); });
        document.querySelectorAll('[data-non-rfq]').forEach((element) => {
            element.classList.toggle('hidden', isRfq || isAbstract || isIars || isIar || isRis || isIcs || usesWinnerDetails);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isRfq || isAbstract || isIars || isIar || isRis || isIcs || usesWinnerDetails; });
        });
        document.querySelectorAll('[data-supplier-field]').forEach((element) => {
            element.classList.toggle('hidden', isRfq || isAbstract || isIars || isIar || isRis || isIcs || usesWinnerDetails);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isRfq || isAbstract || isIars || isIar || isRis || isIcs || usesWinnerDetails; });
        });
        document.querySelectorAll('[data-hide-for-po]').forEach((element) => {
            element.classList.toggle('hidden', isPo);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isPo; });
        });
        document.querySelectorAll('[data-abstract-only]').forEach((element) => {
            element.classList.toggle('hidden', !isAbstract);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = !isAbstract; });
        });
        document.querySelectorAll('[data-extra-rows-only]').forEach((element) => {
            const showExtraRows = isIar || isRis;
            element.classList.toggle('hidden', !showExtraRows);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = !showExtraRows; });
        });
        document.querySelectorAll('[data-ntp-only]').forEach((element) => {
            element.classList.toggle('hidden', !isNtp);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = !isNtp; });
        });
        document.querySelectorAll('[data-ntp-status]').forEach((element) => element.classList.add('hidden'));
        document.querySelectorAll('[data-iar-only]').forEach((element) => element.classList.toggle('hidden', !isIar));
        document.querySelectorAll('[data-iar-input] input, [data-iar-input] select, [data-iar-input] textarea').forEach((control) => { control.disabled = !isIar; });
        document.querySelectorAll('[data-hide-for-iar]').forEach((element) => { element.classList.toggle('hidden', isIar); element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isIar; }); });
        document.querySelectorAll('[data-hide-for-ntp]').forEach((element) => { element.classList.toggle('hidden', isNtp); element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isNtp; }); });
        document.querySelectorAll('[data-hide-for-po]').forEach((element) => {
            element.classList.toggle('hidden', isPo);
            element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = isPo; });
        });
        document.querySelectorAll('[data-iar-received-only]').forEach((element) => { element.classList.toggle('hidden', !isIar); element.querySelectorAll('input').forEach((control) => { control.disabled = !isIar; }); });
        if (isRis) {
            document.querySelectorAll('[data-non-rfq], [data-supplier-field], [data-ntp-only], [data-iar-only], [data-iar-received-only], #document-notes-field').forEach((element) => {
                element.classList.add('hidden');
                element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = true; });
            });
        }
        if (isIcs) {
            document.querySelectorAll('[data-non-rfq], [data-supplier-field], [data-extra-rows-only], [data-ntp-only], [data-iar-only], [data-iar-received-only], #document-notes-field').forEach((element) => {
                element.classList.add('hidden');
                element.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = true; });
            });
        }
        document.querySelectorAll('[data-award-only]').forEach((element) => element.classList.toggle('hidden', !isAwardDocument || isPo || usesWinnerDetails));
        winnerDetailsSection.classList.toggle('hidden', !usesWinnerDetails);
        document.querySelectorAll('[data-noa-only]').forEach((element) => element.classList.toggle('hidden', !isNoa));
        document.getElementById('winner-details-title').textContent = isNtp ? 'Supplier details for Notice to Proceed' : 'Winning bidder details';
        document.getElementById('winner-details-help').textContent = isNtp ? 'Only the details needed for the Notice to Proceed are shown.' : 'Information below is filled automatically from the winning bid, Supplier Manager, and Purchase Request.';
        document.getElementById('iars-assignments').classList.toggle('hidden', !isIars);
        const notesField = document.getElementById('document-notes-field');
        const hideNotes = isIars || isNtp || isRfq || isAbstract || isNoa || isPo || isIar || isRis || isIcs;
        notesField.classList.toggle('hidden', hideNotes);
        notesField.querySelectorAll('textarea').forEach((control) => { control.disabled = hideNotes; });
        document.getElementById('document-recipient').value = isRfq ? '' : (isIar ? (purchaseOrder?.supplier_or_recipient || '') : (isAwardDocument ? (abstractWinner?.name || '') : (button.dataset.recipient || '')));
        document.getElementById('po-supplier-name').value = isPo ? document.getElementById('document-recipient').value : '';
        document.getElementById('award-winner-label').textContent = isNoa ? 'Winning supplier from Abstract:' : (isPo ? 'PO supplier from Abstract:' : 'NTP supplier from Abstract:');
        document.getElementById('award-winner-name').textContent = abstractWinner?.name || 'No winning bidder available';
        document.getElementById('award-winner-total').textContent = abstractWinner ? `₱${Number(abstractWinner.total).toLocaleString('en-PH', {minimumFractionDigits: 2})}` : '';
        if (usesWinnerDetails) {
            const winnerSupplier = supplierProfiles[abstractWinner?.name];
            const ownerName = winnerSupplier ? [winnerSupplier.owner_salutation, winnerSupplier.owner_given_name, winnerSupplier.owner_middle_initial ? `${winnerSupplier.owner_middle_initial}.` : '', winnerSupplier.owner_last_name].filter(Boolean).join(' ') : '';
            document.getElementById('noa-supplier-name').textContent = winnerSupplier?.business_name || abstractWinner?.name || '—';
            document.getElementById('noa-letter-addressee').textContent = winnerSupplier?.addressee || ownerName || 'The Manager';
            document.getElementById('noa-supplier-address').textContent = winnerSupplier?.business_address || '—';
            document.getElementById('noa-supplier-tin').textContent = winnerSupplier?.tin || '—';
            document.getElementById('noa-supplier-owner').textContent = ownerName || '—';
            document.getElementById('noa-pr-number').textContent = procurementSummary.number || '—';
            document.getElementById('noa-pr-abc').textContent = `₱${Number(procurementSummary.amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('noa-bid-amount').textContent = abstractWinner ? `₱${Number(abstractWinner.total).toLocaleString('en-PH', {minimumFractionDigits: 2})}` : '—';
        }
        document.getElementById('document-notes').value = button.dataset.notes || '';
        const metadata = {...defaultMetadata, ...(isRfq ? rfqDefaults : {}), ...(savedMetadata[button.dataset.documentType] || {})};
        if (isRfq && !metadata.transaction_description) metadata.transaction_description = defaultMetadata.transaction_description;
        if (isRfq && !metadata.project_title) metadata.project_title = defaultMetadata.project_title;
        if (isNtp && metadata.template_variant === 'thirty_days') metadata.template_variant = 'mooe';
        if (isNtp && metadata.template_variant === 'scheduled_delivery') metadata.template_variant = 'sbfp';
        if (isNtp) {
            const purchaseOrderMetadata = savedMetadata.purchase_order;
            const poDeliveryStatus = document.getElementById('ntp-po-delivery-status');
            if (purchaseOrderMetadata) {
                ['mode_of_procurement', 'delivery_term', 'payment_term', 'delivery_days', 'source_of_fund'].forEach((field) => {
                    if (purchaseOrderMetadata[field] !== undefined && purchaseOrderMetadata[field] !== '') metadata[field] = purchaseOrderMetadata[field];
                });
                poDeliveryStatus.textContent = 'Delivery term, payment term, delivery days, mode of procurement, and source of fund are copied from the saved Purchase Order.';
            } else {
                poDeliveryStatus.textContent = 'Save a Purchase Order first to use its delivery information on this NTP.';
            }
        }
        document.querySelectorAll('[data-meta]').forEach((field) => { field.value = metadata[field.name] ?? ''; });
        const documentCode = documentCodes[button.dataset.documentType];
        const savedDocumentNumber = savedDocumentNumbers[button.dataset.documentType] || '';
        const validDocumentNumber = new RegExp(`^${documentCode}-\\d{4}-\\d{3,}$`).test(savedDocumentNumber);
        const documentNumber = validDocumentNumber ? savedDocumentNumber : nextDocumentNumbers[button.dataset.documentType];
        const documentNumberPreview = document.getElementById('document-number-preview');
        const manualDocumentToggle = document.getElementById('manually-encode-document-number');
        const manualDocumentField = document.getElementById('manual-document-number-field');
        const manualDocumentInput = document.getElementById('manual-document-number');
        document.getElementById('document-number-title').textContent = `${documentCode} Document Number`;
        document.getElementById('document-number-preview-label').firstChild.textContent = `${documentCode} No.`;
        documentNumberPreview.value = documentNumber;
        manualDocumentToggle.checked = false;
        manualDocumentField.classList.add('hidden');
        manualDocumentInput.disabled = true;
        manualDocumentInput.value = documentNumber;
        manualDocumentInput.placeholder = `${documentCode}-{{ now()->year }}-001`;
        if (isRis) renderRisSummary(documentNumber);
        icsItems = isIcs ? (metadata.ics_items || {}) : {};
        renderIcsItems();
        document.querySelectorAll('#ics-setup-section input, #ics-setup-section select').forEach((control) => {
            if (!isIcs) control.disabled = true;
        });
        iarReceivedItems = isIar ? (metadata.received_items || Object.fromEntries(comparisonItems.map((item) => [item.id, item.quantity]))) : {};
        renderIarReceivedItems();
        document.querySelectorAll('[data-iar-received-only] input').forEach((control) => { control.disabled = !isIar; });
        iarsAssignments = isIars ? (metadata.iars_assignments || []) : []; renderIarsAssignments();
        if (isAwardDocument) {
            const supplier = supplierProfiles[abstractWinner?.name];
            const addressField = document.querySelector('[name="supplier_address"]');
            const tinField = document.querySelector('[name="tin"]');
            if (supplier) {
                if (addressField) addressField.value = supplier.business_address || '';
                if (tinField) tinField.value = supplier.tin || '';
                document.getElementById('award-winner-name').textContent = `${supplier.addressee ? supplier.addressee + ' · ' : ''}${supplier.business_name}`;
                if (isPo) document.getElementById('po-supplier-name').value = supplier.business_name;
            } else if (addressField) {
                addressField.value = '';
                addressField.placeholder = 'Add the complete business address in Supplier Manager';
            }
        }
        if (!isRfq && !isAwardDocument) applySupplierManagerDetails();
        activeBidders = (metadata.bidders?.length ? metadata.bidders : legacyBidders(metadata)).map(normalizeBidderPrices);
        renderBidderComparison(!isAbstract);
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('close-document-modal').focus();
    }));
    document.getElementById('add-bidder').addEventListener('click', () => { activeBidders = readActiveBidders(); activeBidders.push({name: '', prices: {}}); renderBidderComparison(false); });
    document.getElementById('add-iars-staff').addEventListener('click', () => { iarsAssignments = readIarsAssignments(); iarsAssignments.push({staff_id:'',items:{}}); renderIarsAssignments(); });
    document.getElementById('fullscreen-iars').addEventListener('click', () => document.getElementById('iars-assignments').requestFullscreen?.());
    document.getElementById('iars-assignment-rows').addEventListener('click', (event) => { const button=event.target.closest('[data-remove-iars]'); if (!button) return; iarsAssignments = readIarsAssignments(); iarsAssignments.splice(Number(button.dataset.removeIars),1); renderIarsAssignments(); });
    document.getElementById('iars-assignment-rows').addEventListener('input', (event) => { if (event.target.matches('[data-iars-item]')) updateIarsBalances(); });
    document.getElementById('iar-received-items').addEventListener('input', (event) => { if (event.target.matches('[data-iar-received-item]')) iarReceivedItems[event.target.dataset.iarReceivedItem] = event.target.value; });
    document.getElementById('ics-items-table').addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-ics-include]');
        if (!checkbox) return;
        document.querySelectorAll(`[data-ics-control="${checkbox.dataset.icsInclude}"]`).forEach((control) => { control.disabled = !checkbox.checked; });
    });
    document.getElementById('iars-assignment-rows').addEventListener('change', (event) => { if (!event.target.matches('[data-iars-staff]')) return; const staff=schoolStaff.find(item=>String(item.id)===event.target.value); event.target.closest('tr').querySelector('[data-iars-position]').textContent=staff?.position||''; });
    bidderCompanyRow.addEventListener('change', () => { activeBidders = readActiveBidders(); renderBidderComparison(false); });
    bidderCompanyRow.addEventListener('click', (event) => { const remove = event.target.closest('[data-remove-bidder]'); if (!remove) return; activeBidders = readActiveBidders(); activeBidders.splice(Number(remove.dataset.removeBidder), 1); renderBidderComparison(false); });
    bidderPriceTable.addEventListener('input', updateBidderTotals);
    document.getElementById('document-recipient').addEventListener('change', applySupplierManagerDetails);
    document.getElementById('manually-encode-document-number').addEventListener('change', (event) => {
        const enabled = event.target.checked;
        document.getElementById('manual-document-number-field').classList.toggle('hidden', !enabled);
        document.getElementById('manual-document-number').disabled = !enabled;
        if (enabled) document.getElementById('manual-document-number').focus();
    });
    document.getElementById('close-document-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-document-modal').addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') return closeModal();
        if (event.key !== 'Tab') return;
        const focusable = [...modal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])')].filter(element => element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    // Live preview: the left pane renders the real official document from the current form values (nothing is saved).
    (() => {
        const form = document.getElementById('document-form');
        const frame = document.getElementById('doc-preview-frame');
        const status = document.getElementById('doc-preview-status');
        const previewUrl = @json(route('procurement.documents.preview', $procurementRequest));
        let timer = null; let controller = null;
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}.official-sheet{zoom:var(--pz,1)!important;margin-top:12px!important;margin-bottom:12px!important}</style>';
        const zoomSelect = document.getElementById('doc-preview-zoom');
        const applyZoom = () => {
            const doc = frame.contentDocument;
            const sheet = doc && doc.querySelector('.official-sheet, [data-official-page]');
            if (!sheet) return;
            let zoom = parseFloat(zoomSelect.value);
            if (zoomSelect.value === 'fit') {
                doc.documentElement.style.setProperty('--pz', 1);
                const box = sheet.getBoundingClientRect();
                const byWidth = (frame.clientWidth - 48) / (Math.max(sheet.scrollWidth, box.width) || 1);
                zoom = Math.min(0.72, Math.max(0.3, byWidth));
            }
            doc.documentElement.style.setProperty('--pz', zoom);
        };
        frame.addEventListener('load', () => setTimeout(applyZoom, 350));
        zoomSelect.addEventListener('change', applyZoom);
        const refresh = async () => {
            if (modal.classList.contains('hidden')) return;
            if (controller) controller.abort();
            controller = new AbortController();
            status.textContent = 'Updating preview...';
            try {
                const response = await fetch(previewUrl, { method: 'POST', body: new FormData(form), signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                const html = await response.text();
                frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html;
                status.textContent = response.ok ? 'Updates as you type' : 'Preview unavailable';
            } catch (error) { if (error.name !== 'AbortError') status.textContent = 'Preview unavailable'; }
        };
        const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 1000); };
        // Widen the form pane automatically when the form needs more room (bidder tables, distribution lists); reset for each document.
        const pane = document.getElementById('doc-form-pane');
        const split = document.getElementById('doc-split');
        const fitPane = () => {
            if (window.innerWidth < 1024 || modal.classList.contains('hidden')) return;
            pane.style.flexBasis = '';
            const overflow = pane.scrollWidth - pane.clientWidth;
            if (overflow > 4) pane.style.flexBasis = Math.min(pane.clientWidth + overflow + 24, split.clientWidth * 0.62) + 'px';
        };
        new MutationObserver(() => requestAnimationFrame(fitPane)).observe(form, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden', 'style'] });
        window.addEventListener('resize', fitPane);
        document.querySelectorAll('[data-document-type]').forEach((button) => button.addEventListener('click', () => setTimeout(fitPane, 150)));
        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        document.querySelectorAll('[data-document-type]').forEach((button) => button.addEventListener('click', () => { frame.srcdoc = ''; setTimeout(refresh, 400); }));
    })();
</script>
@endsection
