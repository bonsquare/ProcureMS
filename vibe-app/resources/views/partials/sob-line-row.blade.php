{{-- One particulars row of an SOB Item form. $i is the row number (or __INDEX__ in the template for a row added by the button); $line is the saved row, if any. --}}
@php
    $cls = $box ?? 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
    $val = fn (string $key, $fallback = '') => old("lines.$i.$key", $fallback);
    $num = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<div class="contents" data-row>
    @if($line)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line->id }}">@endif
    <label class="text-xs font-semibold text-on-surface-variant sob-f-cat">Category (where it belongs in the APP)<select name="lines[{{ $i }}][category]" class="{{ $cls }}">@php $currentCategory = $val('category', $line ? $line->category : \App\Support\AppCategories::ALL[0]); @endphp<option value="" @selected(blank($currentCategory)) title="Not goods (for example a travel budget): this line is not put in the APP">-</option>@foreach(\App\Support\AppCategories::ALL as $category)<option value="{{ $category }}" @selected($category === \App\Support\AppCategories::of($currentCategory) && filled($currentCategory))>{{ $category }}</option>@endforeach</select></label>
    <label class="text-xs font-semibold text-on-surface-variant sob-f-part">Particulars<input name="lines[{{ $i }}][particulars]" required maxlength="255" value="{{ $val('particulars', $line?->particulars) }}" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant sob-f-num">Frequency<input name="lines[{{ $i }}][frequency]" type="text" inputmode="decimal" value="{{ $val('frequency', $line ? $num($line->frequency) : 1) }}" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant sob-f-num">Quantity<input name="lines[{{ $i }}][quantity]" type="text" inputmode="decimal" required value="{{ $val('quantity', $line ? $num($line->quantity) : '') }}" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant sob-f-unit">Unit<input name="lines[{{ $i }}][unit]" required maxlength="50" value="{{ $val('unit', $line?->unit) }}" placeholder="pcs, ream, month" class="{{ $cls }}"></label>
    <label class="text-xs font-semibold text-on-surface-variant sob-f-cost">Unit cost<input name="lines[{{ $i }}][unit_cost]" type="text" inputmode="decimal" required value="{{ $val('unit_cost', $line ? $num($line->unit_cost) : '') }}" class="{{ $cls }}"></label>
    <button type="button" data-remove-row class="justify-self-start rounded px-2 py-1 text-xs font-semibold text-error hover:bg-surface-low sob-f-remove">× Remove particulars</button>
</div>
