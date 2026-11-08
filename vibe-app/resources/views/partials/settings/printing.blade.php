@php
    $canEdit = auth()->user()?->hasPermission('organization.settings');
    $sides = ['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'];
    $mm = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $default = $printSettings['default'] ?? null;
    $paper = old('paper', $printPaper ?? null);
    // These pages have no margin of their own to replace, so only their own row changes them.
    $ownRowOnly = ['dv', 'ors', 'pr', 'reconciliation', 'rfq', 'po', 'ntp', 'noa', 'abstract', 'iar', 'ris', 'iars', 'ics', 'generic'];
    $box = 'mt-1 h-11 w-full min-w-0 rounded-lg border border-outline-variant bg-white px-3 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20 disabled:bg-surface-low disabled:text-on-surface-variant';
@endphp
<style>
    .print-sheet-demo { aspect-ratio: 210 / 297; width: 100%; max-width: 150px; background: #fff; border: 1px solid #c3ccd6; box-shadow: 0 1px 4px rgba(0,0,0,.08); position: relative; }
    .print-sheet-demo > span { position: absolute; inset: var(--demo-t, 5%) var(--demo-r, 5%) var(--demo-b, 5%) var(--demo-l, 5%); background: #e2edf7; border: 1px dashed #286da8; transition: inset .2s ease; }
    @media (prefers-reduced-motion: reduce) { .print-sheet-demo > span { transition: none; } }
</style>
<div class="mb-5">
    <h2 class="text-lg font-semibold text-on-surface">Printing</h2>
    <p class="mt-1 break-words text-xs text-on-surface-variant">The empty space around the page (in millimetres) of every printed document of this school. Set one default for all, and give a document its own margins only if it needs them. Leave a box empty to use the default.</p>
</div>

<form method="POST" action="{{ route('school-settings.printing.update') }}" @if($canEdit) data-guard="Save the print settings" @endif class="grid grid-cols-[minmax(0,1fr)] items-start gap-5">
    @csrf @method('PUT')
    <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">

    <section class="rounded-xl border border-outline-variant/60 bg-white p-5 shadow-sm" aria-labelledby="print-default">
        <h3 id="print-default" class="text-sm font-bold text-primary">Default margins</h3>
        <div class="mt-3 grid items-start gap-5 sm:grid-cols-[1fr_150px]">
            <div class="min-w-0">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($sides as $side => $label)
                        <label class="min-w-0 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $label }} (mm)
                            <input type="number" inputmode="decimal" step="0.5" min="0" max="40" name="default[{{ $side }}]" value="{{ old('default.'.$side, $mm($default[$side] ?? null)) }}" data-demo="{{ $side }}" @disabled(! $canEdit) class="{{ $box }}">
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 break-words text-xs text-on-surface-variant">Between 0 and 40 mm. Leave all four empty to keep the margins each document already has. Documents printed page by page (such as the SOB and the AIP) keep at least 13 mm at the top and bottom, because printers cannot print closer to the paper edge, unless the document has margins of its own in the table below.</p>
            </div>
            <div class="print-sheet-demo" aria-hidden="true" id="print-demo"><span></span></div>
        </div>
    </section>

    <section class="rounded-xl border border-outline-variant/60 bg-white p-5 shadow-sm" aria-labelledby="print-test">
        <h3 id="print-test" class="text-sm font-bold text-primary">Find the smallest margin of your printer</h3>
        <p class="mt-1 break-words text-xs text-on-surface-variant">Every printer has a strip at the paper edge it cannot print on. Print the test page once with Margins: None and Scale: 100, read the smallest number you can see on each side, and use those numbers (plus 1 mm) as your margins. That fills the paper as much as your printer allows.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('school-settings.printing.margin-test', ['paper' => $paper, 'orientation' => 'portrait']) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-action px-4 text-sm font-bold text-action hover:bg-action/10"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">straighten</span>Test page (portrait)</a>
            <a href="{{ route('school-settings.printing.margin-test', ['paper' => $paper, 'orientation' => 'landscape']) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-action px-4 text-sm font-bold text-action hover:bg-action/10"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">straighten</span>Test page (landscape)</a>
        </div>
    </section>

    <section class="rounded-xl border border-outline-variant/60 bg-white p-5 shadow-sm" aria-labelledby="print-paper">
        <h3 id="print-paper" class="text-sm font-bold text-primary">Paper size</h3>
        <label class="mt-3 block max-w-sm text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Every document opens on
            <select name="paper" @disabled(! $canEdit) class="{{ $box }} font-semibold normal-case tracking-normal text-on-surface">
                <option value="" @selected(! $paper)>Each document's own paper</option>
                @foreach(\App\Services\PrintSettings::PAPERS as $key => $label)<option value="{{ $key }}" @selected($paper === $key)>{{ $label }}</option>@endforeach
            </select>
        </label>
        <p class="mt-3 break-words text-xs text-on-surface-variant">Choose the paper that is loaded in your printer. If the printer has no paper of the size a document asks for, the browser swaps in a shorter one and a page prints across two sheets. A paper chosen in a document's own toolbar still wins for that document on that computer.</p>
    </section>

    @if($canEdit)
    <section class="rounded-xl border border-outline-variant/60 bg-white p-5 shadow-sm" aria-labelledby="print-procurement">
        <h3 id="print-procurement" class="text-sm font-bold text-primary">All procurement documents at once</h3>
        <p class="mt-1 break-words text-xs text-on-surface-variant">Type the margins you also use in the browser's print dialog, then press the button to fill the rows of the Purchase Request, RFQ, Purchase Order, Notice of Award, Notice to Proceed, Abstract, IAR, RIS, IAR (Supplies), ICS, Delivery Reconciliation and the other procurement documents. A document with its own margins uses exactly those: the 13 mm minimum no longer applies to it, so a page holds as many rows as fit. Nothing is saved until you press Save print settings.</p>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach($sides as $side => $label)
                <label class="min-w-0 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $label }} (mm)
                    <input type="number" inputmode="decimal" step="0.5" min="0" max="40" data-procurement-side="{{ $side }}" class="{{ $box }}">
                </label>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button type="button" id="fill-procurement" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-action px-4 text-sm font-bold text-action hover:bg-action/10"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">content_copy</span>Fill the procurement documents</button>
            <p id="fill-procurement-note" role="status" class="min-w-0 break-words text-xs text-on-surface-variant"></p>
        </div>
    </section>
    @endif

    <section class="rounded-xl border border-outline-variant/60 bg-white shadow-sm" aria-labelledby="print-documents">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
            <h3 id="print-documents" class="text-sm font-bold text-primary">Margins of one document</h3>
            <p class="mt-0.5 break-words text-xs text-on-surface-variant">Fill in all four boxes to give a document its own margins. Documents marked "Own row only" (the ORS, DV and the procurement documents) are fixed forms: they do not follow the default and change only when their own row is filled in.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <caption class="sr-only">Margins in millimetres for each printable document</caption>
                <thead>
                    <tr class="border-b border-outline-variant/30 text-left text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">
                        <th scope="col" class="px-5 py-2">Document</th>
                        @foreach($sides as $label)<th scope="col" class="px-2 py-2">{{ $label }}</th>@endforeach
                        <th scope="col" class="px-5 py-2"><span class="sr-only">Clear</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(\App\Services\PrintSettings::DOCUMENTS as $key => $name)
                        @php $own = $printSettings['documents'][$key] ?? null; @endphp
                        <tr class="border-b border-outline-variant/20 last:border-0" data-doc-row="{{ $key }}">
                            <th scope="row" class="min-w-0 break-words px-5 py-2 text-left font-medium">{{ $name }}@if(in_array($key, $ownRowOnly, true))<span class="mt-0.5 block text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Own row only</span>@endif</th>
                            @foreach($sides as $side => $label)
                                <td class="px-2 py-2">
                                    <input type="number" inputmode="decimal" step="0.5" min="0" max="40" name="documents[{{ $key }}][{{ $side }}]" value="{{ old('documents.'.$key.'.'.$side, $mm($own[$side] ?? null)) }}" placeholder="{{ $mm($default[$side] ?? null) ?: 'default' }}" aria-label="{{ $name }}, {{ strtolower($label) }} margin in millimetres" @disabled(! $canEdit) class="{{ str_replace('w-full ', '', $box) }} w-24">
                                </td>
                            @endforeach
                            <td class="px-5 py-2 text-right">
                                @if($canEdit)<button type="button" data-clear-row aria-label="Use default for {{ $name }}" class="inline-flex h-11 items-center rounded-lg px-3 text-xs font-bold text-action hover:bg-action/10">Use default</button>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($canEdit)
        <div class="flex justify-end"><button type="submit" class="inline-flex h-11 items-center gap-2 rounded-lg bg-action px-5 text-sm font-bold text-white hover:opacity-90"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>Save print settings</button></div>
    @else
        <p class="text-xs text-on-surface-variant">Only users who manage the organization settings can change these margins.</p>
    @endif
</form>
<script>
    (() => {
        const demo = document.getElementById('print-demo');
        const sides = {top: ['--demo-t', 297], bottom: ['--demo-b', 297], left: ['--demo-l', 210], right: ['--demo-r', 210]};
        const draw = () => document.querySelectorAll('[data-demo]').forEach((input) => {
            const [variable, size] = sides[input.dataset.demo];
            const value = Math.min(40, Math.max(0, parseFloat(input.value) || 0));
            demo.style.setProperty(variable, (value / size * 100) + '%');
        });
        document.querySelectorAll('[data-demo]').forEach((input) => input.addEventListener('input', draw));
        const fill = document.getElementById('fill-procurement');
        if (fill) {
            const note = document.getElementById('fill-procurement-note');
            fill.addEventListener('click', () => {
                const values = {};
                let complete = true;
                document.querySelectorAll('[data-procurement-side]').forEach((input) => {
                    values[input.dataset.procurementSide] = input.value.trim();
                    if (input.value.trim() === '' || isNaN(parseFloat(input.value))) { complete = false; }
                });
                if (!complete) { note.textContent = 'Fill all four margins first (top, right, bottom, left).'; return; }
                const keys = @json(\App\Services\PrintSettings::PROCUREMENT);
                keys.forEach((key) => {
                    const row = document.querySelector('[data-doc-row="' + key + '"]');
                    if (!row) { return; }
                    Object.keys(values).forEach((side) => { const box = row.querySelector('input[name$="[' + side + ']"]'); if (box) { box.value = values[side]; } });
                });
                note.textContent = 'Filled ' + keys.length + ' documents. Press Save print settings to keep them.';
            });
        }
        document.querySelectorAll('[data-clear-row]').forEach((button) => button.addEventListener('click', () => {
            button.closest('tr').querySelectorAll('input').forEach((input) => { input.value = ''; });
        }));
        draw();
    })();
</script>
