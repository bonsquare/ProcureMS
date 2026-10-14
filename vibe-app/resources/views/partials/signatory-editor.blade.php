{{-- One signatory editor for every planning document (data entry only; the printed form keeps its own signature slots).
     First a summary of who signs, then "Edit" opens one box per signature with: the title printed above the signature, the full name and the position.
     $slots: list of ['key', 'legend', 'role', 'name', 'position', 'status' => saved|suggested|empty, 'namePlaceholder'?, 'positionHint'?, 'picker'?]
     $horizontal: show the signatures side by side (three columns) instead of one under another
     $editable: whether the user may change them · $document: what the signatures belong to (e.g. "AIP") · $open: start with the editor open (after an error) --}}
@php
    $editable = $editable ?? true;
    $document = $document ?? 'document';
    $horizontal = $horizontal ?? false;
    $statusStyle = [
        'saved' => ['bg-secondary/10 text-secondary', 'check_circle', 'Saved'],
        'suggested' => ['bg-amber-100 text-amber-800', 'lightbulb', 'Suggested, not saved yet'],
        'empty' => ['bg-surface-high text-on-surface-variant', 'radio_button_unchecked', 'Not named yet'],
    ];
    $anySuggested = collect($slots)->contains(fn ($slot) => $slot['status'] === 'suggested');
    $fieldClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:bg-surface-low';
@endphp
<div class="rounded-lg border border-outline-variant/40 bg-white" data-signatory-editor>
    <ul class="divide-y divide-outline-variant/20 {{ $horizontal ? 'md:grid md:grid-cols-3 md:divide-x md:divide-y-0' : '' }}" aria-label="Who signs the {{ $document }}">
        @foreach($slots as $slot)
            @php [$chip, $icon, $label] = $statusStyle[$slot['status']]; @endphp
            <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2.5 {{ $horizontal ? 'md:flex-col md:items-start md:justify-start md:gap-y-2' : '' }}">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">{{ $slot['legend'] }}</p>
                    <p class="{{ $horizontal ? 'md:whitespace-normal' : '' }} truncate text-sm font-semibold">{{ $slot['name'] ?: '—' }}@if($slot['position'])<span class="font-normal text-on-surface-variant"> · {{ $slot['position'] }}</span>@endif</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $chip }}"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $icon }}</span>{{ $label }}</span>
            </li>
        @endforeach
    </ul>
    @if($editable)
        <details class="group border-t border-outline-variant/30" data-signatory-box @if($open ?? false) open @endif>
            <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2 px-4 py-2.5 text-xs font-semibold text-primary"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span>Edit signatories</summary>
            <div class="space-y-3 border-t border-outline-variant/20 bg-surface-low/40 p-4">
                <p class="text-xs text-on-surface-variant">The <strong>title</strong> is printed above the signature. The <strong>name</strong> and <strong>position</strong> are printed below it. Nothing changes on the printed {{ $document }} until you save.</p>
                @if($anySuggested)<p class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-900"><strong>Suggested</strong> names are filled in from the school's last saved planning documents. Check them, then save to keep them on the {{ $document }}.</p>@endif
                <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(15rem,1fr))">
                    @foreach($slots as $slot)
                        <fieldset class="rounded-lg border border-outline-variant/40 bg-white p-3">
                            <legend class="px-1.5 text-[11px] font-bold uppercase tracking-wider text-primary">{{ $slot['legend'] }}</legend>
                            <div class="space-y-2.5">
                                @if(! empty($slot['picker']))@include('partials.signatory-picker', ['rows' => [$slot['picker']], 'enabled' => true, 'card' => false])@endif
                                <label class="block text-xs font-semibold text-on-surface-variant">Title above the signature<input name="{{ $slot['key'] }}_role" value="{{ $slot['role'] }}" maxlength="255" class="{{ $fieldClass }}"></label>
                                <label class="block text-xs font-semibold text-on-surface-variant">Full name<input name="{{ $slot['key'] }}_name" value="{{ $slot['name'] }}" maxlength="255" placeholder="{{ $slot['namePlaceholder'] ?? 'Type the full name' }}" class="{{ $fieldClass }}"></label>
                                <label class="block text-xs font-semibold text-on-surface-variant">Position<input name="{{ $slot['key'] }}_position" value="{{ $slot['position'] }}" maxlength="255" class="{{ $fieldClass }}">@if(! empty($slot['positionHint']))<span class="mt-1 block text-[11px] font-normal">{{ $slot['positionHint'] }}</span>@endif</label>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @if(! empty($saveLabel))<button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-container md:w-auto">{{ $saveLabel }}</button>@endif
            </div>
        </details>
    @endif
</div>
