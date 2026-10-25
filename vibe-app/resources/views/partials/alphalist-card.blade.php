{{-- Liquidation page: Alphalist - Tax Remittance. Preview or generate a month's alphalist, then work from its History (view, remittance DVs, edit, delete). --}}
@php
    $alphaLabel = 'block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant';
    $alphaBox = 'mt-1 h-[38px] w-full rounded border border-outline-variant/50 bg-surface-low px-3 text-sm normal-case tracking-normal outline-none focus:border-primary';
    $alphaHistory = \App\Models\CashReportHistory::with('generator', 'school')->where('report', 'alphalist')->whereIn('school_id', $schools->pluck('id'))->latest()->limit(50)->get();
    $remittanceDvs = \App\Models\LiquidationReport::whereIn('school_id', $schools->pluck('id'))->whereNotNull('remittance')->get()->keyBy(fn ($dv) => ($dv->remittance['history_id'] ?? '').'|'.($dv->remittance['form'] ?? ''));
    $canMakeDv = auth()->user()?->hasPermission('accounting.approve');
    $canChangeAlpha = auth()->user()?->hasPermission('liquidation.create');
    $funds = array_diff(\App\Models\CashFundReceipt::FUNDS, ['Others']);
@endphp
<details class="mt-8 rounded border border-outline-variant/30 bg-white" @if($alphaHistory->isNotEmpty() || session('error')) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-outline-variant/30 p-5 [&::-webkit-details-marker]:hidden">
        <div><h2 class="text-lg font-semibold">Alphalist - Tax Remittance</h2><p class="mt-1 text-xs text-on-surface-variant">Monthly Alphalist of Payees for the 1600PT, 0619E / 1601EQ and 1600VT remittances, from the withholding tax of the DVs paid in the month before the returned period.</p></div>
        <span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant">expand_more</span>
    </summary>

    <div class="space-y-6 p-5">
        <section aria-labelledby="alpha-new">
            <h3 id="alpha-new" class="flex items-center gap-2 text-sm font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">1</span>New alphalist</h3>
            <form id="alpha-form" method="POST" action="{{ route('liquidation.alphalist.store') }}" target="_blank" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">@csrf
                <label class="{{ $alphaLabel }}">School<select name="school_id" required class="{{ $alphaBox }}">@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) $selectedSchoolId === (string) $school->id)>{{ $school->name }}</option>@endforeach</select></label>
                <label class="{{ $alphaLabel }}">Returned period<input type="month" name="month" required value="{{ now()->format('Y-m') }}" class="{{ $alphaBox }}"></label>
                <label class="{{ $alphaLabel }}">Source of fund<input name="source_of_fund" list="alpha-funds" placeholder="All funds" class="{{ $alphaBox }}"><datalist id="alpha-funds">@foreach($funds as $fundName)<option value="{{ $fundName }}"></option>@endforeach</datalist></label>
                <label class="{{ $alphaLabel }}">School TIN<input name="school_tin" placeholder="000-000-000-000" class="{{ $alphaBox }}"></label>
                <label class="{{ $alphaLabel }}">Signatory<input name="signatory_name" placeholder="School Head (default)" class="{{ $alphaBox }}"></label>
            </form>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-on-surface-variant">The returned period lists the tax withheld from the payments of the month before it (November lists October). One alphalist per school and month: Preview shows it without saving.</p>
                <div class="flex shrink-0 gap-2">
                    <button type="button" id="alpha-preview" class="flex h-[38px] items-center gap-1.5 rounded-lg border border-primary px-4 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>Preview</button>
                    <button form="alpha-form" class="flex h-[38px] items-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>Generate &amp; save</button>
                </div>
            </div>
        </section>

        <section aria-labelledby="alpha-history">
            <h3 id="alpha-history" class="flex items-center gap-2 text-sm font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">2</span>History <span class="text-xs font-normal text-on-surface-variant">({{ $alphaHistory->count() }})</span></h3>
            <div class="mt-3 overflow-x-auto rounded border border-outline-variant/30">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead class="bg-surface-low text-[11px] uppercase tracking-wider text-on-surface-variant">
                        <tr><th class="px-3 py-2 font-semibold">Returned period</th><th class="px-3 py-2 font-semibold">School · Fund</th><th class="px-3 py-2 text-right font-semibold">Tax withheld</th><th class="px-3 py-2 font-semibold">Generated</th><th class="px-3 py-2 font-semibold">Remittance DVs</th><th class="px-3 py-2 text-right font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                    @forelse($alphaHistory as $entry)
                        @php $entryDvs = collect($entry->params['remittance'] ?? [])->keys()->map(fn ($form) => $remittanceDvs->get($entry->id.'|'.$form))->filter(); @endphp
                        <tr class="align-top hover:bg-surface-low/60">
                            <td class="px-3 py-2.5 font-semibold">{{ $entry->period_text }}</td>
                            <td class="px-3 py-2.5">{{ $entry->school?->name }}<div class="text-on-surface-variant">{{ $entry->source_of_fund ?: 'All funds' }}</div></td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">₱{{ number_format((float) $entry->total_payments, 2) }}</td>
                            <td class="px-3 py-2.5">{{ $entry->created_at->format('M d, Y g:i A') }}<div class="text-on-surface-variant">{{ $entry->generator?->name ?? '—' }}</div></td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                @forelse(($entry->params['remittance'] ?? []) as $form => $block)
                                    @php $dv = $remittanceDvs->get($entry->id.'|'.$form); $formLabel = \App\Services\AlphalistService::FORMS[$form] ?? $form; @endphp
                                    @if($dv)
                                        <a href="{{ route('accounting', ['tab' => $dv->dv_number ? 'with_dv' : 'for_dv', 'open_dv' => $dv->id]) }}" class="rounded bg-secondary/10 px-2 py-1 font-semibold text-secondary hover:underline" title="View the DV">{{ $formLabel }}: {{ $dv->dv_number ? \App\Models\LiquidationReport::dvLabel($dv->dv_number) : $dv->report_number }}</a>
                                    @elseif($canMakeDv)
                                        <form method="POST" action="{{ route('liquidation.alphalist.dv', [$entry, $form]) }}">@csrf<button class="rounded border border-primary px-2 py-1 font-semibold text-primary hover:bg-primary hover:text-white">Create DV {{ $formLabel }} · ₱{{ number_format((float) $block['total'], 2) }}</button></form>
                                    @else
                                        <span class="rounded bg-surface-container px-2 py-1 text-on-surface-variant">{{ $formLabel }} · ₱{{ number_format((float) $block['total'], 2) }}</span>
                                    @endif
                                @empty
                                    <span class="text-on-surface-variant">—</span>
                                @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('liquidation.alphalist.history', $entry) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 px-2 py-1 font-semibold text-primary hover:bg-primary hover:text-white">View</a>
                                    @if($canChangeAlpha)
                                        <button type="button" data-alpha-open="alpha-edit-{{ $entry->id }}" class="rounded border border-outline-variant/60 px-2 py-1 font-semibold text-primary hover:bg-primary hover:text-white">Edit</button>
                                        <button type="button" data-alpha-open="alpha-delete-{{ $entry->id }}" class="rounded border border-error/60 px-2 py-1 font-semibold text-error hover:bg-error hover:text-white">Delete</button>
                                    @endif
                                </div>
                                @if($canChangeAlpha)
                                    <dialog id="alpha-edit-{{ $entry->id }}" class="w-[min(92vw,26rem)] rounded-xl p-0 text-xs shadow-2xl backdrop:bg-black/40">
                                        <form method="POST" action="{{ route('liquidation.alphalist.update', $entry) }}" class="space-y-3 p-5">@csrf @method('PUT')
                                            <h4 class="text-sm font-semibold">Edit the alphalist of {{ $entry->period_text }}</h4>
                                            <p class="text-on-surface-variant">{{ $entry->school?->name }}. Update makes it again from today's figures.</p>
                                            @if($entryDvs->isNotEmpty())<p class="rounded bg-[#fdf4e3] px-3 py-2 font-semibold text-[#7a5410]">Remittance DV already issued ({{ $entryDvs->map(fn ($dv) => $dv->dv_number ?: $dv->report_number)->implode(', ') }}): it keeps its own amount and register columns.</p>@endif
                                            <label class="{{ $alphaLabel }}">Source of fund<input name="source_of_fund" list="alpha-funds" value="{{ $entry->params['source_of_fund'] ?? '' }}" placeholder="All funds" class="{{ $alphaBox }}"></label>
                                            <label class="{{ $alphaLabel }}">School TIN<input name="school_tin" value="{{ $entry->params['school_tin'] ?? '' }}" class="{{ $alphaBox }}"></label>
                                            <label class="{{ $alphaLabel }}">Signatory<input name="signatory_name" value="{{ $entry->params['signatory_name'] ?? '' }}" placeholder="School Head (default)" class="{{ $alphaBox }}"></label>
                                            <label class="{{ $alphaLabel }}">Reason<input name="reason" required class="{{ $alphaBox }}"></label>
                                            <label class="{{ $alphaLabel }}">Your password<input type="password" name="password" required autocomplete="current-password" class="{{ $alphaBox }}"></label>
                                            <div class="flex justify-end gap-2 pt-1"><button type="button" data-alpha-close class="rounded-lg border border-outline-variant/60 px-4 py-2 font-semibold">Cancel</button><button class="rounded-lg bg-primary px-4 py-2 font-semibold text-white hover:bg-primary-container">Update</button></div>
                                        </form>
                                    </dialog>
                                    <dialog id="alpha-delete-{{ $entry->id }}" class="w-[min(92vw,26rem)] rounded-xl p-0 text-xs shadow-2xl backdrop:bg-black/40">
                                        <form method="POST" action="{{ route('liquidation.alphalist.destroy', $entry) }}" class="space-y-3 p-5">@csrf @method('DELETE')
                                            <h4 class="text-sm font-semibold text-error">Delete the alphalist of {{ $entry->period_text }}?</h4>
                                            <p class="text-on-surface-variant">{{ $entry->school?->name }} · {{ $entry->source_of_fund ?: 'All funds' }} · ₱{{ number_format((float) $entry->total_payments, 2) }}</p>
                                            @if($entryDvs->isNotEmpty())<p class="rounded bg-[#fdf4e3] px-3 py-2 font-semibold text-[#7a5410]">Remittance DV already issued ({{ $entryDvs->map(fn ($dv) => $dv->dv_number ?: $dv->report_number)->implode(', ') }}): the DV stays, with its own amount and register columns.</p>@endif
                                            <label class="{{ $alphaLabel }}">Reason<input name="reason" required class="{{ $alphaBox }}"></label>
                                            <label class="{{ $alphaLabel }}">Your password<input type="password" name="password" required autocomplete="current-password" class="{{ $alphaBox }}"></label>
                                            <div class="flex justify-end gap-2 pt-1"><button type="button" data-alpha-close class="rounded-lg border border-outline-variant/60 px-4 py-2 font-semibold">Cancel</button><button class="rounded-lg bg-error px-4 py-2 font-semibold text-white">Delete</button></div>
                                        </form>
                                    </dialog>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-on-surface-variant">No alphalist generated yet. Choose the school and returned period above, then Preview or Generate &amp; save.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</details>
<script>
    (function () {
        // Preview: the same alphalist, opened without saving it (the form values go in the address, never the form's token).
        document.getElementById('alpha-preview').addEventListener('click', function () {
            const form = document.getElementById('alpha-form');
            if (!form.reportValidity()) { return; }
            const query = new URLSearchParams();
            new FormData(form).forEach(function (value, key) { if (key !== '_token' && value !== '') { query.append(key, value); } });
            window.open(@json(route('liquidation.alphalist')) + '?' + query.toString(), '_blank', 'noopener');
        });
        document.querySelectorAll('[data-alpha-open]').forEach(function (button) {
            button.addEventListener('click', function () { document.getElementById(button.dataset.alphaOpen).showModal(); });
        });
        document.querySelectorAll('[data-alpha-close]').forEach(function (button) {
            button.addEventListener('click', function () { button.closest('dialog').close(); });
        });
    })();
</script>
