<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php $editingRequest = $editingRequest ?? null; @endphp
    <title>{{ $editingRequest ? 'Edit Procurement Request' : 'New Procurement Request' }} · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651','outline-variant':'#c5c5d3','error':'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>.procurement-page{max-width:1120px!important}.procurement-card{border-radius:14px!important;box-shadow:0 10px 28px rgba(20,31,56,.07)}.workflow-card{border-radius:10px!important;background:linear-gradient(135deg,#f5f8ff,#fff)!important}.workflow-card span.rounded{border-color:#c5d5f6!important;background:#fff!important}.procurement-form-card label{letter-spacing:.01em}.procurement-form-card input,.procurement-form-card select{background:#fff!important;box-shadow:0 1px 1px rgba(0,0,0,.02)}.items-card{border-radius:12px!important;background:#f8f9fc!important}.items-card>.mb-4{border-bottom:1px solid #dde3ef;padding-bottom:14px}.item-row{border-radius:10px!important;background:#fff!important;box-shadow:0 2px 7px rgba(25,39,68,.04)}.procurement-add-good{box-shadow:0 2px 5px rgba(0,35,111,.12)}@media (min-width:640px){.procurement-form-card{display:grid;grid-template-columns:1fr 1fr;gap:24px}.procurement-form-card>.rounded,.procurement-form-card>.flex{grid-column:1/-1}.procurement-form-card>div:has(#school_id),.procurement-form-card>div:has(#purpose){margin:0!important}}</style>
