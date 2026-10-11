{{-- One "other line" of an SOB Item form (for example 35% Labor): a name, a unit label and an amount, all typed by the user. --}}
@php
    $cls = $box ?? 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
    $val = fn (string $key, $fallback = '') => old("extras.$i.$key", $fallback);
@endphp
<div class="contents" data-row>
    @if($line)<input type="hidden" name="extras[{{ $i }}][id]" value="{{ $line->id }}">@endif
    <label class="text-xs font-semibold text-on-surface-variant md:col-span-3">Other line (e.g. 35% Labor)<input name="extras[{{ $i }}][particulars]" maxlength="255" value="{{ $val('particulars', $line?->particulars) }}" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant">Unit<input name="extras[{{ $i }}][unit]" maxlength="50" value="{{ $val('unit', $line?->unit) }}" placeholder="Labor" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Amount<input name="extras[{{ $i }}][amount]" type="text" inputmode="decimal" value="{{ $val('amount', $line ? rtrim(rtrim(number_format((float) $line->amount, 2, '.', ''), '0'), '.') : '') }}" class="{{ $cls }}"></label>
    <button type="button" data-remove-row class="justify-self-start rounded px-2 py-1 text-xs font-semibold text-error hover:bg-surface-low md:col-span-6">× Remove other line</button>
</div>
