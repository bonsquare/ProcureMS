@extends('layouts.procurement')
@php $editingRequest = $editingRequest ?? null; @endphp
@section('title', $editingRequest ? 'Edit Procurement Request' : 'New Procurement Request')
@section('page-title', $editingRequest ? 'Edit Purchase Request' : 'New Purchase Request')
@push('head')
<style>.procurement-page{max-width:none!important;display:grid;grid-template-columns:minmax(0,1fr);gap:1.25rem}.procurement-page>a:first-child{justify-self:start}.pr-preview-pane{display:none}@media(min-width:1280px){.procurement-page{grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);align-items:start}.procurement-page>a:first-child{grid-column:1/-1}.pr-preview-pane{display:flex;position:sticky;top:5rem;height:calc(100vh - 7rem);flex-direction:column;overflow:hidden;border:1px solid #cbd7e1;border-radius:.8rem;background:#d9dee5}.pr-preview-bar{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .85rem;background:#fff;border-bottom:1px solid #cbd7e1;font-size:.75rem;font-weight:700;color:#103967}.pr-preview-bar small{font-weight:400;color:#536273}.pr-preview-pane iframe{flex:1;width:100%;border:0;background:#d9dee5}}.procurement-card{border-radius:14px!important;box-shadow:0 10px 28px rgba(20,31,56,.07)}.workflow-card{border-radius:10px!important;background:linear-gradient(135deg,#f5f8ff,#fff)!important}.workflow-card span.rounded{border-color:#c5d5f6!important;background:#fff!important}.procurement-form-card label{letter-spacing:.01em}.procurement-form-card input,.procurement-form-card select{background:#fff!important;box-shadow:0 1px 1px rgba(0,0,0,.02)}.items-card{border-radius:12px!important;background:#f8f9fc!important}.items-card>.mb-4{border-bottom:1px solid #dde3ef;padding-bottom:14px}.item-row{border-radius:10px!important;background:#fff!important;box-shadow:0 2px 7px rgba(25,39,68,.04)}.procurement-add-good{box-shadow:0 2px 5px rgba(0,35,111,.12)}@media (min-width:640px){.procurement-form-card{display:grid;grid-template-columns:1fr 1fr;gap:24px}.procurement-form-card>.rounded,.procurement-form-card>.flex{grid-column:1/-1}.procurement-form-card>div:has(#school_id),.procurement-form-card>div:has(#purpose){margin:0!important}}</style>
<style>#procurement-form label{letter-spacing:.01em}#procurement-form input,#procurement-form select{background:#fff!important;box-shadow:0 1px 1px rgba(0,0,0,.02)}#procurement-form>.rounded{border-radius:12px!important;background:#f8f9fc!important;padding:20px!important}#procurement-form>.rounded>.mb-4{border-bottom:1px solid #dde3ef;padding-bottom:14px}.item-row{border-radius:10px!important;background:#fff!important;box-shadow:0 2px 7px rgba(25,39,68,.04)}@media (min-width:640px){#procurement-form{display:grid;grid-template-columns:1fr 1fr;gap:24px}#procurement-form>.rounded,#procurement-form>.flex{grid-column:1/-1}#procurement-form>div:has(#school_id),#procurement-form>div:has(#purpose){margin:0!important}}</style>
<style id="pr-form-polish">
.procurement-page .procurement-card{border:1px solid #dbe4ec!important;border-radius:14px!important;background:#fff!important;box-shadow:0 1px 2px rgba(16,57,103,.05),0 8px 24px rgba(16,57,103,.05)!important;padding:1.5rem 1.5rem 0!important}
.procurement-page .procurement-card>div:first-child{margin-bottom:1.1rem!important;padding-bottom:1rem;border-bottom:1px solid #e6edf3}
.procurement-page .procurement-card>div:first-child p:first-child{color:#286da8!important;letter-spacing:.14em;font-size:.68rem}
#procurement-form{display:flex!important;flex-direction:column;counter-reset:pr-step;gap:1rem!important;padding-bottom:0!important}
#procurement-form>*{width:100%;margin:0}
#procurement-form>div{border:1px solid #e1e8ef!important;border-radius:12px!important;background:#fbfcfe!important;padding:1.1rem!important;box-shadow:none!important}
#procurement-form>div>.mb-4,#procurement-form>div>div>.mb-4{border-bottom:1px solid #e6edf3!important;padding-bottom:.7rem!important;margin-bottom:.9rem!important}
#procurement-form h2{display:flex;align-items:center;gap:.55rem;font-size:.9rem!important;font-weight:700;color:#103967}
#procurement-form h2::before{counter-increment:pr-step;content:counter(pr-step);display:inline-grid;place-items:center;flex:none;height:1.35rem;width:1.35rem;border-radius:999px;background:#103967;color:#fff;font-size:.7rem;font-weight:700}
#procurement-form h2+p,#procurement-form .mb-4 p{margin-left:1.9rem;font-size:.74rem!important;color:#6a7988}
#procurement-form label input,#procurement-form label select,#procurement-form label textarea{font-weight:400!important}
#procurement-form label{font-size:.7rem!important;font-weight:700!important;letter-spacing:.02em;text-transform:none;color:#3f5163!important}
#procurement-form input:not([type=checkbox]),#procurement-form select,#procurement-form textarea{margin-top:.3rem!important;border:1px solid #cdd8e2!important;border-radius:8px!important;background:#fff!important;color:#172538;transition:border-color .15s,box-shadow .15s}
#procurement-form input:focus,#procurement-form select:focus,#procurement-form textarea:focus{border-color:#286da8!important;box-shadow:0 0 0 3px rgba(40,109,168,.15)!important;outline:none}
#procurement-form input[readonly]{background:#f1f5f9!important;color:#536273}
#procurement-form .items-scroll{border-radius:10px!important;border:1px solid #dbe4ec!important}
#procurement-form thead th{background:#eef3f8!important;color:#3f5163!important}
#procurement-form #add-item{border-radius:8px;font-weight:700}
#procurement-form #grand-total{font-size:1.25rem!important}
#procurement-form .item-row,#procurement-form #items tr{transition:background .15s}
#procurement-form #items tr:hover{background:#f6f9fc}
#procurement-form>.flex.justify-end:last-child,#procurement-form>div.flex.justify-end{position:sticky;bottom:0;z-index:5;margin:0 -1.5rem!important;padding:.85rem 1.5rem!important;border:0!important;border-top:1px solid #dbe4ec!important;border-radius:0 0 14px 14px!important;background:rgba(255,255,255,.96)!important;backdrop-filter:blur(6px)}
#procurement-form button[type=submit]{border-radius:8px;padding-inline:1.4rem;box-shadow:0 2px 6px rgba(16,57,103,.25)}
.pr-preview-pane{border-radius:14px!important;box-shadow:0 1px 2px rgba(16,57,103,.05),0 8px 24px rgba(16,57,103,.05)}
.blank-rows-card{display:flex;align-items:center;justify-content:space-between;gap:.75rem;border:1px solid #e1e8ef;border-radius:10px;background:#fbfcfe;padding:.45rem .8rem}
.blank-rows-text label{display:block;font-size:.74rem!important;font-weight:700!important;color:#103967!important}
.blank-rows-text p{margin-top:.15rem;font-size:.72rem;line-height:1.45;color:#6a7988}
.blank-rows-stepper{display:inline-flex;flex:none;align-items:stretch;overflow:hidden;border:1px solid #cdd8e2;border-radius:8px;background:#fff}
.blank-rows-stepper button{width:1.7rem;border:0;background:#eef3f8;color:#103967;font-size:.9rem!important;font-weight:700;line-height:1;cursor:pointer}
.blank-rows-stepper button:hover{background:#dde8f2}
#procurement-form .blank-rows-stepper input{width:2.6rem;min-height:1.7rem!important;padding:0!important;font-size:.76rem!important;margin:0!important;border:0!important;border-radius:0!important;border-inline:1px solid #cdd8e2!important;text-align:center;font-weight:700;box-shadow:none!important}
</style>
@endpush
@section('content')
<div class="procurement-page mx-auto max-w-3xl"><a href="{{ route('procurement.requests') }}" class="mb-4 inline-flex min-h-11 items-center gap-1 text-xs font-bold text-action"><span class="material-symbols-outlined text-[17px]">arrow_back</span>Back to requests</a>
    <section class="procurement-card rounded border border-outline-variant/30 bg-white p-6 sm:p-8"><div class="mb-7"><p class="text-xs font-semibold uppercase tracking-wider text-primary">Procurement Management</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $editingRequest ? 'Edit Purchase Request' : 'New Purchase Request' }}</h1><p class="mt-2 text-sm text-on-surface-variant">{{ $editingRequest ? 'Update the request information, items, and printed form spacing.' : 'Create an itemized purchase request for review and approval.' }}</p></div>
        <form id="procurement-form" method="POST" action="{{ $editingRequest ? route('procurement.update', $editingRequest) : route('procurement.store') }}" class="space-y-6 pb-24">@csrf <div class="rounded-lg border border-outline-variant/50 bg-white p-4"><h2 class="text-sm font-bold">Request details</h2><p class="mt-1 text-xs text-on-surface-variant">Funding and linkage information, items, and official request details. Choose the school first; its details fill the form below.</p><div class="mt-4"><label for="school_id" class="text-xs font-semibold text-on-surface-variant">School / Institution</label><select id="school_id" name="school_id" required class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm outline-none focus:border-primary"><option value="">Select a school</option>@foreach($schools as $school)<option value="{{ $school->id }}" data-school-name="{{ $school->name }}" data-school-division="{{ $school->division }}" data-school-region="{{ $school->region }}" data-school-district="{{ $school->district }}" @selected(old('school_id', $editingRequest?->school_id) == $school->id)>{{ $school->name }} ({{ $school->code }})</option>@endforeach</select>@error('school_id')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div></div> @if($editingRequest) @method('PUT') @endif
            <div class="rounded border border-outline-variant/30 bg-surface-low p-4">
                <div class="mb-4"><h2 class="text-sm font-semibold">Purchase Request Reference Details</h2><p class="mt-1 text-xs text-on-surface-variant">These details appear in the Purchase Request header. PR numbers use the format PR-{{ now()->year }}-001.</p></div>
                <div class="mb-4 rounded border border-primary/15 bg-white p-3">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="text-xs font-semibold text-on-surface-variant">PR Number<input readonly value="{{ old('manual_pr_number', $editingRequest?->request_number ?? $nextPrNumber) }}" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal text-on-surface-variant outline-none"></label>
                        <label class="text-xs font-semibold text-on-surface-variant">PR Date<input name="request_date" type="date" required value="{{ old('request_date', optional($editingRequest?->requested_at)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">@error('request_date')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    </div>
                    <label class="mt-3 flex cursor-pointer items-center gap-2 text-xs font-semibold text-primary"><input id="manually_encode_pr_number" name="manually_encode_pr_number" type="checkbox" value="1" @checked(old('manually_encode_pr_number')) class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">Manually encode PR Number</label>
                    <div id="manual-pr-number-wrap" class="mt-3 hidden"><label class="block text-xs font-semibold text-on-surface-variant">Manual PR Number<input id="manual_pr_number" name="manual_pr_number" value="{{ old('manual_pr_number', $editingRequest?->request_number) }}" placeholder="PR-{{ now()->year }}-001" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><span class="mt-1 block font-normal text-on-surface-variant">Use the format PR-{{ now()->year }}-001.</span>@error('manual_pr_number')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label></div>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="text-xs font-semibold text-on-surface-variant">Entity Name<input id="entity_name" name="entity_name" required maxlength="255" value="{{ old('entity_name', $editingRequest?->entity_name ?? $editingRequest?->school?->division ?? '') }}" placeholder="Select school to use its division/entity" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">@error('entity_name')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="text-xs font-semibold text-on-surface-variant">Fund Cluster / Source of Fund<input id="source_of_fund" name="source_of_fund" required maxlength="255" value="{{ old('source_of_fund', $editingRequest?->source_of_fund ?? 'MOOE') }}" placeholder="e.g. MOOE or General Fund" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">@error('source_of_fund')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    @include('partials.budget-item-select', ['budgetItems' => $budgetItems, 'selectedItem' => $editingRequest?->budget_allocation_id, 'selectClass' => 'mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary', 'labelClass' => 'text-xs font-semibold text-on-surface-variant md:col-span-2'])
                    <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Department<input id="department_name" name="department_name" required maxlength="255" value="{{ old('department_name', $editingRequest?->department_name ?? $editingRequest?->school?->name ?? '') }}" placeholder="Select a school or enter department" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">@error('department_name')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="text-xs font-semibold text-on-surface-variant">Section<input name="section" maxlength="255" value="{{ old('section', $editingRequest?->section) }}" placeholder="Enter section" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                    <label class="text-xs font-semibold text-on-surface-variant">Responsibility Center Code<input name="responsibility_center_code" maxlength="255" value="{{ old('responsibility_center_code', $editingRequest?->responsibility_center_code) }}" placeholder="Enter responsibility center code" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                    <label class="text-xs font-semibold text-on-surface-variant">SAI Number<input name="sai_number" maxlength="255" value="{{ old('sai_number', $editingRequest?->sai_number) }}" placeholder="Enter SAI number" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                    <label class="text-xs font-semibold text-on-surface-variant">SAI Date<input name="sai_date" type="date" value="{{ old('sai_date', $editingRequest?->sai_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded border border-outline-variant/50 px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                
                    @include('partials.budget-hint')
                </div>
            </div>
            <div class="rounded border border-outline-variant/30 bg-surface-low p-4"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-sm font-semibold">Items</h2><p class="mt-1 text-xs text-on-surface-variant">Enter each item on one compact line; add its specification directly below.</p></div></div><style>.items-scroll th:last-child,.items-scroll tbody td:last-child{position:sticky;right:0;z-index:1;background:#fff;box-shadow:-6px 0 8px -6px rgba(0,0,0,.18)}.items-scroll thead th:last-child{background:#f6f8fc}</style><div class="items-scroll overflow-x-auto rounded-lg border border-outline-variant/30 bg-white"><table class="w-full min-w-[820px] border-collapse text-left text-xs"><thead class="bg-[#f6f8fc] text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"><tr><th class="w-[7%] px-2 py-3 text-center">Stock<br>No.</th><th class="w-[14%] px-2 py-3">Unit</th><th class="w-[31%] px-2 py-3">Item Description</th><th class="w-[11%] px-2 py-3">Quantity</th><th class="w-[14%] px-2 py-3">Unit Cost</th><th class="w-[15%] px-2 py-3 text-right">Total Cost</th><th class="w-[8%] px-2 py-3 text-center">Action</th></tr></thead><tbody id="items" class="divide-y divide-outline-variant/20"></tbody><div id="items-status" class="sr-only" aria-live="polite"></div></table></div><div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-outline-variant/30 bg-white px-4 py-3"><button type="button" id="add-item" class="procurement-add-good flex items-center gap-1 rounded border border-primary px-4 py-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[17px]">add</span>Add Item</button><span class="ml-auto flex items-center"><span class="mr-3 text-sm font-semibold">Estimated Total</span><span id="grand-total" class="text-xl font-semibold text-primary">₱0.00</span></span></div></div>
            <div class="rounded border border-outline-variant/30 bg-surface-low p-4"><div class="mb-4"><h2 class="text-sm font-semibold">Request Details</h2><p class="mt-1 text-xs text-on-surface-variant">Describe the procurement and its project title for the transaction.</p></div><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label for="transaction_description" class="text-xs font-semibold text-on-surface-variant">Transaction Details / Description of Transaction<input id="transaction_description" name="transaction_description" value="{{ old('transaction_description', $editingRequest?->transaction_description) }}" maxlength="255" placeholder="e.g. Procurement of Office Supplies" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">@error('transaction_description')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label><label for="purpose" class="text-xs font-semibold text-on-surface-variant">Project Title<input id="purpose" name="purpose" value="{{ old('purpose', $editingRequest?->title) }}" required maxlength="255" placeholder="e.g. 3rd Quarter MOOE 2026" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">@error('purpose')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label></div></div>
            <div class="blank-rows-card"><div class="blank-rows-text"><label for="extra_blank_rows">Extra blank print rows</label></div><div class="blank-rows-stepper" role="group" aria-label="Extra blank print rows"><button type="button" data-step="-1" aria-label="Fewer blank rows">&minus;</button><input id="extra_blank_rows" name="extra_blank_rows" type="number" min="0" max="100" value="{{ old('extra_blank_rows', $editingRequest?->extra_blank_rows ?? 0) }}"><button type="button" data-step="1" aria-label="More blank rows">+</button></div></div>
            <div class="flex justify-end gap-2 border-t border-outline-variant/20 pt-6"><a href="{{ route('procurement') }}" class="rounded border border-outline-variant/50 px-4 py-2.5 text-xs font-semibold hover:border-primary hover:text-primary">Cancel</a><button type="submit" class="min-h-11 rounded-lg bg-primary px-5 text-xs font-bold text-white">Save request</button></div>
        </form>
    </section>
    <aside class="pr-preview-pane" aria-label="Purchase Request preview"><div class="pr-preview-bar"><span>Purchase Request (updates as you edit)</span><span class="flex items-center gap-2"><small id="pr-preview-status">Updates as you type</small><select id="pr-preview-zoom" aria-label="Preview zoom" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option></select></span></div><iframe id="pr-preview-frame" title="Purchase Request preview"></iframe></aside></div>
@php
    $initialItems = old('items');
    if ($initialItems === null && $editingRequest) {
        $initialItems = $editingRequest->items->map(function ($item) {
            return ['name' => $item->name, 'description' => $item->description, 'app_item_id' => $item->app_item_id, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price];
        })->values()->all();
    }
    $initialItems = $initialItems ?: [];
@endphp
<script>
    let itemIndex = 0;
    const unitOptions = @json($unitOptions);
    const unitOptionsHtml = (selected) => (!selected || unitOptions.includes(selected) ? unitOptions : [...unitOptions, selected]).map((unit) => `<option ${unit === (selected || 'piece') ? 'selected' : ''}>${escapeHtml(unit)}</option>`).join('');
    const existingItems = @json($initialItems);
    const appItems = @json($appItems);
    const items = document.getElementById('items');
    const total = document.getElementById('grand-total');
    function money(value) { return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function updateTotal() {
        let grand = 0;
        items.querySelectorAll('.item-row').forEach((row) => {
            const lineTotal = Number(row.querySelector('[data-field="quantity"]').value || 0) * Number(row.querySelector('[data-field="unit_price"]').value || 0);
            grand += lineTotal;
            row.querySelector('[data-line-total]').textContent = money(lineTotal);
        });
        total.textContent = money(grand);
    }
    function renumberItems() {
        items.querySelectorAll('.item-row').forEach((row, position) => row.querySelector('[data-stock-number]').textContent = position + 1);
    }
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    }
    function fillAppOptions(select, selected) {
        const schoolId = document.getElementById('school_id').value;
        const options = appItems.filter((candidate) => String(candidate.school_id) === String(schoolId));
        select.innerHTML = '<option value="">Not linked to the APP</option>' + options.map((candidate) => `<option value="${candidate.id}" ${String(candidate.id) === String(selected) ? 'selected' : ''}>${escapeHtml(candidate.name)} — ${candidate.remaining_quantity} ${escapeHtml(candidate.unit)} left (${money(candidate.remaining_cost)})</option>`).join('');
    }
    function addItem(item = {}) {
        const index = itemIndex++;
        const row = document.createElement('tr');
        const specificationRow = document.createElement('tr');
        row.className = 'item-row bg-white';
        specificationRow.className = 'item-spec-row bg-[#fbfcfe]';
        row.innerHTML = `<td data-stock-number class="px-2 py-2 text-center text-sm font-semibold text-primary"></td><td class="px-2 py-2"><select name="items[${index}][unit]" class="w-full rounded border border-outline-variant/50 bg-white px-2 py-2 text-xs outline-none focus:border-primary">${unitOptionsHtml(item.unit)}</select></td><td class="px-2 py-2"><input name="items[${index}][name]" required maxlength="255" placeholder="Item description" value="${escapeHtml(item.name)}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td class="px-2 py-2"><input data-field="quantity" name="items[${index}][quantity]" required min="0.01" step="0.01" value="${escapeHtml(item.quantity ?? 1)}" type="number" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td class="px-2 py-2"><input data-field="unit_price" name="items[${index}][unit_price]" required min="0" step="0.01" value="${escapeHtml(item.unit_price ?? 0)}" type="number" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td data-line-total class="px-2 py-2 text-right text-sm font-semibold text-on-surface">₱0.00</td><td class="px-2 py-2 text-center"><button type="button" class="remove-item rounded bg-red-50 px-2.5 py-2 text-[11px] font-semibold text-error hover:bg-red-100">Remove</button></td>`;
        specificationRow.innerHTML = `<td colspan="7" class="px-2 pb-3 pt-0"><label class="mb-2 flex items-center gap-3 text-[11px] font-semibold text-on-surface-variant"><span class="shrink-0">Approved APP item</span><select data-field="app_item" name="items[${index}][app_item_id]" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs font-normal outline-none focus:border-primary"></select></label><label class="flex items-center gap-3 text-[11px] font-semibold text-on-surface-variant"><span class="shrink-0">Item specification</span><input name="items[${index}][description]" maxlength="255" placeholder="Brand, size, technical specification, or other details" value="${escapeHtml(item.description)}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs font-normal outline-none focus:border-primary"></label></td>`;
        items.append(row, specificationRow);
        row.querySelectorAll('input').forEach((input) => input.addEventListener('input', updateTotal));
        const appSelect = specificationRow.querySelector('[data-field="app_item"]');
        fillAppOptions(appSelect, item.app_item_id);
        appSelect.addEventListener('change', () => {
            const planned = appItems.find((candidate) => String(candidate.id) === appSelect.value);
            if (!planned) return;
            const unit = row.querySelector('select[name$="[unit]"]');
            if (![...unit.options].some((option) => option.value === planned.unit)) unit.add(new Option(planned.unit, planned.unit));
            unit.value = planned.unit;
            row.querySelector('[name$="[name]"]').value = planned.name;
            specificationRow.querySelector('[name$="[description]"]').value = planned.specifications || '';
            row.querySelector('[data-field="quantity"]').value = planned.remaining_quantity;
            row.querySelector('[data-field="unit_price"]').value = planned.unit_price;
            updateTotal();
        });
        document.getElementById('school_id').addEventListener('change', () => fillAppOptions(appSelect, appSelect.value));
        row.querySelector('.remove-item').addEventListener('click', () => {
            if (items.querySelectorAll('.item-row').length > 1) {
                specificationRow.remove();
                row.remove();
                renumberItems();
                updateTotal();
            }
        });
        renumberItems();
        updateTotal();
    }
    document.getElementById('add-item').addEventListener('click', addItem);
    function applySchoolHeaderDefaults(event, force = false) {
        const department = document.getElementById('department_name');
        const entity = document.getElementById('entity_name');
        const schoolName = event.target.selectedOptions[0]?.dataset.schoolName || '';
        const schoolDivision = event.target.selectedOptions[0]?.dataset.schoolDivision || '';
        if (schoolName && (force || !department.value)) department.value = schoolName;
        if (schoolDivision && (force || !entity.value)) entity.value = schoolDivision;
    }
    document.getElementById('school_id').addEventListener('change', (event) => applySchoolHeaderDefaults(event, true));
    applySchoolHeaderDefaults({ target: document.getElementById('school_id') });
    const manualNumberToggle = document.getElementById('manually_encode_pr_number');
    const manualNumberWrap = document.getElementById('manual-pr-number-wrap');
    const manualNumberInput = document.getElementById('manual_pr_number');
    function toggleManualNumber() {
        manualNumberWrap.classList.toggle('hidden', !manualNumberToggle.checked);
        manualNumberInput.disabled = !manualNumberToggle.checked;
    }
    manualNumberToggle.addEventListener('change', toggleManualNumber);
    toggleManualNumber();
    (existingItems.length ? existingItems : [{}]).forEach(addItem);

    // Live Purchase Request preview (nothing is saved).
    (() => {
        const form = document.getElementById('procurement-form');
        const frame = document.getElementById('pr-preview-frame');
        if (!form || !frame || window.innerWidth < 1280) return;
        const status = document.getElementById('pr-preview-status');
        const zoomSelect = document.getElementById('pr-preview-zoom');
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}.official-sheet{zoom:var(--pz,1)!important;margin-top:12px!important;margin-bottom:12px!important}</style>';
        const previewUrl = @json(route('procurement.preview'));
        const editingId = @json($editingRequest?->id);
        let timer = null; let controller = null;
        const applyZoom = () => {
            const doc = frame.contentDocument;
            const sheet = doc && doc.querySelector('.official-sheet, [data-official-page]');
            if (!sheet) return;
            let zoom = parseFloat(zoomSelect.value);
            if (zoomSelect.value === 'fit') {
                doc.documentElement.style.setProperty('--pz', 1);
                const width = Math.max(sheet.scrollWidth, sheet.getBoundingClientRect().width) || 1;
                zoom = Math.min(0.85, Math.max(0.3, (frame.clientWidth - 48) / width));
            }
            doc.documentElement.style.setProperty('--pz', zoom);
        };
        const refresh = async () => {
            if (controller) controller.abort();
            controller = new AbortController();
            status.textContent = 'Updating preview...';
            const body = new FormData(form);
            body.delete('_method'); // the edit form spoofs PUT; the preview is a plain POST
            if (editingId) body.append('editing_id', editingId);
            try {
                const response = await fetch(previewUrl, { method: 'POST', body, signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                const html = await response.text();
                frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html;
                status.textContent = response.ok ? 'Updates as you type' : 'Preview unavailable';
            } catch (error) { if (error.name !== 'AbortError') status.textContent = 'Preview unavailable'; }
        };
        const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 1000); };
        frame.addEventListener('load', () => setTimeout(applyZoom, 350));
        zoomSelect.addEventListener('change', applyZoom);
        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        document.getElementById('add-item')?.addEventListener('click', schedule);
        form.addEventListener('click', (event) => { if (event.target.closest('button[type=button]')) schedule(); });
        setTimeout(refresh, 600);
    })();

    document.querySelectorAll('.blank-rows-stepper button').forEach((button) => button.addEventListener('click', () => {
        const input = document.getElementById('extra_blank_rows');
        const next = Math.min(100, Math.max(0, (parseInt(input.value, 10) || 0) + parseInt(button.dataset.step, 10)));
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }));
</script>
@endsection