<style>#procurement-form label{letter-spacing:.01em}#procurement-form input,#procurement-form select{background:#fff!important;box-shadow:0 1px 1px rgba(0,0,0,.02)}#procurement-form>.rounded{border-radius:12px!important;background:#f8f9fc!important;padding:20px!important}#procurement-form>.rounded>.mb-4{border-bottom:1px solid #dde3ef;padding-bottom:14px}.item-row{border-radius:10px!important;background:#fff!important;box-shadow:0 2px 7px rgba(25,39,68,.04)}@media (min-width:640px){#procurement-form{display:grid;grid-template-columns:1fr 1fr;gap:24px}#procurement-form>.rounded,#procurement-form>.flex{grid-column:1/-1}#procurement-form>div:has(#school_id),#procurement-form>div:has(#purpose){margin:0!important}}</style>
@include('partials.input-fixes')
</head>
<body class="bg-surface font-inter text-on-surface antialiased"><main class="procurement-page mx-auto min-h-screen max-w-3xl px-4 py-8 sm:px-6"><div class="mb-8 flex items-center justify-between"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-white shadow-sm"><span class="material-symbols-outlined text-[20px]">school</span></div><div><p class="font-semibold">ProcureMS</p><p class="text-xs text-on-surface-variant">Purchase request workspace</p></div></div><a href="{{ route('procurement') }}" class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5"><span class="material-symbols-outlined text-[17px]">arrow_back</span>Back to requests</a></div>
    <section class="procurement-card rounded border border-outline-variant/30 bg-white p-6 sm:p-8"><div class="mb-7"><p class="text-xs font-semibold uppercase tracking-wider text-primary">Procurement Management</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $editingRequest ? 'Edit Purchase Request' : 'New Purchase Request' }}</h1><p class="mt-2 text-sm text-on-surface-variant">{{ $editingRequest ? 'Update the request information, items, and printed form spacing.' : 'Create an itemized purchase request for review and approval.' }}</p></div>
        <div class="mb-8 rounded border border-primary/20 bg-primary/5 p-4"><div class="flex items-start gap-3"><span class="material-symbols-outlined text-primary">account_tree</span><div><h2 class="text-sm font-semibold">Complete Procurement Document Workflow</h2><p class="mt-1 text-xs leading-5 text-on-surface-variant">After saving the Purchase Request, open Procurement Documents to prepare RFQ, Notice to Award, Purchase Order, Notice to Proceed, IAR, RIS, IARS, ICS, and PAR.</p><div class="mt-3 flex flex-wrap gap-1.5">@foreach(['PR','RFQ','NOA','PO','NTP','IAR','RIS','IARS','ICS','PAR'] as $step)<span class="rounded bg-white px-2 py-1 text-[10px] font-semibold text-primary ring-1 ring-primary/20">{{ $step }}</span>@endforeach</div></div></div></div>
        <form id="procurement-form" method="POST" action="{{ $editingRequest ? route('procurement.update', $editingRequest) : route('procurement.store') }}" class="space-y-6 pb-24">@csrf @if($editingRequest) @method('PUT') @endif
            <div><label for="school_id" class="text-xs font-semibold text-on-surface-variant">School / Institution</label><select id="school_id" name="school_id" required class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm outline-none focus:border-primary"><option value="">Select a school</option>@foreach($schools as $school)<option value="{{ $school->id }}" data-school-name="{{ $school->name }}" data-school-division="{{ $school->division }}" data-school-region="{{ $school->region }}" data-school-district="{{ $school->district }}" @selected(old('school_id', $editingRequest?->school_id) == $school->id)>{{ $school->name }} ({{ $school->code }})</option>@endforeach</select>@error('school_id')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
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
            <div class="rounded border border-outline-variant/30 bg-surface-low p-4"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-sm font-semibold">Goods / Line Items</h2><p class="mt-1 text-xs text-on-surface-variant">Enter each item on one compact line; add its specification directly below.</p></div><button type="button" id="add-item" class="procurement-add-good flex items-center gap-1 rounded border border-primary px-3 py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[17px]">add</span>Add Item</button></div><div class="overflow-x-auto rounded-lg border border-outline-variant/30 bg-white"><table class="w-full min-w-[820px] border-collapse text-left text-xs"><thead class="bg-[#f6f8fc] text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"><tr><th class="w-[7%] px-2 py-3 text-center">Stock<br>No.</th><th class="w-[14%] px-2 py-3">Unit</th><th class="w-[31%] px-2 py-3">Item Description</th><th class="w-[11%] px-2 py-3">Quantity</th><th class="w-[14%] px-2 py-3">Unit Cost</th><th class="w-[15%] px-2 py-3 text-right">Total Cost</th><th class="w-[8%] px-2 py-3 text-center">Action</th></tr></thead><tbody id="items" class="divide-y divide-outline-variant/20"></tbody></table></div><div class="mt-4 flex items-center justify-end rounded-lg border border-outline-variant/30 bg-white px-4 py-3"><span class="mr-3 text-sm font-semibold">Estimated Total</span><span id="grand-total" class="text-xl font-semibold text-primary">₱0.00</span></div></div>
            <div class="rounded border border-outline-variant/30 bg-surface-low p-4"><div class="mb-4"><h2 class="text-sm font-semibold">Request Details</h2><p class="mt-1 text-xs text-on-surface-variant">Describe the procurement and its project title for the transaction.</p></div><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label for="transaction_description" class="text-xs font-semibold text-on-surface-variant">Transaction Details / Description of Transaction<input id="transaction_description" name="transaction_description" value="{{ old('transaction_description', $editingRequest?->transaction_description) }}" maxlength="255" placeholder="e.g. Procurement of Office Supplies" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">@error('transaction_description')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label><label for="purpose" class="text-xs font-semibold text-on-surface-variant">Project Title<input id="purpose" name="purpose" value="{{ old('purpose', $editingRequest?->title) }}" required maxlength="255" placeholder="e.g. 3rd Quarter MOOE 2026" class="mt-2 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">@error('purpose')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label></div></div>
            <label class="block text-xs font-semibold text-on-surface-variant">Extra blank print rows<input name="extra_blank_rows" type="number" min="0" max="20" value="{{ old('extra_blank_rows', $editingRequest?->extra_blank_rows ?? 0) }}" class="mt-2 w-32 rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm font-normal outline-none focus:border-primary"><span class="mt-1 block font-normal text-on-surface-variant">Adds empty rows after “**** NOTHING FOLLOWS ****” on the printed Purchase Request.</span></label>
            <div class="flex justify-end gap-2 border-t border-outline-variant/20 pt-6"><a href="{{ route('procurement') }}" class="rounded border border-outline-variant/50 px-4 py-2.5 text-xs font-semibold hover:border-primary hover:text-primary">Cancel</a><input type="submit" value="SAVE REQUEST" style="display:inline-block!important;visibility:visible!important;opacity:1!important;background:#00236f!important;color:#ffffff!important;border:1px solid #00236f!important;border-radius:4px!important;padding:10px 18px!important;font-size:12px!important;font-weight:700!important;cursor:pointer!important;"></div>
        </form>
    </section></main>
</body>
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
        row.innerHTML = `<td data-stock-number class="px-2 py-2 text-center text-sm font-semibold text-primary"></td><td class="px-2 py-2"><select name="items[${index}][unit]" class="w-full rounded border border-outline-variant/50 bg-white px-2 py-2 text-xs outline-none focus:border-primary"><option ${item.unit === 'piece' ? 'selected' : ''}>piece</option><option ${item.unit === 'box' ? 'selected' : ''}>box</option><option ${item.unit === 'pack' ? 'selected' : ''}>pack</option><option ${item.unit === 'ream' ? 'selected' : ''}>ream</option><option ${item.unit === 'set' ? 'selected' : ''}>set</option><option ${item.unit === 'liter' ? 'selected' : ''}>liter</option></select></td><td class="px-2 py-2"><input name="items[${index}][name]" required maxlength="255" placeholder="Item description" value="${escapeHtml(item.name)}" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td class="px-2 py-2"><input data-field="quantity" name="items[${index}][quantity]" required min="0.01" step="0.01" value="${escapeHtml(item.quantity ?? 1)}" type="number" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td class="px-2 py-2"><input data-field="unit_price" name="items[${index}][unit_price]" required min="0" step="0.01" value="${escapeHtml(item.unit_price ?? 0)}" type="number" class="w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></td><td data-line-total class="px-2 py-2 text-right text-sm font-semibold text-on-surface">₱0.00</td><td class="px-2 py-2 text-center"><button type="button" class="remove-item rounded bg-red-50 px-2.5 py-2 text-[11px] font-semibold text-error hover:bg-red-100">Remove</button></td>`;
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
</script>
</html>
