{{-- The budget lines (UACS codes) an ORS is charged to. Expects $o (the ORS) and $budgetItems. Editable until the ORS has a DV or a payment; then the lines are shown locked. --}}
@php
    $chargeEditable = ! ($o->dv_number || $o->paid_at);
    $chargeCurrent = $o->chargedLines()->filter(fn ($charge) => $charge['allocation']);
    $chargeFund = $o->source_of_fund ?: data_get($chargeCurrent->first(), 'allocation.source_of_fund');
    $chargeOwn = $chargeCurrent->mapWithKeys(fn ($charge) => [$charge['allocation']->id => (float) $charge['amount']]);
    $chargeOptions = $budgetItems
        ->filter(fn ($item) => (int) $item->school_id === (int) $o->school_id && (! $chargeFund || strcasecmp((string) $item->source_of_fund, (string) $chargeFund) === 0))
        ->map(fn ($item) => ['id' => $item->id, 'code' => $item->uacs_code, 'title' => $item->particulars, 'available' => round((float) ($item->available_balance ?? $item->amount) + (float) ($chargeOwn[$item->id] ?? 0), 2)])
        ->values();
    foreach ($chargeCurrent as $charge) {
        if (! $chargeOptions->contains('id', $charge['allocation']->id)) {
            $chargeOptions->push(['id' => $charge['allocation']->id, 'code' => $charge['allocation']->uacs_code, 'title' => $charge['allocation']->particulars, 'available' => null]);
        }
    }
    $chargeRows = $chargeCurrent->map(fn ($charge) => ['id' => $charge['allocation']->id, 'amount' => round((float) $charge['amount'], 2)])->values();
    $chargeTarget = $o->procurement_request_id ? round((float) $o->amount, 2) : null;
