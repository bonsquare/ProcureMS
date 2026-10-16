{{-- The fields of an SOB Item below its activity and account: a free note, the particulars rows and any other lines. $group is the saved Item when editing. --}}
@php
    $lineRows = $group ? $group['items']->values() : collect([null]);
    $extraRows = $group ? $group['extras']->values() : collect();
@endphp
<div class="sob-rows md:col-span-6" data-particulars-rows data-next-index="{{ $lineRows->count() }}">
    @foreach($lineRows as $i => $line)@include('partials.sob-line-row', ['i' => $i, 'line' => $line, 'box' => $box])@endforeach
</div>
<template data-line-template>@include('partials.sob-line-row', ['i' => '__INDEX__', 'line' => null, 'box' => $box])</template>
<div class="md:col-span-6"><button type="button" data-add-particulars class="rounded border border-primary px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low">+ Add particulars</button></div>
<label class="text-xs font-semibold text-on-surface-variant md:col-span-6">Note (optional; prints beside the particulars, e.g. POW required)<input name="note" maxlength="255" value="{{ old('note', $group['note'] ?? '') }}" class="{{ $box }}"></label>
<label class="text-xs font-semibold text-on-surface-variant md:col-span-6">Summary label (optional; the name in the Summary per object of expenditure, e.g. Trainings. Items with the same label are added together)<input name="summary_label" maxlength="120" value="{{ old('summary_label', $group['summary_label'] ?? '') }}" class="{{ $box }}"></label>
<div class="grid gap-3 md:col-span-6 md:grid-cols-6" data-extra-rows data-next-index="{{ $extraRows->count() }}">
    @foreach($extraRows as $i => $line)@include('partials.sob-extra-row', ['i' => $i, 'line' => $line, 'box' => $box])@endforeach
</div>
<template data-extra-template>@include('partials.sob-extra-row', ['i' => '__INDEX__', 'line' => null, 'box' => $box])</template>
<div class="md:col-span-6"><button type="button" data-add-extra class="rounded border border-outline-variant/60 px-3 py-1.5 text-xs font-semibold text-on-surface-variant hover:bg-surface-low">+ Add other line (e.g. Labor)</button></div>
