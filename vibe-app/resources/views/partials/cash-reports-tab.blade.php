{{-- The Reports tab of Cash: the period, the three cash reports and the fund received (opening balance, NCA, cash advance). Design standard of docs/specs.md section 7. --}}
@php
    $box = 'mt-1.5 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal normal-case tracking-normal text-on-surface shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant';
    $head = fn (int $number, string $title, string $hint) => '<div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-white">'.$number.'</span><div><h2 class="text-sm font-bold text-primary">'.e($title).'</h2><p class="text-xs text-on-surface-variant">'.e($hint).'</p></div></div>';
    $reportLinks = [
        ['cash.report.roci', 'Report of Checks Issued', 'Appendix 35: every check of the period, cancelled checks included.', 'fact_check', ['officer_id' => 'Disbursing Officer / Cashier'], false],
        ['cash.report.checks', 'Checks and Advices to Debit Account', 'Disbursement record with the check and ADA columns and the running balance.', 'account_balance', ['officer_id' => 'Disbursing Officer'], false],
        ['cash.report.register', 'Cash Disbursement Register', 'Working fund, payments and the breakdown per expense account.', 'menu_book', ['officer_id' => 'Certified correct (Accountable Officer)', 'received_by_id' => 'Received by'], false],
        ['cash.report.bank', 'Bank Reconciliation', 'Cash downloaded to the account and the checks and ADAs paid, with the running balance. The other reports start from it.', 'account_balance_wallet', [], true],
    ];
