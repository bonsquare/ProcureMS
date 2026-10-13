{{-- One shared edit window per kind of record on the Planning page. A button with data-edit-dialog fills the window from its data-payload and points the form at its data-action. --}}
@php
    $dialog = 'w-[min(94vw,46rem)] max-h-[92vh] overflow-y-auto rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40';
    $field = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
    $actions = '<div class="mt-5 flex justify-end gap-2 sm:col-span-2"><button type="button" data-close class="rounded border border-outline-variant px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save changes</button></div>';
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

{{-- New SIP program --}}
<dialog id="dlg-sip-new" class="{{ $dialog }}">
    <form method="POST" class="grid gap-3 p-5 sm:grid-cols-2">@csrf
        <h3 class="text-lg font-bold sm:col-span-2">Add SIP program</h3>
        <input type="hidden" name="school_id">
        <label class="text-xs font-semibold">Plan Start Year (Year 1)<input required type="number" name="school_year" min="2000" max="2100" title="The SIP covers this year and the next two, e.g. 2026 means FY 2026-2028." class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Pillar / Enabling Mechanism<select required name="pillar" class="{{ $field }}"><option value="">Select pillar</option>@foreach(\App\Models\Aip::PILLARS as $pillar)<option>{{ $pillar }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold sm:col-span-2">KRA<input required name="kra" maxlength="255" placeholder="e.g. KRA 3: Learner Formation and Development" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-2">DepEd Organizational Outcomes<textarea name="organizational_outcome" rows="2" maxlength="1000" placeholder="e.g. Percentage of School-age Children in School - Net Enrollment Rate (NER) in Elementary and 6-Year Target" class="{{ $field }}"></textarea></label>
        <label class="text-xs font-semibold">Strategy (Processes)<input name="strategy" maxlength="255" placeholder="e.g. Learner Support Management" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">5-Point Agenda<input name="five_point_agenda" maxlength="255" placeholder="e.g. Enhanced Governance structure..." class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-2">Specific Program / Project<input required name="project" maxlength="255" placeholder="e.g. Papel mo Kinabukasan Ko!" class="{{ $field }}"></label>
        <p class="text-xs text-on-surface-variant sm:col-span-2">Add its activities after saving.</p>
        <div class="mt-2 flex justify-end gap-2 sm:col-span-2"><button type="button" data-close class="rounded border border-outline-variant px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save SIP Program</button></div>
    </form>
</dialog>

{{-- New SIP activity (the program it belongs to is shown on top) --}}
<dialog id="dlg-activity-new" class="{{ $dialog }}">
    <form method="POST" class="grid gap-3 p-5 sm:grid-cols-3">@csrf
        <h3 class="text-lg font-bold sm:col-span-3">Add activity</h3>
        <input name="program" disabled aria-label="Program" class="{{ $field }} !mt-0 border-0 bg-transparent !px-0 font-semibold text-primary sm:col-span-3">
        <label class="text-xs font-semibold sm:col-span-3">Activity<input required name="activity" maxlength="1000" class="{{ $field }}"></label>
        @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Physical Y{{ $y }}<input type="number" min="0" step="0.01" name="physical_year{{ $y }}" class="{{ $field }}"></label>@endforeach
        @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Financial Y{{ $y }} (₱)<input type="number" min="0" step="0.01" name="financial_year{{ $y }}" class="{{ $field }}"></label>@endforeach
        <label class="text-xs font-semibold sm:col-span-3">Source of Fund<input name="source_of_fund" list="sip-funds" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-3">Responsible Person<input name="responsible_person" maxlength="1000" placeholder="e.g. School Head, Teachers, PTA Officials" class="{{ $field }}"></label>
        <label class="text-xs font-semibold sm:col-span-3">Remarks (Important Notes)<input name="remarks" maxlength="1000" class="{{ $field }}"></label>
        <div class="mt-2 flex justify-end gap-2 sm:col-span-3"><button type="button" data-close class="rounded border border-outline-variant px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save Activity</button></div>
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
        <label class="text-xs font-semibold sm:col-span-2">Category<select name="category" class="{{ $field }}">@foreach(\App\Support\AppCategories::ALL as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold">Procurement mode<input name="procurement_mode" maxlength="100" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Schedule<input name="procurement_schedule" maxlength="255" class="{{ $field }}"></label>
        <label class="text-xs font-semibold">Fund source<select name="fund_source" class="{{ $field }}"><option value="">Select fund source</option>@foreach($fundOptions as $fund)<option value="{{ $fund }}">{{ $fund }}</option>@endforeach</select></label>
        {!! $actions !!}
    </form>
</dialog>

{{-- SOB: its quarter and fund --}}
<dialog id="dlg-sob" class="w-[min(94vw,28rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40">
    <form method="POST" class="grid gap-3 p-5">@csrf @method('PUT')
        <h3 class="text-lg font-bold">Edit SOB</h3>
        <label class="text-xs font-semibold">Quarter<select required name="quarter" class="{{ $field }}">@foreach(\App\Models\SobPlan::QUARTERS as $quarter => $quarterLabel)<option value="{{ $quarter }}">{{ $quarterLabel }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold">Source of fund<select required name="fund_source" class="{{ $field }}">@foreach($fundOptions as $fund)<option value="{{ $fund }}">{{ $fund }}</option>@endforeach</select></label>
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
                // A choice the list does not offer (a fund typed on the SOB page) is added so it stays selected.
                if (element.tagName === 'SELECT' && data[element.name] && ! Array.from(element.options).some((option) => option.value === String(data[element.name]))) {
                    element.add(new Option(data[element.name], data[element.name]));
                }
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

{{-- The SIP windows show the official SIP beside the form, drawn from the entry as it is now (nothing is saved). Each form posts to its own action + "/preview". --}}
<style>
    dialog[data-split]{width:min(96vw,1320px)!important;max-height:94vh}
    dialog[data-split][open]{display:flex;flex-direction:row;overflow:hidden}
    dialog[data-split] .split-form{flex:1 1 44%;min-width:0;overflow-y:auto;max-height:94vh}
    dialog[data-split] .split-preview{display:flex;flex:1 1 56%;min-width:0;flex-direction:column;background:#d9dee5;border-left:1px solid #cbd7e1}
    dialog[data-split] .split-bar{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 14px;background:#fff;border-bottom:1px solid #cbd7e1;font-size:12px;font-weight:700;color:#103967}
    dialog[data-split] .split-bar small{font-weight:400;color:#536273}
    dialog[data-split] iframe{width:100%;flex:1;border:0;background:#d9dee5}
    @media(max-width:1023px){dialog[data-split][open]{flex-direction:column;overflow-y:auto}dialog[data-split] .split-form{max-height:none;overflow:visible}dialog[data-split] .split-preview{min-height:60vh}}
</style>
<script>
    (() => {
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}.official-sheet{zoom:var(--pz,1)!important;margin-top:12px!important;margin-bottom:12px!important}</style>';
        ['dlg-sip', 'dlg-sip-new', 'dlg-activity-new', 'dlg-activity'].forEach((id) => {
            const dialog = document.getElementById(id);
            const form = dialog && dialog.querySelector('form');
            if (! form) return;
            dialog.setAttribute('data-split', '');
            const pane = document.createElement('div');
            pane.className = 'split-form';
            dialog.insertBefore(pane, form);
            pane.appendChild(form);
            const preview = document.createElement('aside');
            preview.className = 'split-preview';
            preview.setAttribute('aria-label', 'Official SIP preview');
            preview.innerHTML = '<div class="split-bar"><span>Official SIP (updates as you edit)</span><span style="display:flex;gap:8px;align-items:center"><small data-status>Updates as you type</small><select data-zoom aria-label="Preview zoom" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option></select></span></div><iframe title="Official SIP preview"></iframe>';
            dialog.appendChild(preview);
            const frame = preview.querySelector('iframe');
            const status = preview.querySelector('[data-status]');
            const zoomSelect = preview.querySelector('[data-zoom]');
            let timer = null; let controller = null;
            const applyZoom = () => {
                const doc = frame.contentDocument;
                const sheet = doc && doc.querySelector('.official-sheet, [data-official-page]');
                if (! sheet) return;
                let zoom = parseFloat(zoomSelect.value);
                if (zoomSelect.value === 'fit') {
                    doc.documentElement.style.setProperty('--pz', 1);
                    const box = sheet.getBoundingClientRect();
                    zoom = Math.min(0.9, Math.max(0.3, (frame.clientWidth - 48) / (Math.max(sheet.scrollWidth, box.width) || 1)));
                }
                doc.documentElement.style.setProperty('--pz', zoom);
            };
            frame.addEventListener('load', () => setTimeout(applyZoom, 350));
            zoomSelect.addEventListener('change', applyZoom);
            const refresh = async () => {
                if (! dialog.open || ! form.action) return;
                if (controller) controller.abort();
                controller = new AbortController();
                status.textContent = 'Updating preview...';
                try {
                    const body = new FormData(form);
                    body.delete('_method');
                    const response = await fetch(form.action.replace(/\/$/, '') + '/preview', { method: 'POST', body, signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                    const html = await response.text();
                    if (! response.ok) { status.textContent = 'Preview unavailable (HTTP ' + response.status + ')'; return; }
                    frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html;
                    status.textContent = 'Updates as you type';
                } catch (error) {
                    if (error.name !== 'AbortError') status.textContent = 'Preview unavailable';
                }
            };
            const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 900); };
            form.addEventListener('input', schedule);
            form.addEventListener('change', schedule);
            new MutationObserver(() => { if (dialog.open) { clearTimeout(timer); timer = setTimeout(refresh, 300); } }).observe(dialog, { attributes: true, attributeFilter: ['open'] });
        });
    })();
</script>
