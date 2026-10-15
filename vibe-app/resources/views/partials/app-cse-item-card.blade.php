{{-- One line of the APP-CSE page ($rowColumns: the grid columns shared with the category heading): the item, its price, its 12 months and its totals on a single row, editable while the plan is a draft (a line from the SOB is changed in the SOB). --}}
    @php
        $itemEditable = $editable && ! $item->from_sob;
        $priceEditable = $itemEditable && ($item->isCustom() || $item->catalogItem?->price === null);
    @endphp
    <div class="border-b border-outline-variant/10 last:border-0">
        <div class="px-4 py-2">
            @if($itemEditable)
            <form method="POST" action="{{ route('planning.app-cse.items.update', $item) }}">@csrf @method('PUT')
            @endif
                <div class="grid items-center gap-x-1 text-sm" style="{{ $rowColumns }}">
                    <div class="min-w-0 pr-2">
                        @if($item->isCustom() && $itemEditable)
                            <div class="grid gap-1"><input name="category" value="{{ $item->category }}" maxlength="150" aria-label="Category" class="{{ $small }} !text-left"><input name="description" value="{{ $item->description }}" maxlength="500" aria-label="Description" class="{{ $small }} !text-left"><input name="unit" value="{{ $item->unit }}" maxlength="50" aria-label="Unit" class="{{ $small }} !text-left"></div>
                        @else
                            <p class="font-semibold leading-snug">@if(! $item->isCustom())<span class="font-mono text-xs font-normal text-on-surface-variant">{{ $item->catalogItem?->code }}</span> @endif{{ $item->description }}</p>
                            <p class="text-xs text-on-surface-variant">{{ $item->unit }}@if($item->from_sob) · <span class="font-semibold text-primary">From the SOB: change it in the SOB</span>@endif</p>
                        @endif
                    </div>
                    <div class="text-right">@if($priceEditable)<input type="number" name="unit_price" step="0.01" min="0" value="{{ $item->unit_price }}" aria-label="Price" class="{{ $small }}">@else<span class="text-sm font-semibold tabular-nums">{{ $peso($item->unit_price) }}</span>@endif</div>
                    @foreach($monthNames as $index => $name)
                        @php $monthQty = $item->{'m'.($index + 1)}; @endphp
                        @if($itemEditable)<input type="number" name="m{{ $index + 1 }}" step="0.01" min="0" value="{{ $qty($monthQty) }}" aria-label="{{ $name }}" class="{{ $small }}">@else<span class="rounded bg-surface-low/60 py-1 text-center text-xs tabular-nums {{ (float) $monthQty > 0 ? 'font-bold text-primary' : 'text-on-surface-variant/40' }}">{{ (float) $monthQty > 0 ? $qty($monthQty) : '–' }}</span>@endif
                    @endforeach
                    <span class="text-right text-sm font-semibold tabular-nums">{{ $qty($item->totalQuantity()) }}</span>
                    <span class="text-right text-sm font-bold tabular-nums text-primary">{{ $peso($item->totalAmount()) }}</span>
                </div>
            @if($itemEditable)
                <div class="mt-2 flex items-center gap-3"><button class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-container">Save item</button></div>
            </form>
            <form method="POST" action="{{ route('planning.app-cse.items.destroy', $item) }}" class="mt-1" onsubmit="return confirm('Remove this item from the APP-CSE?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-error hover:underline">Remove</button></form>
            @endif
        </div>
    </div>
