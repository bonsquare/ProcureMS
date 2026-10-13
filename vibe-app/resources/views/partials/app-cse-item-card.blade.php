{{-- One line of the APP-CSE page: its months and price, editable while the plan is a draft (a line from the SOB is changed in the SOB). --}}
    @php $itemEditable = $editable && ! $item->from_sob; $priceEditable = $itemEditable && ($item->isCustom() || $item->catalogItem?->price === null); @endphp
    <div class="border-b border-outline-variant/10 px-4 py-3 last:border-0">
        @if($itemEditable)
        <form method="POST" action="{{ route('planning.app-cse.items.update', $item) }}">@csrf @method('PUT')
        @endif
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1 text-sm">
                    @if($item->isCustom() && $itemEditable)
                        <div class="grid gap-2 md:grid-cols-4"><label class="text-xs font-semibold text-on-surface-variant">Category<input name="category" value="{{ $item->category }}" maxlength="150" class="{{ $box }}"></label><label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Description<input name="description" value="{{ $item->description }}" maxlength="500" class="{{ $box }}"></label><label class="text-xs font-semibold text-on-surface-variant">Unit<input name="unit" value="{{ $item->unit }}" maxlength="50" class="{{ $box }}"></label></div>
                    @else
                        <p class="font-semibold">@if(! $item->isCustom())<span class="font-mono text-xs font-normal text-on-surface-variant">{{ $item->catalogItem?->code }}</span> @endif{{ $item->description }}</p>
                        <p class="text-xs text-on-surface-variant">{{ $item->category }} · {{ $item->unit }}@if($item->from_sob) · <span class="font-semibold text-primary">From the SOB: change it in the SOB</span>@endif</p>
                    @endif
                </div>
                <div class="w-32 text-xs font-semibold text-on-surface-variant">Price @if($priceEditable)<input type="number" name="unit_price" step="0.01" min="0" value="{{ $item->unit_price }}" class="{{ $small }}">@else<span class="mt-1 block text-right text-sm font-normal text-on-surface">{{ $peso($item->unit_price) }}</span>@endif</div>
            </div>
            <div class="mt-2 grid grid-cols-4 gap-2 md:grid-cols-12">
                @foreach($monthNames as $index => $name)
                    <label class="text-[10px] font-semibold uppercase text-on-surface-variant">{{ $name }}@if($itemEditable)<input type="number" name="m{{ $index + 1 }}" step="0.01" min="0" value="{{ $qty($item->{'m'.($index + 1)}) }}" class="{{ $small }}">@else<span class="mt-1 block text-right text-xs font-normal text-on-surface">{{ $qty($item->{'m'.($index + 1)}) }}</span>@endif</label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">Q1 {{ $qty($item->quarterQuantity(1)) }} · Q2 {{ $qty($item->quarterQuantity(2)) }} · Q3 {{ $qty($item->quarterQuantity(3)) }} · Q4 {{ $qty($item->quarterQuantity(4)) }} · Total quantity <strong>{{ $qty($item->totalQuantity()) }}</strong> · Total amount <strong>{{ $peso($item->totalAmount()) }}</strong></p>
        @if($itemEditable)
            <div class="mt-2"><button class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-container">Save item</button></div>
        </form>
        <form method="POST" action="{{ route('planning.app-cse.items.destroy', $item) }}" class="mt-1" onsubmit="return confirm('Remove this item from the APP-CSE?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-error hover:underline">Remove</button></form>
        @endif
    </div>
