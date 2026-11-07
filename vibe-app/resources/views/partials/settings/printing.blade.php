@php
    $canEdit = auth()->user()?->hasPermission('organization.settings');
    $sides = ['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'];
    $mm = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $default = $printSettings['default'] ?? null;
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

<form method="POST" action="{{ route('school-settings.printing.update') }}" @if($canEdit) data-guard="Save the print settings" @endif class="grid items-start gap-5">
    @csrf @method('PUT')
    <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">

    <section class="rounded-xl border border-outline-variant/60 bg-white p-5 shadow-sm" aria-labelledby="print-default">
        <h3 id="print-default" class="text-sm font-bold text-primary">Default margins</h3>
        <div class="mt-3 grid items-start gap-5 sm:grid-cols-[1fr_150px]">
            <div class="min-w-0">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($sides as $side => $label)
                        <label class="min-w-0 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $label }} (mm)
                            <input type="number" inputmode="decimal" step="0.5" min="0" max="40" name="default[{{ $side }}]" value="{{ $mm($default[$side] ?? null) }}" data-demo="{{ $side }}" @disabled(! $canEdit) class="{{ $box }}">
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 break-words text-xs text-on-surface-variant">Between 0 and 40 mm. Leave all four empty to keep the margins each document already has. Documents printed page by page (such as the SOB and the AIP) always keep at least 13 mm at the top and bottom, because printers cannot print closer to the paper edge.</p>
            </div>
            <div class="print-sheet-demo" aria-hidden="true" id="print-demo"><span></span></div>
        </div>
    </section>

    <section class="rounded-xl border border-outline-variant/60 bg-white shadow-sm" aria-labelledby="print-documents">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
            <h3 id="print-documents" class="text-sm font-bold text-primary">Margins of one document</h3>
            <p class="mt-0.5 break-words text-xs text-on-surface-variant">Fill in all four boxes to give a document its own margins. The Obligation Request (ORS) and the Disbursement Voucher (DV) are fixed forms: they follow only their own row here, not the default.</p>
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
                            <th scope="row" class="min-w-0 break-words px-5 py-2 text-left font-medium">{{ $name }}</th>
                            @foreach($sides as $side => $label)
                                <td class="px-2 py-2">
                                    <input type="number" inputmode="decimal" step="0.5" min="0" max="40" name="documents[{{ $key }}][{{ $side }}]" value="{{ $mm($own[$side] ?? null) }}" placeholder="{{ $mm($default[$side] ?? null) ?: 'default' }}" aria-label="{{ $name }}, {{ strtolower($label) }} margin in millimetres" @disabled(! $canEdit) class="{{ $box }} w-24">
                                </td>
                            @endforeach
                            <td class="px-5 py-2 text-right">
                                @if($canEdit)<button type="button" data-clear-row class="inline-flex h-11 items-center rounded-lg px-3 text-xs font-bold text-action hover:bg-action/10">Use default</button>@endif
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
        document.querySelectorAll('[data-clear-row]').forEach((button) => button.addEventListener('click', () => {
            button.closest('tr').querySelectorAll('input').forEach((input) => { input.value = ''; });
        }));
        draw();
    })();
</script>
