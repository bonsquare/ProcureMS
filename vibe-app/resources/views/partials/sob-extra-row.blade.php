{{-- One "other line" of an SOB Item form (for example 35% Labor): a name, a unit label and an amount, all typed by the user. --}}
@php
    $cls = $box ?? 'mt-1.5 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal normal-case tracking-normal text-on-surface shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';
    $val = fn (string $key, $fallback = '') => old("extras.$i.$key", $fallback);
@endphp
<div class="contents" data-row>
    @if($line)<input type="hidden" name="extras[{{ $i }}][id]" value="{{ $line->id }}">@endif
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant md:col-span-3">Other line (e.g. 35% Labor)<input name="extras[{{ $i }}][particulars]" maxlength="255" value="{{ $val('particulars', $line?->particulars) }}" class="{{ $cls }}"></label>
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Unit<input name="extras[{{ $i }}][unit]" maxlength="50" value="{{ $val('unit', $line?->unit) }}" placeholder="Labor" class="{{ $cls }}"></label>
    <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant md:col-span-2">Amount<input name="extras[{{ $i }}][amount]" type="text" inputmode="decimal" value="{{ $val('amount', $line ? rtrim(rtrim(number_format((float) $line->amount, 2, '.', ''), '0'), '.') : '') }}" class="{{ $cls }}"></label>
    <button type="button" data-remove-row class="justify-self-start rounded px-2 py-1 text-xs font-semibold text-error hover:bg-surface-low md:col-span-6">× Remove other line</button>
</div>
