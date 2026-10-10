{{-- One activity under a KRA. Expects $i (index or __INDEX__), $a (array of values), $accounts, $fundOptions, $field, $label. --}}
@php
    $a = $a ?? [];
    $persons = $a['responsible_persons'] ?? [];
    $remarks = $a['remarks_list'] ?? [];
    $fund = $a['source_of_fund'] ?? '';
    $knownFund = in_array($fund, $fundOptions, true);
    $name = fn ($key) => "activities[{$i}][{$key}]";
@endphp
<div data-activity class="rounded-lg border border-outline-variant/40 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-outline-variant/30 bg-surface-low/60 px-4 py-2.5">
        <p class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Activity <span data-number></span></p>
        <button type="button" data-remove-activity class="flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[16px]">delete</span>Remove</button>
    </div>
    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
        <input type="hidden" name="{{ $name('id') }}" value="{{ $a['id'] ?? '' }}">
        <label class="{{ $label }} md:col-span-2">Activity <span class="text-error">*</span><textarea name="{{ $name('activity') }}" rows="2" required class="{{ $field }}">{{ $a['activity'] ?? '' }}</textarea></label>
        <label class="{{ $label }}">Physical Target<input type="number" min="0" name="{{ $name('physical_target') }}" value="{{ $a['physical_target'] ?? 1 }}" class="{{ $field }}"></label>
        <label class="{{ $label }}">Specific Timeline<input name="{{ $name('timeline') }}" value="{{ $a['timeline'] ?? '' }}" placeholder="e.g. January - December" class="{{ $field }}"></label>

        <fieldset class="rounded border border-outline-variant/40 p-3 md:col-span-2">
            <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Financial Target (₱ per quarter) · <span data-total class="tabular-nums">₱0.00</span></legend>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach([1, 2, 3, 4] as $q)
                    <label class="{{ $label }}">{{ ['1st', '2nd', '3rd', '4th'][$q - 1] }} Quarter<input type="number" step="0.01" min="0" data-quarter name="{{ $name("q{$q}_amount") }}" value="{{ (float) ($a["q{$q}_amount"] ?? 0) ?: '' }}" class="{{ $field }}"></label>
                @endforeach
            </div>
        </fieldset>

        <div class="{{ $label }}">Source of Fund
            <select data-fund-select class="{{ $field }}">
                <option value="">Select source of fund</option>
                @foreach($fundOptions as $option)<option @selected($fund === $option || (!$knownFund && $fund && $option === 'Others'))>{{ $option }}</option>@endforeach
            </select>
            <input data-fund-other value="{{ !$knownFund ? $fund : '' }}" placeholder="Specify source of fund" class="{{ $field }} {{ !$knownFund && $fund ? '' : 'hidden' }}">
            <input type="hidden" data-fund-value name="{{ $name('source_of_fund') }}" value="{{ $fund }}">
        </div>
        <label class="{{ $label }}">Account Code (optional)
            <select name="{{ $name('chart_of_account_id') }}" class="{{ $field }}">
                <option value="">Select account code</option>
                @foreach($accounts->groupBy('category') as $category => $group)
                    <optgroup label="{{ $category }}">@foreach($group as $account)<option value="{{ $account->id }}" @selected((int) ($a['chart_of_account_id'] ?? 0) === $account->id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach</optgroup>
                @endforeach
            </select>
        </label>

        <fieldset class="rounded border border-outline-variant/40 p-3">
            <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Responsible Persons</legend>
            <div data-list="responsible_persons" data-placeholder="Name or position" class="space-y-2">
                @foreach($persons ?: [''] as $person)<div class="flex items-center gap-2"><input name="{{ $name('responsible_persons') }}[]" value="{{ $person }}" placeholder="Name or position" class="min-w-0 flex-1 {{ $field }} !mt-0"><button type="button" data-remove-row aria-label="Remove" class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]">close</span></button></div>@endforeach
            </div>
            <button type="button" data-add-row="responsible_persons" class="mt-3 text-xs font-semibold text-primary hover:underline">+ Add person</button>
        </fieldset>
        <fieldset class="rounded border border-outline-variant/40 p-3">
            <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Remarks</legend>
            <div data-list="remarks_list" data-placeholder="Remark" class="space-y-2">
                @foreach($remarks ?: [''] as $remark)<div class="flex items-center gap-2"><input name="{{ $name('remarks_list') }}[]" value="{{ $remark }}" placeholder="Remark" class="min-w-0 flex-1 {{ $field }} !mt-0"><button type="button" data-remove-row aria-label="Remove" class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]">close</span></button></div>@endforeach
            </div>
            <button type="button" data-add-row="remarks_list" class="mt-3 text-xs font-semibold text-primary hover:underline">+ Add remark</button>
        </fieldset>
    </div>
</div>
