{{-- The fields of a Program of Works, shared by the Form Generator and the POW page. $pow is the saved POW when editing; $lines are the material lines
     (arrays: description, unit, quantity, unit_cost, sob_item_id, include); $labor the labor cost; $signatories the four slots. --}}
@php
    $box = 'mt-1.5 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal text-on-surface shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';
    $cell = 'w-full rounded-lg border border-outline-variant bg-white px-2.5 py-1.5 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant';
    $val = fn (string $key, $default = '') => old($key, $default);
    $date = fn ($value) => $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : ($value ?: '');
    $slots = ['prepared_by' => 'Prepared by', 'recommended_by' => 'Recommending Approval', 'approved_by' => 'Approved', 'division_superintendent' => 'Approved (Schools Division Superintendent, optional)'];
    $sectionHead = fn (int $number, string $title, string $hint) => '<div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-white">'.$number.'</span><div><h3 class="text-sm font-bold text-primary">'.e($title).'</h3><p class="text-xs text-on-surface-variant">'.e($hint).'</p></div></div>';
@endphp

<section class="mb-5 overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    {!! $sectionHead(1, 'Project details', 'Title, address, schedule and the manpower and equipment the work needs.') !!}
    <div class="grid gap-4 p-5 md:grid-cols-3">
        <label class="{{ $label }} md:col-span-3">Project Title <span class="text-error">*</span><input name="project_title" required maxlength="255" value="{{ $val('project_title', $pow->project_title ?? $program ?? '') }}" class="{{ $box }} font-semibold"></label>
        <label class="{{ $label }} md:col-span-3">School Address<input name="school_address" maxlength="255" value="{{ $val('school_address', $pow->school_address ?? $school->address ?? '') }}" class="{{ $box }}"></label>
        <label class="{{ $label }}">Date<input type="date" name="pow_date" value="{{ $val('pow_date', $date($pow->pow_date ?? null)) }}" class="{{ $box }}"></label>
        <label class="{{ $label }}">Date Started<input type="date" name="date_started" value="{{ $val('date_started', $date($pow->date_started ?? null)) }}" class="{{ $box }}"></label>
        <label class="{{ $label }}">Date of Completion<input type="date" name="date_completed" value="{{ $val('date_completed', $date($pow->date_completed ?? null)) }}" class="{{ $box }}"></label>
        <div class="grid gap-4 md:col-span-3 md:grid-cols-2">
            <label class="{{ $label }}">Minimum Required Manpower<textarea name="manpower" rows="2" maxlength="2000" class="{{ $box }}">{{ $val('manpower', $pow->manpower ?? '') }}</textarea></label>
            <label class="{{ $label }}">Minimum Required Equipment<textarea name="equipment" rows="2" maxlength="2000" class="{{ $box }}">{{ $val('equipment', $pow->equipment ?? '') }}</textarea></label>
        </div>
    </div>
</section>

<section class="mb-5 overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    {!! $sectionHead(2, 'A. Materials', 'Tick the lines that belong in the Program of Works. The total follows as you type.') !!}
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm" id="pow-lines">
            <thead class="bg-surface-low text-[11px] uppercase tracking-wider text-on-surface-variant"><tr><th class="w-14 px-4 py-2.5 text-center">Use</th><th class="px-3 py-2.5">Item Description</th><th class="px-3 py-2.5">Unit</th><th class="px-3 py-2.5 text-right">Quantity</th><th class="px-3 py-2.5 text-right">Unit Cost</th><th class="px-5 py-2.5 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/30">
            @forelse($lines as $i => $line)
                <tr data-line class="even:bg-surface-low/40 hover:bg-[#eef4fa]">
                    <td class="px-4 py-2 text-center"><input type="checkbox" name="lines[{{ $i }}][include]" value="1" @checked(old("lines.$i.include", $line['include'] ?? true)) aria-label="Use this line" class="h-4 w-4 rounded border-outline-variant accent-primary"><input type="hidden" name="lines[{{ $i }}][sob_item_id]" value="{{ $line['sob_item_id'] ?? '' }}"></td>
                    <td class="px-3 py-2"><input name="lines[{{ $i }}][description]" maxlength="255" value="{{ old("lines.$i.description", $line['description']) }}" class="{{ $cell }}"></td>
                    <td class="px-3 py-2"><input name="lines[{{ $i }}][unit]" maxlength="50" value="{{ old("lines.$i.unit", $line['unit'] ?? '') }}" class="{{ $cell }} w-24"></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0.01" name="lines[{{ $i }}][quantity]" data-qty value="{{ old("lines.$i.quantity", $line['quantity']) }}" class="{{ $cell }} ml-auto w-28 text-right tabular-nums"></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_cost]" data-cost value="{{ old("lines.$i.unit_cost", $line['unit_cost']) }}" class="{{ $cell }} ml-auto w-32 text-right tabular-nums"></td>
                    <td class="px-5 py-2 text-right font-semibold tabular-nums text-on-surface" data-amount>{{ number_format((float) $line['quantity'] * (float) $line['unit_cost'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-xs text-on-surface-variant">No material lines. The POW can still be saved with a labor cost.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mb-5 overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    {!! $sectionHead(3, 'Cost summary', 'Labor is typed by hand; the total project cost is also the budget allocation.') !!}
    <div class="grid gap-5 p-5 md:grid-cols-2">
        <label class="{{ $label }}">B. Labor Cost<input type="number" step="0.01" min="0" name="labor_cost" id="pow-labor" value="{{ number_format((float) $val('labor_cost', $labor), 2, '.', '') }}" class="{{ $box }} text-right text-base font-semibold tabular-nums"></label>
        <dl class="grid gap-1.5 rounded-xl border border-outline-variant/50 bg-[#eef4fa] p-4 text-sm">
            <div class="flex justify-between"><dt class="text-on-surface-variant">Total Materials Cost</dt><dd class="font-semibold tabular-nums" id="pow-materials">0.00</dd></div>
            <div class="flex justify-between"><dt class="text-on-surface-variant">B. Labor Cost</dt><dd class="font-semibold tabular-nums" id="pow-labor-total">0.00</dd></div>
            <div class="mt-1 flex items-center justify-between border-t-2 border-primary/30 pt-2"><dt class="font-bold text-primary">C. Total Project Cost</dt><dd class="text-xl font-bold tabular-nums text-primary" id="pow-total">0.00</dd></div>
        </dl>
    </div>
</section>

<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    {!! $sectionHead(4, 'Signatures', 'Who signs the printed Program of Works.') !!}
    <div class="grid gap-4 p-5 md:grid-cols-2">
        @foreach($slots as $slot => $slotLabel)
            <fieldset class="rounded-xl border border-outline-variant/50 border-l-4 border-l-primary bg-white p-4"><legend class="px-2 text-[11px] font-bold uppercase tracking-wider text-primary">{{ $slotLabel }}</legend>
                <input name="signatories[{{ $slot }}][name]" maxlength="120" placeholder="Name" value="{{ old("signatories.$slot.name", $signatories[$slot]['name'] ?? '') }}" class="{{ $box }} mt-0 font-semibold">
                <input name="signatories[{{ $slot }}][position]" maxlength="120" placeholder="Position" value="{{ old("signatories.$slot.position", $signatories[$slot]['position'] ?? '') }}" class="{{ $box }}">
            </fieldset>
        @endforeach
    </div>
</section>
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
