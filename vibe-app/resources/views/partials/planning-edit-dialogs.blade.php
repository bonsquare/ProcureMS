{{-- One shared edit window per kind of record on the Planning page. A button with data-edit-dialog fills the window from its data-payload and points the form at its data-action. --}}
@php
    $dialog = 'w-[min(94vw,46rem)] max-h-[92vh] overflow-y-auto rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40';
    $field = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
    $actions = '<div class="mt-5 flex justify-end gap-2 sm:col-span-2"><button type="button" data-close class="rounded border border-outline-variant px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button class="rounded bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save changes</button></div>';
@endphp

{{-- SIP program --}}
<dialog id="dlg-sip" class="{{ $dialog }}">
    <form method="POST" class="grid gap-3 p-5 sm:grid-cols-2">@csrf @method('PUT')
        <h3 class="text-lg font-bold sm:col-span-2">Edit SIP program</h3>
        <label class="text-xs font-semibold">Plan Start Year (Year 1)<input required type="number" name="school_year" min="2000" max="2100" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Planning period<input name="planning_period" maxlength="100" placeholder="e.g. 2026-2028" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Pillar / Enabling Mechanism<select required name="pillar" class="{{ $field }}">@foreach(\App\Models\Aip::PILLARS as $pillar)<option>{{ $pillar }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold">KRA<input required name="kra" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-2">DepEd Organizational Outcome<textarea name="organizational_outcome" rows="2" maxlength="1000" class="{{ $field }}"></textarea></label>
        <label class="text-xs font-semibold">Strategy (Processes)<input name="strategy" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">5-Point Agenda<input name="five_point_agenda" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-2">Specific Program / Project<input required name="project" maxlength="255" class="{{ $field }}"></label>
        {!! $actions !!}
    </form>
</dialog>

{{-- SIP activity --}}
<dialog id="dlg-activity" class="{{ $dialog }}">
    <form method="POST" class="grid gap-3 p-5 sm:grid-cols-3">@csrf @method('PUT')
        <h3 class="text-lg font-bold sm:col-span-3">Edit SIP activity</h3>
        <label class="text-xs font-semibold sm:col-span-3">Activity<input required name="activity" maxlength="1000" class="{{ $field }}"></label>
        @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Physical Y{{ $y }}<input type="number" min="0" step="0.01" name="physical_year{{ $y }}" class="{{ $field }}"></label>@endforeach
        @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Financial Y{{ $y }} (₱)<input type="number" min="0" step="0.01" name="financial_year{{ $y }}" class="{{ $field }}"></label>@endforeach
        <label class="text-xs font-semibold sm:col-span-3">Source of Fund<input name="source_of_fund" list="sip-funds" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-3">Responsible Person<input name="responsible_person" maxlength="1000" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-3">Remarks (Important Notes)<input name="remarks" maxlength="1000" class="{{ $field }}"></label>
        <div class="sm:col-span-3">{!! $actions !!}</div>
    </form>
</dialog>

{{-- APP item --}}
<dialog id="dlg-app-item" class="{{ $dialog }}">
    <form method="POST" class="grid gap-3 p-5 sm:grid-cols-2">@csrf @method('PUT')
        <h3 class="text-lg font-bold sm:col-span-2">Edit APP item</h3>
        <label class="text-xs font-semibold sm:col-span-2">Procurement item<input required name="procurement_item" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-2">Specifications<input name="specifications" maxlength="2000" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Quantity<input required type="number" name="quantity" min="0.01" step="0.01" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Unit<input required name="unit" maxlength="50" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Estimated unit cost<input required type="number" name="estimated_unit_cost" min="0" step="0.01" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Procurement mode<input name="procurement_mode" maxlength="100" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Schedule<input name="procurement_schedule" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Fund source<select name="fund_source" class="{{ $field }}"><option value="">Select fund source</option>@foreach($fundOptions as $fund)<option value="{{ $fund }}">{{ $fund }}</option>@endforeach</select></label>
        {!! $actions !!}
    </form>
</dialog>

<script>
    // Edit buttons: fill the shared window from the button, then open it. Delete forms ask first.
    (() => {
        document.querySelectorAll('[data-edit-dialog]').forEach((button) => button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.editDialog);
            const form = dialog.querySelector('form');
            const data = JSON.parse(button.dataset.payload || '{}');
            form.action = button.dataset.action;
            Array.from(form.elements).forEach((element) => {
                if (! element.name || ['_token', '_method'].includes(element.name)) return;
                element.value = data[element.name] ?? '';
            });
            dialog.showModal();
        }));
        document.addEventListener('click', (event) => {
            const closer = event.target.closest('dialog [data-close]');
            if (closer) { closer.closest('dialog').close(); return; }
            if (event.target instanceof HTMLDialogElement) { event.target.close(); }
        });
        document.addEventListener('submit', (event) => {
            const message = event.target.dataset ? event.target.dataset.confirm : null;
            if (message && ! window.confirm(message)) { event.preventDefault(); }
        });
    })();
</script>
