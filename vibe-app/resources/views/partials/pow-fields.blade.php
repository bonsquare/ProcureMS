{{-- The fields of a Program of Works, shared by the Form Generator and the POW page. $pow is the saved POW when editing; $lines are the material lines
     (arrays: description, unit, quantity, unit_cost, sob_item_id, include); $labor the labor cost; $signatories the four slots. --}}
@php
    $box = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
    $val = fn (string $key, $default = '') => old($key, $default);
    $date = fn ($value) => $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : ($value ?: '');
    $slots = ['prepared_by' => 'Prepared by', 'recommended_by' => 'Recommending Approval', 'approved_by' => 'Approved', 'division_superintendent' => 'Approved (Schools Division Superintendent, optional)'];
@endphp
<div class="grid gap-3 md:grid-cols-2">
    <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Project Title <span class="text-error">*</span><input name="project_title" required maxlength="255" value="{{ $val('project_title', $pow->project_title ?? $program ?? '') }}" class="{{ $box }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">School Address<input name="school_address" maxlength="255" value="{{ $val('school_address', $pow->school_address ?? $school->address ?? '') }}" class="{{ $box }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant">Date<input type="date" name="pow_date" value="{{ $val('pow_date', $date($pow->pow_date ?? null)) }}" class="{{ $box }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant">Date Started<input type="date" name="date_started" value="{{ $val('date_started', $date($pow->date_started ?? null)) }}" class="{{ $box }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant">Date of Completion<input type="date" name="date_completed" value="{{ $val('date_completed', $date($pow->date_completed ?? null)) }}" class="{{ $box }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant">B. Labor Cost<input type="number" step="0.01" min="0" name="labor_cost" id="pow-labor" value="{{ number_format((float) $val('labor_cost', $labor), 2, '.', '') }}" class="{{ $box }} text-right"></label>
    <label class="text-xs font-semibold text-on-surface-variant">Minimum Required Manpower<textarea name="manpower" rows="2" maxlength="2000" class="{{ $box }}">{{ $val('manpower', $pow->manpower ?? '') }}</textarea></label>
    <label class="text-xs font-semibold text-on-surface-variant">Minimum Required Equipment<textarea name="equipment" rows="2" maxlength="2000" class="{{ $box }}">{{ $val('equipment', $pow->equipment ?? '') }}</textarea></label>
</div>

<h3 class="mt-5 text-sm font-semibold">A. Materials</h3>
<div class="mt-2 overflow-x-auto rounded border border-outline-variant/40">
    <table class="w-full min-w-[640px] text-left text-sm" id="pow-lines">
        <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-3 py-2">Use</th><th class="px-3 py-2">Item Description</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2 text-right">Quantity</th><th class="px-3 py-2 text-right">Unit Cost</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20">
        @forelse($lines as $i => $line)
            <tr data-line>
                <td class="px-3 py-2"><input type="checkbox" name="lines[{{ $i }}][include]" value="1" @checked(old("lines.$i.include", $line['include'] ?? true)) aria-label="Use this line"><input type="hidden" name="lines[{{ $i }}][sob_item_id]" value="{{ $line['sob_item_id'] ?? '' }}"></td>
                <td class="px-3 py-2"><input name="lines[{{ $i }}][description]" maxlength="255" value="{{ old("lines.$i.description", $line['description']) }}" class="{{ $box }} mt-0"></td>
                <td class="px-3 py-2"><input name="lines[{{ $i }}][unit]" maxlength="50" value="{{ old("lines.$i.unit", $line['unit'] ?? '') }}" class="{{ $box }} mt-0 w-20"></td>
                <td class="px-3 py-2"><input type="number" step="0.01" min="0.01" name="lines[{{ $i }}][quantity]" data-qty value="{{ old("lines.$i.quantity", $line['quantity']) }}" class="{{ $box }} mt-0 w-24 text-right"></td>
                <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_cost]" data-cost value="{{ old("lines.$i.unit_cost", $line['unit_cost']) }}" class="{{ $box }} mt-0 w-28 text-right"></td>
                <td class="px-3 py-2 text-right tabular-nums" data-amount>{{ number_format((float) $line['quantity'] * (float) $line['unit_cost'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-3 py-4 text-center text-xs text-on-surface-variant">No material lines. The POW can still be saved with a labor cost.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<dl class="mt-3 grid gap-1 text-sm md:ml-auto md:max-w-xs">
    <div class="flex justify-between"><dt>Total Materials Cost</dt><dd class="tabular-nums" id="pow-materials">0.00</dd></div>
    <div class="flex justify-between"><dt>B. Labor Cost</dt><dd class="tabular-nums" id="pow-labor-total">0.00</dd></div>
    <div class="flex justify-between border-t border-outline-variant/40 pt-1 font-bold"><dt>C. Total Project Cost</dt><dd class="tabular-nums" id="pow-total">0.00</dd></div>
</dl>

<h3 class="mt-5 text-sm font-semibold">Signatures</h3>
<div class="mt-2 grid gap-3 md:grid-cols-2">
    @foreach($slots as $slot => $label)
        <fieldset class="rounded border border-outline-variant/40 p-3"><legend class="px-1 text-xs font-semibold text-on-surface-variant">{{ $label }}</legend>
            <input name="signatories[{{ $slot }}][name]" maxlength="120" placeholder="Name" value="{{ old("signatories.$slot.name", $signatories[$slot]['name'] ?? '') }}" class="{{ $box }} mt-0">
            <input name="signatories[{{ $slot }}][position]" maxlength="120" placeholder="Position" value="{{ old("signatories.$slot.position", $signatories[$slot]['position'] ?? '') }}" class="{{ $box }}">
        </fieldset>
    @endforeach
</div>
<script>
    (() => {
        const rows = [...document.querySelectorAll('#pow-lines [data-line]')];
        const labor = document.getElementById('pow-labor');
        const money = (n) => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const update = () => {
            let materials = 0;
            rows.forEach((row) => {
                const amount = Math.round((parseFloat(row.querySelector('[data-qty]').value) || 0) * (parseFloat(row.querySelector('[data-cost]').value) || 0) * 100) / 100;
                row.querySelector('[data-amount]').textContent = money(amount);
                if (row.querySelector('input[type=checkbox]').checked) materials += amount;
            });
            const laborCost = parseFloat(labor.value) || 0;
            document.getElementById('pow-materials').textContent = money(materials);
            document.getElementById('pow-labor-total').textContent = money(laborCost);
            document.getElementById('pow-total').textContent = money(materials + laborCost);
        };
        document.getElementById('pow-lines').addEventListener('input', update);
        document.getElementById('pow-lines').addEventListener('change', update);
        labor.addEventListener('input', update);
        update();
    })();
</script>
