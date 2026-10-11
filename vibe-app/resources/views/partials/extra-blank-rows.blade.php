{{-- The one "Extra Blank Rows" control of every form that can add empty rows to a printed item table (docs/specs.md, Screen design standards).
     $max: the most rows that document's printed page holds. Optional: $value (default 0), $class (extra classes on the label), $wrapperAttributes (attribute names for the label, such as the document form's show/hide hooks), $meta (adds data-meta to the box). --}}
<label @foreach(($wrapperAttributes ?? []) as $attribute){{ $attribute }} @endforeach class="block text-xs font-semibold text-on-surface-variant {{ $class ?? '' }}">Extra Blank Rows
    <input @if($meta ?? false) data-meta @endif type="number" min="0" max="{{ $max }}" name="extra_blank_rows" value="{{ $value ?? 0 }}" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">
    <span class="mt-1 block font-normal text-on-surface-variant">Empty bordered rows added at the end of the printed table (up to {{ $max }}).</span>
</label>
