{{-- The Reports tab of Cash: the period, the three cash reports and the fund received (opening balance, NCA, cash advance). Design standard of docs/specs.md section 7. --}}
@php
    $box = 'mt-1.5 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal normal-case tracking-normal text-on-surface shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant';
    $head = fn (int $number, string $title, string $hint) => '<div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-white">'.$number.'</span><div><h2 class="text-sm font-bold text-primary">'.e($title).'</h2><p class="text-xs text-on-surface-variant">'.e($hint).'</p></div></div>';
    $reportLinks = [
        ['cash.report.roci', 'Report of Checks Issued', 'Appendix 35: every check of the period, cancelled checks included.', 'fact_check'],
        ['cash.report.checks', 'Checks and Advices to Debit Account', 'Disbursement record with the check and ADA columns and the running balance.', 'account_balance'],
        ['cash.report.register', 'Cash Disbursement Register', 'Working fund, payments and the breakdown per expense account.', 'menu_book'],
    ];
@endphp
<div class="space-y-5 p-5">
    @if(! $reportSchool)
        <p class="rounded-lg border border-outline-variant/40 bg-white px-4 py-6 text-center text-sm text-on-surface-variant">No school to report on.</p>
    @else
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        {!! $head(1, 'Period and reports', 'Choose the school and the period, then open a report on its official form.') !!}
        <form method="GET" action="{{ route('cash') }}" id="cash-report-period" class="grid gap-4 p-5 md:grid-cols-4">
            <input type="hidden" name="tab" value="reports">
            <label class="{{ $label }}">School
                <select name="school_id" onchange="this.form.submit()" class="{{ $box }}">@foreach($reportSchools as $option)<option value="{{ $option->id }}" @selected($option->id === $reportSchool->id)>{{ $option->name }}</option>@endforeach</select>
            </label>
            <label class="{{ $label }}">From<input type="date" name="from" value="{{ $reportFrom->toDateString() }}" class="{{ $box }}"></label>
            <label class="{{ $label }}">To<input type="date" name="to" value="{{ $reportTo->toDateString() }}" class="{{ $box }}"></label>
            <div class="flex items-end"><button class="rounded-lg border border-primary bg-white px-4 py-2 text-xs font-bold text-primary hover:bg-primary hover:text-white">Apply period</button></div>
        </form>
        <div class="grid gap-4 border-t border-outline-variant/40 p-5 md:grid-cols-2">
            <label class="{{ $label }}">Report No. <span class="font-normal normal-case tracking-normal">(printed on the form)</span><input id="cash-report-no" maxlength="50" class="{{ $box }}"></label>
            <label class="{{ $label }}">Sheet No. <span class="font-normal normal-case tracking-normal">(printed on the form)</span><input id="cash-sheet-no" maxlength="50" placeholder="1 of 1" class="{{ $box }}"></label>
        </div>
        <div class="grid gap-4 border-t border-outline-variant/40 p-5 md:grid-cols-3">
            @foreach($reportLinks as [$routeName, $title, $hint, $icon])
                <a href="{{ route($routeName, ['school_id' => $reportSchool->id, 'from' => $reportFrom->toDateString(), 'to' => $reportTo->toDateString()]) }}" target="_blank" rel="noopener" data-cash-report-link class="group flex items-start gap-3 rounded-xl border border-outline-variant/50 border-l-4 border-l-primary bg-white p-4 shadow-sm transition hover:bg-[#eef4fa]">
                    <span class="material-symbols-outlined text-[24px] text-primary" aria-hidden="true">{{ $icon }}</span>
                    <span><span class="block text-sm font-bold text-primary">{{ $title }}</span><span class="mt-1 block text-xs text-on-surface-variant">{{ $hint }}</span></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        {!! $head(2, 'Fund received', 'The opening balance, NCA or cash advance the reports start from. Type one for each quarter.') !!}
        <form method="POST" action="{{ route('cash.fund.store') }}" class="grid gap-4 p-5 md:grid-cols-6">@csrf
            <input type="hidden" name="school_id" value="{{ $reportSchool->id }}">
            <label class="{{ $label }}">Date<input type="date" name="received_at" required value="{{ old('received_at', $reportFrom->toDateString()) }}" class="{{ $box }}"></label>
            <label class="{{ $label }}">Kind<select name="kind" class="{{ $box }}">@foreach(\App\Models\CashFundReceipt::KINDS as $key => $kindLabel)<option value="{{ $key }}">{{ $kindLabel }}</option>@endforeach</select></label>
            <label class="{{ $label }}">Amount<input type="number" step="0.01" min="0.01" name="amount" required class="{{ $box }} text-right tabular-nums"></label>
            <label class="{{ $label }}">Fund Cluster<input name="fund_cluster" maxlength="100" placeholder="General Fund" class="{{ $box }}"></label>
            <label class="{{ $label }}">Bank Account No.<input name="bank_account_number" maxlength="60" value="{{ $reportSchool->bank_account_number }}" class="{{ $box }}"></label>
            <label class="{{ $label }}">Reference<input name="reference" maxlength="100" class="{{ $box }}"></label>
            <label class="{{ $label }} md:col-span-5">Note<input name="note" maxlength="255" class="{{ $box }}"></label>
            <div class="flex items-end justify-end"><button class="rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-primary-container">Add</button></div>
        </form>
        <div class="overflow-x-auto border-t border-outline-variant/40">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-surface-low text-[11px] uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-2.5">Date</th><th class="px-3 py-2.5">Kind</th><th class="px-3 py-2.5">Fund Cluster</th><th class="px-3 py-2.5">Reference</th><th class="px-3 py-2.5 text-right">Amount</th><th class="px-5 py-2.5 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-outline-variant/30">
                @forelse($fundReceipts as $receipt)
                    <tr class="even:bg-surface-low/40">
                        <td class="px-5 py-2.5 font-semibold">{{ $receipt->received_at->format('M d, Y') }}</td>
                        <td class="px-3 py-2.5">{{ $receipt->kindLabel() }}</td>
                        <td class="px-3 py-2.5">{{ $receipt->fund_cluster }}</td>
                        <td class="px-3 py-2.5">{{ $receipt->reference ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $peso($receipt->amount) }}</td>
                        <td class="px-5 py-2.5 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <button type="button" data-edit-receipt="{{ $receipt->id }}" title="Edit" aria-label="Edit fund received" class="grid h-8 w-8 place-items-center rounded-lg border border-outline-variant/60 hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]">edit</span></button>
                                <form method="POST" action="{{ route('cash.fund.destroy', $receipt) }}" onsubmit="return confirm('Delete this fund received?')" class="inline">@csrf @method('DELETE')<button title="Delete" aria-label="Delete fund received" class="grid h-8 w-8 place-items-center rounded-lg border border-error/40 text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
                            </div>
                        </td>
                    </tr>
                    <tr id="edit-receipt-{{ $receipt->id }}" class="hidden bg-[#eef4fa]">
                        <td colspan="6" class="px-5 py-4">
                            <form method="POST" action="{{ route('cash.fund.update', $receipt) }}" class="grid gap-4 md:grid-cols-6">@csrf @method('PUT')
                                <input type="hidden" name="school_id" value="{{ $receipt->school_id }}">
                                <label class="{{ $label }}">Date<input type="date" name="received_at" required value="{{ $receipt->received_at->toDateString() }}" class="{{ $box }}"></label>
                                <label class="{{ $label }}">Kind<select name="kind" class="{{ $box }}">@foreach(\App\Models\CashFundReceipt::KINDS as $key => $kindLabel)<option value="{{ $key }}" @selected($receipt->kind === $key)>{{ $kindLabel }}</option>@endforeach</select></label>
                                <label class="{{ $label }}">Amount<input type="number" step="0.01" min="0.01" name="amount" required value="{{ $receipt->amount }}" class="{{ $box }} text-right tabular-nums"></label>
                                <label class="{{ $label }}">Fund Cluster<input name="fund_cluster" maxlength="100" value="{{ $receipt->fund_cluster }}" class="{{ $box }}"></label>
                                <label class="{{ $label }}">Bank Account No.<input name="bank_account_number" maxlength="60" value="{{ $receipt->bank_account_number }}" class="{{ $box }}"></label>
                                <label class="{{ $label }}">Reference<input name="reference" maxlength="100" value="{{ $receipt->reference }}" class="{{ $box }}"></label>
                                <label class="{{ $label }} md:col-span-4">Note<input name="note" maxlength="255" value="{{ $receipt->note }}" class="{{ $box }}"></label>
                                <div class="flex items-end justify-end gap-2 md:col-span-2"><button type="button" data-cancel-receipt="{{ $receipt->id }}" class="rounded-lg border border-outline-variant bg-white px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save changes</button></div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-xs text-on-surface-variant">No fund received yet. Add the opening balance or the NCA so the reports can show the balance.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
<script>
    document.querySelectorAll('[data-edit-receipt]').forEach((button) => button.addEventListener('click', () => document.getElementById('edit-receipt-' + button.dataset.editReceipt)?.classList.toggle('hidden')));
    document.querySelectorAll('[data-cancel-receipt]').forEach((button) => button.addEventListener('click', () => document.getElementById('edit-receipt-' + button.dataset.cancelReceipt)?.classList.add('hidden')));
    // The Report No. and Sheet No. typed here travel with the report link.
    document.querySelectorAll('[data-cash-report-link]').forEach((link) => link.addEventListener('click', () => {
        const url = new URL(link.getAttribute('href'), window.location.origin);
        const reportNo = document.getElementById('cash-report-no')?.value.trim();
        const sheetNo = document.getElementById('cash-sheet-no')?.value.trim();
        reportNo ? url.searchParams.set('report_no', reportNo) : url.searchParams.delete('report_no');
        sheetNo ? url.searchParams.set('sheet_no', sheetNo) : url.searchParams.delete('sheet_no');
        link.setAttribute('href', url.pathname + url.search);
    }));
</script>