@endphp
<div class="space-y-5 p-5">
    @if(! $reportSchool)
        <p class="rounded-lg border border-outline-variant/40 bg-white px-4 py-6 text-center text-sm text-on-surface-variant">No school to report on.</p>
    @else
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        {!! $head(1, 'Reports', 'Choose a report to fill in its form and see it on its official form.') !!}
        <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach($reportLinks as [$routeName, $title, $hint, $icon, $slots, $isBank])
                <button type="button" data-cash-report-open data-url="{{ route($routeName) }}" data-slots='@json($slots)' data-bank="{{ ($isBank ?? false) ? 1 : 0 }}" data-register="{{ $routeName === 'cash.report.register' ? 1 : 0 }}" data-form="{{ $routeName === 'cash.report.register' ? 'r' : ($routeName === 'cash.report.checks' ? 'c' : (($isBank ?? false) ? 'b' : 'o')) }}" data-report-no="{{ $routeName === 'cash.report.roci' ? now()->format('Y-m').'-001' : '' }}" data-title="{{ $title }}" class="group flex items-start gap-3 rounded-xl border border-outline-variant/50 border-l-4 border-l-primary bg-white p-4 text-left shadow-sm transition hover:bg-[#eef4fa]">
                    <span class="material-symbols-outlined text-[24px] text-primary" aria-hidden="true">{{ $icon }}</span>
                    <span><span class="block text-sm font-bold text-primary">{{ $title }}</span><span class="mt-1 block text-xs text-on-surface-variant">{{ $hint }}</span></span>
                </button>
            @endforeach
        </div>
    </section>
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        {!! $head(2, 'Report history', 'Press Save to history in the window of any report to keep it as it was generated. Open it again to print the same document.') !!}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="bg-surface-low uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-2">Generated</th><th class="px-3 py-2">Report</th><th class="px-3 py-2">Period covered</th><th class="px-3 py-2">Fund</th><th class="px-3 py-2">Report No.</th><th class="px-3 py-2 text-right">Payments</th><th class="px-3 py-2">By</th><th class="px-4 py-2 text-right"></th></tr></thead>
                <tbody id="register-history-rows" class="divide-y divide-outline-variant/30">
                    @forelse($reportHistory as $entry)
                        @php $keptParams = $entry->params ?: array_filter(['school_id' => $entry->school_id, 'from' => $entry->period_from->toDateString(), 'to' => $entry->period_to->toDateString(), 'source_of_fund' => $entry->source_of_fund, 'report_no' => $entry->report_no]); @endphp
                        <tr><td class="px-4 py-2.5">{{ $entry->created_at->format('M d, Y g:i A') }}</td><td class="px-3 py-2.5 font-semibold">{{ $entry->label() }}</td><td class="px-3 py-2.5">{{ $entry->period_text }}</td><td class="px-3 py-2.5">{{ $entry->source_of_fund ?: 'All funds' }}</td><td class="px-3 py-2.5">{{ $entry->report_no ?: '—' }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $entry->total_payments, 2) }}</td><td class="px-3 py-2.5">{{ $entry->generator?->name }}</td><td class="px-4 py-2.5 text-right"><div class="flex items-center justify-end gap-1.5"><a href="{{ route('cash.report.history', $entry->id) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 px-3 py-1.5 font-semibold text-primary hover:bg-primary hover:text-white">Open</a><button type="button" data-history-edit data-kind="{{ $entry->report }}" data-url="{{ route('cash.report.history.update', $entry->id) }}" data-name="{{ $entry->label() }} · {{ $entry->period_text }}" data-params='@json($keptParams)' title="Edit" aria-label="Edit {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></button><form method="POST" action="{{ route('cash.report.history.destroy', $entry->id) }}" data-guard="Delete {{ $entry->label() }} · {{ $entry->period_text }} from the history">@csrf @method('DELETE')<button title="Delete from the history" aria-label="Delete {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-error/50 text-error hover:bg-error hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button></form></div></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-on-surface-variant">No report has been generated yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
@if($reportSchool)
<div id="cash-report-modal" role="dialog" aria-modal="true" aria-labelledby="cash-report-title" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-3 md:left-60">
    <div class="flex max-h-[96vh] w-full max-w-[1500px] flex-col overflow-hidden rounded bg-white shadow-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-outline-variant/30 bg-white px-5 py-4">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-primary">Cash Report</p><h2 id="cash-report-title" class="mt-1 text-lg font-semibold"></h2></div>
            <button type="button" id="cash-report-close" aria-label="Close report form" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="flex min-h-0 flex-1 flex-col-reverse lg:flex-row-reverse">
            <aside class="flex min-w-0 flex-[1_1_60%] flex-col border-l border-outline-variant/40 bg-[#d9dee5]">
                <div class="flex items-center justify-between border-b border-outline-variant/40 bg-white px-4 py-2 text-xs font-bold text-primary"><span>Report (updates as you edit)</span><small id="cash-report-status" class="font-normal text-on-surface-variant"></small></div>
                <iframe id="cash-report-frame" title="Cash report preview" class="min-h-[60vh] w-full flex-1 border-0 bg-[#d9dee5]"></iframe>
            </aside>
            <div class="overflow-y-auto p-5 lg:flex-[0_0_min(34%,420px)]">
                <div class="space-y-4">
                    <section class="rounded-xl border border-outline-variant/50 p-4">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">School and period</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="{{ $label }} sm:col-span-2">School<select id="cash-report-school" class="{{ $box }}">@foreach($reportSchools as $option)<option value="{{ $option->id }}" @selected($option->id === $reportSchool->id)>{{ $option->name }}</option>@endforeach</select></label>
                            <label class="{{ $label }}">Period covered<select id="cash-report-quarter" data-native class="{{ $box }}"></select></label>
                            <label class="{{ $label }}">Year (C.Y.)<input type="number" id="cash-report-year" min="2000" max="2100" step="1" value="{{ $reportFrom->year }}" class="{{ $box }}"></label>
                            <label class="{{ $label }}">From<input type="date" id="cash-report-from" value="{{ $reportFrom->toDateString() }}" class="{{ $box }}"></label>
                            <label class="{{ $label }}">To<input type="date" id="cash-report-to" value="{{ $reportTo->toDateString() }}" class="{{ $box }}"></label>
                        </div>
                    </section>
                    <section class="rounded-xl border border-outline-variant/50 p-4">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">Header of the form</h3>
                        <div id="cash-report-header" class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label data-o="1" class="{{ $label }}">Report No.<input id="cash-report-no" maxlength="50" class="{{ $box }}"></label>
                            <label data-o="2" data-r="9" data-c="4" class="{{ $label }}">Sheet No.<input id="cash-sheet-no" maxlength="50" placeholder="1 of 1" class="{{ $box }}"></label>
                            <label data-o="3" data-r="4" data-c="3" class="{{ $label }}">Fund Cluster<input id="cash-report-cluster" maxlength="100" class="{{ $box }}"></label>
                            <label data-o="3" data-r="4" data-c="3" data-b="3" class="{{ $label }}">Source of Fund<select id="cash-report-source" class="{{ $box }}">@foreach(\App\Models\CashFundReceipt::FUNDS as $fundName)<option value="{{ $fundName }}">{{ $fundName }}</option>@endforeach</select></label>
                            <label id="cash-report-source-other-wrap" data-o="3" data-r="4" data-c="3" data-b="3" class="{{ $label }} hidden sm:col-span-2">Specify the fund<input id="cash-report-source-other" maxlength="50" placeholder="Name of the fund" class="{{ $box }}"></label>
                            <div data-o="4" data-c="2" class="grid gap-3 sm:col-span-2 sm:grid-cols-[3fr_2fr]">
                                <label class="{{ $label }}">Bank Name<input id="cash-report-bank-name" maxlength="100" class="{{ $box }}"></label>
                                <label class="{{ $label }}">Bank Account No.<input id="cash-report-bank-account" maxlength="60" class="{{ $box }}"></label>
                            </div>
                            <label data-r="1" data-c="1" class="{{ $label }} sm:col-span-2">Entity Name<input id="cash-report-entity" maxlength="150" class="{{ $box }}"></label>
                            <label data-r="2" class="{{ $label }} sm:col-span-2">Sub Office / District / Division<input id="cash-report-district" maxlength="150" class="{{ $box }}"></label>
                            <label data-r="3" class="{{ $label }} sm:col-span-2">Municipality / City / Province<input id="cash-report-municipality" maxlength="200" class="{{ $box }}"></label>
                            <label data-r="5" data-c="5" class="{{ $label }} sm:col-span-2">Name of Accountable Officer<input id="cash-report-officer-name" maxlength="150" class="{{ $box }}"></label>
                            <label data-r="6" data-c="6" class="{{ $label }} sm:col-span-2">Official Designation<input id="cash-report-officer-position" maxlength="150" class="{{ $box }}"></label>
                            <label data-r="7" data-c="7" class="{{ $label }}">Station<input id="cash-report-station" maxlength="150" class="{{ $box }}"></label>
                            <label data-r="8" class="{{ $label }}">Registered No.<input id="cash-report-registered" maxlength="50" class="{{ $box }}"></label>
                        </div>
                    </section>
                    <section data-hide-bank class="rounded-xl border border-outline-variant/50 p-4">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">Signatories</h3>
                        <div id="cash-report-slots" class="mt-3 space-y-3"></div>
                    </section>
                    <section data-bank-only class="hidden rounded-xl border border-outline-variant/50 p-4">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">Cash downloaded</h3>
                        <p class="mt-1 text-xs text-on-surface-variant">The opening balance, NCA or cash advance credited to the account. The records are kept and feed the Bank Reconciliation and the other reports.</p>
                        <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant/40">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-surface-low uppercase tracking-wider text-on-surface-variant"><tr><th class="px-3 py-2">Date</th><th class="px-2 py-2">Kind</th><th class="px-2 py-2">Reference</th><th class="px-2 py-2 text-right">Amount</th><th class="px-3 py-2"></th></tr></thead>
                                <tbody id="cash-fund-rows" class="divide-y divide-outline-variant/30"></tbody>
                            </table>
                        </div>
                        <form id="cash-fund-form" class="mt-3 grid gap-3 sm:grid-cols-2">
                            <input type="hidden" id="cash-fund-id">
                            <label class="{{ $label }}">Date<input type="date" id="cash-fund-date" required class="{{ $box }}"></label>
                            <label class="{{ $label }}">Kind<select id="cash-fund-kind" class="{{ $box }}">@foreach(\App\Models\CashFundReceipt::KINDS as $key => $kindLabel)<option value="{{ $key }}">{{ $kindLabel }}</option>@endforeach</select></label>
                            <label class="{{ $label }}">Fund<select id="cash-fund-source" required class="{{ $box }}"><option value="">Select the fund</option>@foreach(\App\Models\CashFundReceipt::FUNDS as $fundName)<option value="{{ $fundName }}">{{ $fundName }}</option>@endforeach</select></label>
                            <label id="cash-fund-other-wrap" class="{{ $label }} hidden sm:col-span-2">Specify the fund<input id="cash-fund-other" maxlength="50" placeholder="Name of the fund" class="{{ $box }}"></label>
                            <label class="{{ $label }}">Amount<input type="number" step="0.01" min="0.01" id="cash-fund-amount" required class="{{ $box }} text-right tabular-nums"></label>
                            <label class="{{ $label }}">Reference<input id="cash-fund-reference" maxlength="100" class="{{ $box }}"></label>
                            <label class="{{ $label }} sm:col-span-2">Note<input id="cash-fund-note" maxlength="255" class="{{ $box }}"></label>
                            <p id="cash-fund-error" class="hidden text-xs font-semibold text-error sm:col-span-2"></p>
                            <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" id="cash-fund-reset" class="hidden rounded border border-outline-variant/50 px-3 py-2 text-xs font-semibold hover:bg-surface-low">Cancel edit</button><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container" id="cash-fund-save">Add</button></div>
                        </form>
                    </section>
                    <section data-hide-bank class="rounded-xl border border-outline-variant/50 p-4">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">Remarks</h3>
                        <label class="{{ $label }} mt-3">Printed above the signature<textarea id="cash-report-remarks" rows="3" maxlength="500" class="{{ $box }}"></textarea></label>
                    </section>
                    <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4">
                        <button type="button" id="cash-report-cancel" class="rounded border border-outline-variant/50 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button>
                        <button type="button" id="cash-report-save" class="hidden rounded-lg border border-primary px-4 py-2.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Save to history</button>
                        <a id="cash-report-open" href="#" target="_blank" rel="noopener" class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Open to print</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
<script>
    // Each report opens as a split view: Report No. and Sheet No. on the left, the real report on the right.
    (() => {
        const modal = document.getElementById('cash-report-modal');
        if (!modal) return;
        const frame = document.getElementById('cash-report-frame');
        const status = document.getElementById('cash-report-status');
        const printLink = document.getElementById('cash-report-open');
        const field = (id) => document.getElementById(id);
        const staffBySchool = @json($reportStaff);
        const bankDefaults = @json($reportBankDefaults);
        const slotBox = field('cash-report-slots');
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}</style>';
        let baseUrl = ''; let timer = null; let controller = null; let registerOn = false; let formKey = 'o';
        // Fund Cluster, Bank Name and Bank Account No. start from the school's settings (and its last payment); a value the user typed is kept.
        const defaultFields = { 'cash-report-cluster': 'fund_cluster', 'cash-report-bank-name': 'bank_name', 'cash-report-bank-account': 'bank_account', 'cash-report-entity': 'entity_name', 'cash-report-district': 'district', 'cash-report-municipality': 'municipality', 'cash-report-registered': 'registered_no', 'cash-report-officer-name': 'officer_name', 'cash-report-officer-position': 'officer_position' };
        const fillDefaults = () => {
            const defaults = bankDefaults[field('cash-report-school').value] || {};
            Object.entries(defaultFields).forEach(([id, key]) => { if (!field(id).dataset.typed) field(id).value = defaults[key] || ''; });
        };
        Object.keys(defaultFields).forEach((id) => field(id).addEventListener('input', () => { field(id).dataset.typed = '1'; }));
        // Period covered: a quarter of the calendar year of the From date fills From and To; typed dates that make a whole quarter pick it, anything else is Custom.
        const quarterSelect = field('cash-report-quarter');
        const quarterNames = ['1st', '2nd', '3rd', '4th'];
        const pad = (number) => String(number).padStart(2, '0');
        const quarterDates = (year, quarter) => [year + '-' + pad(quarter * 3 + 1) + '-01', year + '-' + pad(quarter * 3 + 3) + '-' + (quarter === 0 || quarter === 3 ? '31' : '30')];
        const yearInput = field('cash-report-year');
        const currentYear = () => parseInt(yearInput.value, 10) || parseInt((field('cash-report-from').value || '').slice(0, 4), 10) || new Date().getFullYear();
        const syncQuarter = () => {
            const year = currentYear();
            quarterSelect.innerHTML = '';
            quarterNames.forEach((name, quarter) => quarterSelect.add(new Option(name + ' Quarter C.Y. ' + year, quarter)));
            quarterSelect.add(new Option('Custom dates', 'custom'));
            const match = [0, 1, 2, 3].find((quarter) => { const [from, to] = quarterDates(year, quarter); return field('cash-report-from').value === from && field('cash-report-to').value === to; });
            quarterSelect.value = match === undefined ? 'custom' : String(match);
        };
        const applyQuarter = () => {
            if (quarterSelect.value === 'custom' || currentYear() < 2000 || currentYear() > 2100) return;
            [field('cash-report-from').value, field('cash-report-to').value] = quarterDates(currentYear(), parseInt(quarterSelect.value, 10));
            if (!modal.querySelector('[data-bank-only]').classList.contains('hidden')) loadFund();
            schedule();
        };
        quarterSelect.addEventListener('change', applyQuarter);
        // The year is typed by hand (a 2026 liquidation can be made in 2027); typing it moves the chosen quarter to that year.
        yearInput.addEventListener('input', () => {
            const keep = quarterSelect.value;
            syncQuarter();
            quarterSelect.value = keep;
            applyQuarter();
        });
        ['cash-report-from', 'cash-report-to'].forEach((id) => field(id).addEventListener('input', () => {
            const fromYear = parseInt((field('cash-report-from').value || '').slice(0, 4), 10);
            if (fromYear) yearInput.value = fromYear;
            syncQuarter();
        }));
        syncQuarter();
        const schedule = () => { clearTimeout(timer); timer = setTimeout(() => refresh(), 600); };
        const manualKey = { officer_id: 'officer', received_by_id: 'received_by' };
        const buildSlots = () => {
            const kept = {};
            slotBox.querySelectorAll('[data-slot]').forEach((control) => { kept[control.dataset.slot + (control.dataset.part || '')] = control.value; });
            slotBox.innerHTML = '';
            Object.entries(JSON.parse(slotBox.dataset.slots || '{}')).forEach(([key, text]) => {
                const label = document.createElement('label');
                label.className = @json($label);
                label.append(text);
                const select = document.createElement('select');
                select.className = @json($box);
                select.dataset.slot = key;
                select.add(new Option(key === 'officer_id' ? 'Default (Disbursing Officer)' : 'Leave blank', ''));
                select.add(new Option('Type a name (not in the staff list)', 'manual'));
                (staffBySchool[field('cash-report-school').value] || []).forEach((member) => select.add(new Option(member.label, member.id)));
                select.value = [...select.options].some((option) => option.value === kept[key]) ? kept[key] : '';
                label.append(select);
                slotBox.append(label);

                // A person who is not in the school's staff (for example the one who receives the payment) is typed by hand.
                const manual = document.createElement('div');
                manual.className = 'grid gap-2 sm:grid-cols-2';
                ['name', 'position'].forEach((part) => {
                    const input = document.createElement('input');
                    input.className = @json($box);
                    input.maxLength = 150;
                    input.placeholder = part === 'name' ? 'Full name' : 'Position';
                    input.dataset.slot = key;
                    input.dataset.part = part;
                    input.value = kept[key + part] || '';
                    input.addEventListener('input', () => { syncHeaderOfficer(key); schedule(); });
                    manual.append(input);
                });
                const showManual = () => manual.classList.toggle('hidden', select.value !== 'manual');
                showManual();
                slotBox.append(manual);

                select.addEventListener('change', () => { showManual(); syncHeaderOfficer(key); schedule(); });
            });
        };
        // The accountable officer of the header follows the officer chosen (or typed) for the certification unless it was typed by hand.
        const syncHeaderOfficer = (key) => {
            if (key !== 'officer_id') return;
            const select = slotBox.querySelector('select[data-slot="officer_id"]');
            if (!select) return;
            const defaults = bankDefaults[field('cash-report-school').value] || {};
            let name = defaults.officer_name || ''; let position = defaults.officer_position || '';
            if (select.value === 'manual') {
                name = slotBox.querySelector('input[data-slot="officer_id"][data-part="name"]').value;
                position = slotBox.querySelector('input[data-slot="officer_id"][data-part="position"]').value;
            } else if (select.value) {
                [name, position] = select.selectedOptions[0].text.split(' - ');
            }
            if (!field('cash-report-officer-name').dataset.typed) field('cash-report-officer-name').value = name || '';
            if (!field('cash-report-officer-position').dataset.typed) field('cash-report-officer-position').value = position || '';
        };
        const urlWithFields = () => {
            const url = new URL(baseUrl, window.location.origin);
            const set = (name, value) => { value ? url.searchParams.set(name, value) : url.searchParams.delete(name); };
            set('school_id', field('cash-report-school').value);
            set('from', field('cash-report-from').value);
            set('to', field('cash-report-to').value);
            set('report_no', field('cash-report-no').value.trim());
            set('sheet_no', field('cash-sheet-no').value.trim());
            set('fund_cluster', field('cash-report-cluster').value.trim());
            set('source_of_fund', field('cash-report-source').value === 'Others' ? field('cash-report-source-other').value.trim() : field('cash-report-source').value);
            set('bank_name', field('cash-report-bank-name').value.trim());
            set('bank_account', field('cash-report-bank-account').value.trim());
            set('remarks', field('cash-report-remarks').value.trim());
            if (formKey !== 'o') {
                set('entity_name', field('cash-report-entity').value.trim()); set('station', field('cash-report-station').value.trim());
                set('officer_name', field('cash-report-officer-name').value.trim()); set('officer_position', field('cash-report-officer-position').value.trim());
            }
            if (registerOn) {
                set('district', field('cash-report-district').value.trim()); set('municipality', field('cash-report-municipality').value.trim());
                set('registered_no', field('cash-report-registered').value.trim());
            }
            ['officer_id', 'received_by_id'].forEach((name) => {
                const choice = slotBox.querySelector('select[data-slot="' + name + '"]')?.value || '';
                set(name, choice);
                ['name', 'position'].forEach((part) => set(manualKey[name] + '_manual_' + part, choice === 'manual' ? (slotBox.querySelector('input[data-slot="' + name + '"][data-part="' + part + '"]')?.value.trim() || '') : ''));
            });
            return url.pathname + url.search;
        };
        // Save to history keeps a copy of the open report as it is now and adds it to the list.
        const historyKinds = { o: 'roci', c: 'checks', r: 'register', b: 'bank' };
        const saveButton = document.getElementById('cash-report-save');
        let editing = null;
        saveButton.addEventListener('click', async () => {
            if (editing) {
                // The edited form replaces the kept copy; the reason and the password are asked first.
                const form = document.createElement('form');
                form.method = 'POST'; form.action = editing.url; form.dataset.guard = 'Update ' + editing.name + ' in the history'; form.hidden = true;
                [['_token', document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token())], ['_method', 'PUT'], ['query', new URL(printLink.getAttribute('href'), location.origin).search.slice(1)]].forEach(([name, value]) => {
                    const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
                });
                document.body.append(form);
                form.requestSubmit();
                return;
            }
            saveButton.disabled = true;
            const notice = window.appNotify?.('Saving to history…', 'progress');
            try {
                const response = await fetch(@json(url('/cash/reports')) + '/' + historyKinds[formKey] + '/history' + new URL(printLink.getAttribute('href'), location.origin).search, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token()) } });
                if (response.status === 409) {
                    const kept = await response.json();
                    notice?.set(kept.message, 'info');
                    status.textContent = 'Already saved to history';
                    saveButton.disabled = false;
                    return;
                }
                if (!response.ok) throw new Error('save failed');
                const { entry } = await response.json();
                const rows = document.getElementById('register-history-rows');
                const row = document.createElement('tr');
                const cell = (text, classes) => { const td = document.createElement('td'); td.className = classes; td.textContent = text; row.append(td); return td; };
                cell(entry.generated, 'px-4 py-2.5'); cell(entry.report, 'px-3 py-2.5 font-semibold'); cell(entry.period, 'px-3 py-2.5'); cell(entry.fund, 'px-3 py-2.5'); cell(entry.report_no, 'px-3 py-2.5'); cell(entry.payments, 'px-3 py-2.5 text-right tabular-nums'); cell(entry.by, 'px-3 py-2.5');
                const open = document.createElement('a'); open.href = entry.url; open.target = '_blank'; open.rel = 'noopener'; open.textContent = 'Open'; open.className = 'rounded border border-outline-variant/60 px-3 py-1.5 font-semibold text-primary hover:bg-primary hover:text-white';
                const token = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
                const editButton = () => {
                    const button = document.createElement('button'); button.type = 'button'; button.title = 'Edit'; button.setAttribute('aria-label', 'Edit ' + entry.name);
                    button.dataset.historyEdit = ''; button.dataset.kind = entry.kind; button.dataset.url = entry.update_url; button.dataset.name = entry.name; button.dataset.params = JSON.stringify(entry.params || {});
                    button.className = 'inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 text-primary hover:bg-primary hover:text-white';
                    const glyph = document.createElement('span'); glyph.className = 'material-symbols-outlined text-[18px]'; glyph.setAttribute('aria-hidden', 'true'); glyph.textContent = 'edit'; button.append(glyph);
                    return button;
                };
                const action = (url, method, label, icon, guard, danger) => {
                    const form = document.createElement('form'); form.method = 'POST'; form.action = url; form.dataset.guard = guard;
                    const hidden = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input); };
                    hidden('_token', token); hidden('_method', method);
                    const button = document.createElement('button'); button.title = label; button.setAttribute('aria-label', label);
                    button.className = 'inline-flex h-8 w-8 items-center justify-center rounded border ' + (danger ? 'border-error/50 text-error hover:bg-error hover:text-white' : 'border-outline-variant/60 text-primary hover:bg-primary hover:text-white');
                    const glyph = document.createElement('span'); glyph.className = 'material-symbols-outlined text-[18px]'; glyph.setAttribute('aria-hidden', 'true'); glyph.textContent = icon; button.append(glyph); form.append(button);
                    return form;
                };
                const wrap = document.createElement('div'); wrap.className = 'flex items-center justify-end gap-1.5';
                wrap.append(open, editButton(), action(entry.delete_url, 'DELETE', 'Delete from the history', 'delete', 'Delete ' + entry.name + ' from the history', true));
                cell('', 'px-4 py-2.5 text-right').append(wrap);
                if (rows.querySelector('td[colspan]')) rows.innerHTML = '';
                rows.prepend(row);
                status.textContent = 'Saved to history';
                notice?.set('Saved to history.', 'done');
            } catch (error) { status.textContent = 'Could not save to history. Try again'; notice?.set('Could not save to history. Try again.', 'error'); }
            saveButton.disabled = false;
        });
        const refresh = async () => {
            if (modal.classList.contains('hidden')) return;
            if (controller) controller.abort();
            controller = new AbortController();
            const target = urlWithFields();
            printLink.setAttribute('href', target);
            status.textContent = 'Updating preview...';
            try {
                const response = await fetch(target, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                const html = await response.text();
                frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html;
                status.textContent = response.ok ? 'Updates as you type' : 'Preview unavailable (HTTP ' + response.status + ')';
            } catch (error) { if (error.name !== 'AbortError') status.textContent = 'Preview unavailable. Reopen the report to retry'; }
        };
        // Bank Reconciliation only: the records of the cash downloaded to the account.
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
        const receiptsUrl = @json(route('cash.fund.index'));
        const receiptsStore = @json(route('cash.fund.store'));
        const receiptsBase = @json(url('/cash/fund-receipts'));
        const fundRows = field('cash-fund-rows');
        const fundForm = field('cash-fund-form');
        const fundError = field('cash-fund-error');
        let fundList = [];
        const fundNames = @json(\App\Models\CashFundReceipt::FUNDS);
        const showFundOther = () => field('cash-fund-other-wrap').classList.toggle('hidden', field('cash-fund-source').value !== 'Others');
        const resetFund = () => {
            fundForm.reset(); field('cash-fund-id').value = ''; showFundOther();
            field('cash-fund-save').textContent = 'Add'; field('cash-fund-reset').classList.add('hidden'); fundError.classList.add('hidden');
        };
        const renderFund = () => {
            fundRows.innerHTML = '';
            if (!fundList.length) {
                fundRows.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-on-surface-variant">No cash downloaded in this period yet.</td></tr>';
                return;
            }
            fundList.forEach((item) => {
                const row = document.createElement('tr');
                [item.date_label, item.kind_label + (item.source_of_fund ? ' · ' + item.source_of_fund : ''), item.reference || '-', item.amount_label].forEach((text, index) => {
                    const cell = document.createElement('td');
                    cell.className = index === 3 ? 'px-2 py-2 text-right font-semibold tabular-nums' : (index === 0 ? 'px-3 py-2' : 'px-2 py-2');
                    cell.textContent = text; row.append(cell);
                });
                const actions = document.createElement('td');
                actions.className = 'whitespace-nowrap px-3 py-2 text-right';
                const edit = document.createElement('button'); edit.type = 'button'; edit.textContent = 'Edit'; edit.className = 'mr-2 font-semibold text-primary hover:underline';
                edit.addEventListener('click', () => {
                    field('cash-fund-id').value = item.id; field('cash-fund-date').value = item.received_at; field('cash-fund-kind').value = item.kind; const known = fundNames.includes(item.source_of_fund); field('cash-fund-source').value = known || !item.source_of_fund ? item.source_of_fund : 'Others'; field('cash-fund-other').value = known ? '' : item.source_of_fund; showFundOther();
                    field('cash-fund-amount').value = item.amount; field('cash-fund-reference').value = item.reference; field('cash-fund-note').value = item.note;
                    field('cash-fund-save').textContent = 'Save changes'; field('cash-fund-reset').classList.remove('hidden');
                });
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Delete'; remove.className = 'font-semibold text-error hover:underline';
                remove.addEventListener('click', async () => {
                    if (!confirm('Delete this record of cash downloaded?')) return;
                    await fundRequest('DELETE', receiptsBase + '/' + item.id, {});
                    resetFund(); loadFund(); refresh();
                });
                actions.append(edit, remove); row.append(actions); fundRows.append(row);
            });
            // The total of the cash downloaded in the period, and the amount of each fund when there is more than one.
            const totals = {}; fundList.forEach((item) => { const name = item.source_of_fund || 'Not specified'; totals[name] = (totals[name] || 0) + Number(item.amount); });
            const format = (value) => value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const addTotal = (label, value, strong) => { const row = document.createElement('tr'); row.className = 'border-t border-outline-variant/40' + (strong ? ' font-bold' : ' text-on-surface-variant'); row.innerHTML = '<td class="px-3 py-2" colspan="3"></td><td class="px-2 py-2 text-right tabular-nums"></td><td></td>'; row.children[0].textContent = label; row.children[0].classList.add('text-right'); row.children[1].textContent = format(value); fundRows.append(row); };
            if (Object.keys(totals).length > 1) Object.entries(totals).forEach(([name, value]) => addTotal(name, value, false));
            addTotal('Total', Object.values(totals).reduce((sum, value) => sum + value, 0), true);
        };
        const loadFund = async () => {
            const query = new URLSearchParams({ school_id: field('cash-report-school').value, from: field('cash-report-from').value, to: field('cash-report-to').value });
            try {
                const response = await fetch(receiptsUrl + '?' + query, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                fundList = response.ok ? await response.json() : [];
            } catch (error) { fundList = []; }
            renderFund();
        };
        const fundRequest = (method, url, data) => fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ ...data, _method: method }) });
        fundForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            fundError.classList.add('hidden');
            const id = field('cash-fund-id').value;
            const response = await fundRequest(id ? 'PUT' : 'POST', id ? receiptsBase + '/' + id : receiptsStore, {
                school_id: field('cash-report-school').value, received_at: field('cash-fund-date').value, kind: field('cash-fund-kind').value, source_of_fund: field('cash-fund-source').value === 'Others' ? field('cash-fund-other').value.trim() : field('cash-fund-source').value,
                amount: field('cash-fund-amount').value, reference: field('cash-fund-reference').value, note: field('cash-fund-note').value,
            });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                fundError.textContent = Object.values(body.errors || {})[0]?.[0] || body.message || 'It could not be saved.';
                fundError.classList.remove('hidden');
                return;
            }
            resetFund(); loadFund(); refresh();
        });
        field('cash-fund-reset').addEventListener('click', resetFund);
        field('cash-fund-source').addEventListener('change', showFundOther);
        field('cash-report-source').addEventListener('change', () => { field('cash-report-source-other-wrap').classList.toggle('hidden', field('cash-report-source').value !== 'Others'); schedule(); });
        field('cash-report-source-other').addEventListener('input', schedule);
        const toggleBank = (on) => {
            modal.querySelectorAll('[data-bank-only]').forEach((box) => box.classList.toggle('hidden', !on));
            if (on) { resetFund(); if (!field('cash-fund-date').value) field('cash-fund-date').value = field('cash-report-from').value; loadFund(); }
        };
        ['cash-report-school', 'cash-report-from', 'cash-report-to'].forEach((id) => field(id).addEventListener('change', () => { if (!modal.querySelector('[data-bank-only]').classList.contains('hidden')) loadFund(); }));
        const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
        document.querySelectorAll('[data-cash-report-open]').forEach((button) => button.addEventListener('click', () => {
            baseUrl = button.dataset.url;
            slotBox.dataset.slots = button.dataset.slots;
            field('cash-report-no').placeholder = button.dataset.reportNo || '';
            toggleBank(button.dataset.bank === '1');
            modal.querySelectorAll('[data-hide-bank]').forEach((box) => box.classList.toggle('hidden', button.dataset.bank === '1'));
            registerOn = button.dataset.register === '1';
            document.getElementById('cash-report-save').classList.remove('hidden');
            editing = null; saveButton.textContent = 'Save to history';
            formKey = button.dataset.form || 'o';
            modal.querySelectorAll('#cash-report-header > [data-o], #cash-report-header > [data-r], #cash-report-header > [data-c], #cash-report-header > [data-b]').forEach((item) => {
                const place = item.dataset[formKey];
                item.classList.toggle('hidden', !place);
                item.style.order = place || 0;
            });
            field('cash-report-source-other-wrap').classList.toggle('hidden', field('cash-report-source').value !== 'Others');
            buildSlots();
            fillDefaults();
            document.getElementById('cash-report-title').textContent = button.dataset.title;
            modal.classList.remove('hidden'); modal.classList.add('flex');
            refresh();
        }));
        // Edit on a kept report: the window opens with the values it was saved with; Update replaces the kept copy.
        const formOfKind = { roci: 'o', checks: 'c', register: 'r', bank: 'b' };
        const fieldOfParam = { school_id: 'cash-report-school', from: 'cash-report-from', to: 'cash-report-to', report_no: 'cash-report-no', sheet_no: 'cash-sheet-no', fund_cluster: 'cash-report-cluster', bank_name: 'cash-report-bank-name', bank_account: 'cash-report-bank-account', remarks: 'cash-report-remarks', entity_name: 'cash-report-entity', station: 'cash-report-station', officer_name: 'cash-report-officer-name', officer_position: 'cash-report-officer-position', district: 'cash-report-district', municipality: 'cash-report-municipality', registered_no: 'cash-report-registered' };
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-history-edit]');
            if (!button) return;
            const opener = document.querySelector('[data-cash-report-open][data-form="' + formOfKind[button.dataset.kind] + '"]');
            if (!opener) return;
            let params = {}; try { params = JSON.parse(button.dataset.params || '{}'); } catch (error) { params = {}; }
            opener.click();
            Object.entries(fieldOfParam).forEach(([name, id]) => { if (params[name] !== undefined && field(id)) { field(id).value = params[name]; field(id).dataset.typed = '1'; field(id).dispatchEvent(new Event('change', { bubbles: true })); } });
            const fund = params.source_of_fund || '';
            const known = [...field('cash-report-source').options].some((option) => option.value === fund);
            field('cash-report-source').value = known ? fund : (fund ? 'Others' : '');
            field('cash-report-source-other').value = known ? '' : fund;
            field('cash-report-source-other-wrap').classList.toggle('hidden', field('cash-report-source').value !== 'Others');
            ['officer_id', 'received_by_id'].forEach((name) => { const select = slotBox.querySelector('select[data-slot="' + name + '"]'); if (select && params[name] !== undefined) { select.value = params[name]; select.dispatchEvent(new Event('change', { bubbles: true })); } });
            editing = { url: button.dataset.url, name: button.dataset.name };
            saveButton.textContent = 'Update history';
            document.getElementById('cash-report-title').textContent = 'Edit · ' + button.dataset.name;
            schedule();
        });
        field('cash-report-school').addEventListener('change', () => { buildSlots(); fillDefaults(); schedule(); });
        ['cash-report-entity', 'cash-report-district', 'cash-report-municipality', 'cash-report-station', 'cash-report-registered', 'cash-report-officer-name', 'cash-report-officer-position', 'cash-report-from', 'cash-report-to', 'cash-report-no', 'cash-sheet-no', 'cash-report-cluster', 'cash-report-bank-name', 'cash-report-bank-account', 'cash-report-remarks'].forEach((id) => field(id).addEventListener('input', schedule));
        document.getElementById('cash-report-close').addEventListener('click', close);
        document.getElementById('cash-report-cancel').addEventListener('click', close);
        modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    })();
</script>