@endphp
<div class="charge-editor md:col-span-2" @if($chargeEditable) data-charge-editor data-options="{{ json_encode($chargeOptions) }}" data-rows="{{ json_encode($chargeRows) }}" data-target="{{ $chargeTarget }}" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary" aria-hidden="true"><span class="material-symbols-outlined text-[18px]">account_tree</span></span>
            <div class="min-w-0"><p class="text-xs font-bold text-on-surface-variant" id="charge-title-{{ $o->id }}">Charge to budget lines</p><p class="text-[11px] font-normal leading-4 text-on-surface-variant">An ORS can be charged to several budget lines of the same fund.</p></div>
        </div>
        @if($chargeFund)<span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold text-primary"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">payments</span>Fund: {{ $chargeFund }}</span>@endif
    </div>

    @if($chargeEditable)
        <div class="mt-3 space-y-2" data-rows role="list" aria-labelledby="charge-title-{{ $o->id }}"></div>
        <button type="button" data-add class="mt-2 inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-dashed border-primary/50 px-3 text-xs font-bold text-primary hover:bg-primary/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>Add a budget line</button>
        <div class="mt-3 rounded-xl border border-outline-variant/40 bg-white p-3" role="status" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs"><span class="font-bold" data-summary-total></span><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold" data-summary-state></span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-container" aria-hidden="true"><div class="h-full rounded-full transition-[width] duration-300" data-summary-bar style="width:0%"></div></div>
            <p class="mt-2 text-[11px] font-normal text-on-surface-variant" data-summary-note></p>
        </div>
    @else
        <ul class="mt-3 space-y-2">
            @foreach($chargeCurrent as $charge)
                <li class="flex items-center gap-3 rounded-xl border border-outline-variant/40 bg-surface-low px-3 py-2.5">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary/10 text-[11px] font-bold text-primary">{{ $loop->iteration }}</span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-on-surface">{{ $charge['allocation']->uacs_code }} · {{ $charge['allocation']->particulars }}</span><span class="block text-[11px] font-normal text-on-surface-variant">{{ $charge['allocation']->source_of_fund }}</span></span>
                    <span class="text-xs font-bold tabular-nums text-on-surface">₱{{ number_format($charge['amount'], 2) }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-semibold leading-4 text-amber-900"><span class="material-symbols-outlined shrink-0 text-[16px]" aria-hidden="true">lock</span><span class="min-w-0 flex-1 whitespace-normal break-words">These lines are locked because this ORS already has a DV or a payment. Cancel the check first to change them.</span></p>
    @endif
</div>

@once
<style>
    .charge-row { display: grid; grid-template-columns: auto minmax(0, 1fr) 9.5rem auto; align-items: start; gap: .6rem; border: 1px solid #dbe4ec; border-left: 4px solid #14818f; border-radius: .8rem; background: #fff; padding: .6rem .7rem; }
    .charge-row .charge-no { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; margin-top: .35rem; border-radius: 999px; background: #e4edf6; color: #14818f; font-size: .7rem; font-weight: 700; }
    .charge-row select, .charge-row input { width: 100%; margin-top: 0 !important; }
    .charge-row .charge-amount { position: relative; }
    .charge-row .charge-amount span.peso { position: absolute; left: .65rem; top: .55rem; font-size: .8125rem; color: #536273; pointer-events: none; }
    .charge-row .charge-amount input { padding-left: 1.5rem; text-align: right; font-variant-numeric: tabular-nums; }
    .charge-row .charge-hint { grid-column: 2 / 4; margin-top: -.15rem; font-size: .6875rem; color: #536273; }
    .charge-row .charge-remove { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: .6rem; color: #536273; }
    .charge-row .charge-remove:hover { background: rgba(224,65,79,.1); color: #e0414f; }
    @media (max-width: 640px) { .charge-row { grid-template-columns: auto minmax(0, 1fr) auto; } .charge-row .charge-amount { grid-column: 2 / 3; } .charge-row .charge-hint { grid-column: 2 / 4; } }
    @media (prefers-reduced-motion: reduce) { [data-summary-bar] { transition: none !important; } }
</style>
<script>
    (() => {
        const money = (value) => '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const setup = (root) => {
            const options = JSON.parse(root.dataset.options || '[]');
            const initial = JSON.parse(root.dataset.rows || '[]');
            const target = root.dataset.target === '' ? null : Number(root.dataset.target);
            const form = root.closest('form');
            const amountInput = form.elements.amount;
            const list = root.querySelector('[data-rows]');
            const state = root.querySelector('[data-summary-state]');
            const bar = root.querySelector('[data-summary-bar]');
            const note = root.querySelector('[data-summary-note]');
            const totalLine = root.querySelector('[data-summary-total]');
            const field = 'w-full rounded-lg border border-outline-variant/60 bg-white px-2.5 py-2 text-[13px] font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/25';

            const update = () => {
                const rows = [...list.children];
                const picked = rows.map((row) => row.querySelector('select').value).filter(Boolean);
                rows.forEach((row, index) => {
                    const select = row.querySelector('select');
                    const amount = row.querySelector('input');
                    select.name = 'charges[' + index + '][budget_allocation_id]';
                    amount.name = 'charges[' + index + '][amount]';
                    row.querySelector('.charge-no').textContent = index + 1;
                    [...select.options].forEach((option) => { option.disabled = !!option.value && option.value !== select.value && picked.includes(option.value); });
                    const chosen = options.find((option) => String(option.id) === select.value);
                    row.querySelector('.charge-hint').textContent = chosen ? (chosen.available === null ? 'This line is not open for new charges; it keeps its current amount or can be removed.' : 'Available on this line before this ORS: ' + money(chosen.available)) : 'Choose the budget line to charge.';
                    row.querySelector('.charge-remove').hidden = rows.length === 1;
                });
                const total = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('input').value) || 0), 0);
                const incomplete = rows.some((row) => !row.querySelector('select').value && (parseFloat(row.querySelector('input').value) || 0) > 0);
                let tone = 'ok', label = 'Balanced', width = 100, text = '';
                if (target === null) {
                    // A direct ORS: its amount is whatever its lines add up to.
                    if (amountInput) { amountInput.value = total ? total.toFixed(2) : ''; amountInput.readOnly = true; amountInput.classList.add('bg-surface-low'); }
                    totalLine.textContent = 'Total ' + money(total);
                    text = 'The ORS amount follows the total of its lines. Each line is checked against its own budget balance when you save.';
                    if (!total) { tone = 'warn'; label = 'Enter an amount'; width = 0; }
                } else {
                    const diff = Math.round((target - total) * 100) / 100;
                    totalLine.textContent = 'Assigned ' + money(total) + ' of ' + money(target);
                    width = Math.min(100, target ? (total / target) * 100 : 0);
                    if (Math.abs(diff) < 0.01) { text = 'The lines add up to the amount of the Purchase Order.'; }
                    else if (diff > 0) { tone = 'warn'; label = money(diff) + ' left to assign'; text = 'The lines must add up to the amount of the Purchase Order.'; }
                    else { tone = 'bad'; label = money(-diff) + ' over'; text = 'The lines add up to more than the Purchase Order.'; }
                }
                if (incomplete) { tone = 'bad'; label = 'Choose a budget line for each amount'; }
                const palette = { ok: ['#e3f6ec', '#0f7a4d', '#2aa86b'], warn: ['#fdf1d8', '#8a5a00', '#e0a21a'], bad: ['#fde7e9', '#b3202e', '#e0414f'] }[tone];
                state.textContent = label; state.style.background = palette[0]; state.style.color = palette[1];
                bar.style.width = width + '%'; bar.style.background = palette[2];
                note.textContent = text;
                form.dispatchEvent(new Event('input', { bubbles: true }));
            };

            const addRow = (id, amount) => {
                const row = document.createElement('div');
                row.className = 'charge-row'; row.setAttribute('role', 'listitem');
                const number = Object.assign(document.createElement('span'), { className: 'charge-no' });
                const select = document.createElement('select'); select.className = field; select.setAttribute('aria-label', 'Budget line');
                select.append(new Option('Select the budget line', ''));
                options.forEach((option) => select.append(new Option(option.code + ' · ' + option.title, option.id)));
                select.value = id ? String(id) : '';
                const box = Object.assign(document.createElement('div'), { className: 'charge-amount' });
                const peso = Object.assign(document.createElement('span'), { className: 'peso', textContent: '₱' }); peso.setAttribute('aria-hidden', 'true');
                const input = document.createElement('input'); input.type = 'number'; input.step = '0.01'; input.min = '0.01'; input.placeholder = '0.00'; input.className = field; input.setAttribute('aria-label', 'Amount charged to this line');
                if (amount) input.value = Number(amount).toFixed(2);
                box.append(peso, input);
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'charge-remove'; remove.setAttribute('aria-label', 'Remove this budget line'); remove.title = 'Remove this budget line';
                remove.innerHTML = '<span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>';
                const hint = Object.assign(document.createElement('p'), { className: 'charge-hint' });
                remove.addEventListener('click', () => { row.remove(); update(); });
                select.addEventListener('change', update); input.addEventListener('input', update);
                row.append(number, select, box, remove, hint); list.append(row);
            };

            (initial.length ? initial : [{ id: '', amount: '' }]).forEach((row) => addRow(row.id, row.amount));
            root.querySelector('[data-add]').addEventListener('click', () => {
                if (list.children.length >= 10) return;
                addRow('', '');
                update();
                list.lastElementChild.querySelector('select').focus();
            });
            update();
        };
        const run = () => document.querySelectorAll('[data-charge-editor]').forEach((root) => { if (!root.dataset.ready) { root.dataset.ready = '1'; setup(root); } });
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    })();
</script>
@endonce
