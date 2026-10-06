@extends('layouts.budget')
@section('title', $kra->exists ? 'Edit KRA' : 'Add KRA')
@section('section', 'Reports')
@section('crumb', 'AIP')
@section('content')
@php
    $editing = $kra->exists;
    $field = $inputClass;
    $label = 'block text-xs font-semibold text-on-surface-variant';
    $v = fn ($key) => old($key, $kra->{$key});
    $activities = collect($activities)->values();
@endphp
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $editing ? 'Edit KRA and Activities' : 'Add KRA and Activities' }}</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">AIP FY {{ $aip->fiscal_year }} · {{ $aip->school?->name }} · {{ $aip->entity }}. Fill in the KRA and every activity under it, then save once.</p>
    </div>
    <a href="{{ route('aip.show', $aip) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">← Back to AIP</a>
</div>

@if($errors->any())
    <div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error"><p class="font-semibold">Please fix the following:</p><ul class="mt-1 list-disc pl-5">@foreach(collect($errors->all())->unique()->take(5) as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif

<form id="kra-page-form" method="POST" action="{{ $editing ? route('aip.kras.update', [$aip, $kra]) : route('aip.kras.store', $aip) }}" class="space-y-5">
    @csrf @if($editing) @method('PUT') @endif

    <section class="overflow-hidden rounded-lg border border-outline-variant/40 bg-white">
        <header class="flex items-center gap-3 bg-primary px-5 py-3 text-white"><span class="material-symbols-outlined text-[20px]">flag</span><h2 class="text-base font-semibold">Pillar and Key Result Area</h2></header>
        <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <label class="{{ $label }} md:col-span-2">Pillar / Enabling Mechanism
                <input name="pillar" list="pillar-list" autocomplete="off" value="{{ $v('pillar') }}" placeholder="Choose from the list or type your own" class="{{ $field }}">
                <datalist id="pillar-list">@foreach($pillars as $pillar)<option value="{{ $pillar }}">@endforeach</datalist>
                <span class="mt-1 block text-[11px] font-normal text-on-surface-variant">Pick a standard option, or type another pillar / enabling mechanism if it isn't listed.</span>
            </label>
            <label class="{{ $label }} md:col-span-2">Key Result Area (KRA) <span class="text-error">*</span><input name="kra" required value="{{ $v('kra') }}" placeholder="e.g. KRA 3: Learner Formation and Development" class="{{ $field }}"></label>
            <label class="{{ $label }} md:col-span-2">Intermediate Outcome<textarea name="intermediate_outcome" rows="2" class="{{ $field }}">{{ $v('intermediate_outcome') }}</textarea></label>
            <label class="{{ $label }}">Strategy<input name="strategy" value="{{ $v('strategy') }}" class="{{ $field }}"></label>
            <label class="{{ $label }}">5-Point Agenda<input name="five_point_agenda" value="{{ $v('five_point_agenda') }}" class="{{ $field }}"></label>
            <label class="{{ $label }} md:col-span-2">Specific Program / Project<input name="program" value="{{ $v('program') }}" class="{{ $field }}"></label>
        </div>
    </section>

    <section class="rounded-lg border border-outline-variant/40 border-l-4 border-l-secondary bg-surface-low/40 p-4">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold">Activities under this KRA <span id="activity-count" class="ml-1 rounded-full bg-secondary/10 px-2 py-0.5 text-xs text-secondary"></span></h2>
            <button type="button" id="add-activity" class="rounded bg-secondary px-4 py-2 text-xs font-semibold text-white hover:opacity-90">+ Add Activity</button>
        </div>
        <div id="activity-list" class="space-y-4">
            @foreach($activities as $i => $a)
                @include('partials.aip-activity-card', ['i' => $i, 'a' => $a, 'accounts' => $accounts, 'fundOptions' => $fundOptions, 'field' => $field, 'label' => $label])
            @endforeach
        </div>
        <p class="mt-4 text-right text-sm font-semibold">All activities: <span id="grand-total" class="tabular-nums">₱0.00</span></p>
    </section>

    <div class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t border-outline-variant/30 bg-surface/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
        <a href="{{ route('aip.show', $aip) }}" class="rounded border border-outline-variant/60 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-surface-low">Cancel</a>
        <button class="rounded bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-container">Save KRA and Activities</button>
    </div>
</form>

<template id="activity-template">
    @include('partials.aip-activity-card', ['i' => '__INDEX__', 'a' => [], 'accounts' => $accounts, 'fundOptions' => $fundOptions, 'field' => $field, 'label' => $label])
</template>
@endsection
@push('scripts')
<script>
    (() => {
        const list = document.getElementById('activity-list');
        const template = document.getElementById('activity-template').innerHTML;
        let next = list.querySelectorAll('[data-activity]').length;
        const peso = (n) => '₱' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const refresh = () => {
            let grand = 0;
            const cards = list.querySelectorAll('[data-activity]');
            cards.forEach((card, index) => {
                card.querySelector('[data-number]').textContent = index + 1;
                const total = Array.from(card.querySelectorAll('[data-quarter]')).reduce((sum, input) => sum + Number(input.value || 0), 0);
                card.querySelector('[data-total]').textContent = peso(total);
                grand += total;
            });
            document.getElementById('grand-total').textContent = peso(grand);
            document.getElementById('activity-count').textContent = cards.length + (cards.length === 1 ? ' activity' : ' activities');
        };

        const addRow = (card, name) => {
            const group = card.querySelector('[data-list="' + name + '"]');
            const index = card.dataset.index;
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            row.innerHTML = '<input class="min-w-0 flex-1 rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary"><button type="button" data-remove-row aria-label="Remove" class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]">close</span></button>';
            const input = row.querySelector('input');
            input.name = 'activities[' + index + '][' + name + '][]';
            input.placeholder = group.dataset.placeholder;
            group.appendChild(row);
            input.focus();
        };

        const syncFund = (card) => {
            const select = card.querySelector('[data-fund-select]');
            const other = card.querySelector('[data-fund-other]');
            const isOther = select.value === 'Others';
            other.classList.toggle('hidden', !isOther);
            card.querySelector('[data-fund-value]').value = isOther ? (other.value.trim() || 'Others') : select.value;
        };

        // Existing cards keep the index their field names were rendered with.
        list.querySelectorAll('[data-activity]').forEach((card, index) => { card.dataset.index = index; });

        document.getElementById('add-activity').addEventListener('click', () => {
            const index = next++;
            list.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', index));
            const card = list.lastElementChild;
            card.dataset.index = index;
            refresh();
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.querySelector('textarea').focus();
        });

        list.addEventListener('click', (event) => {
            const card = event.target.closest('[data-activity]');
            if (!card) return;
            if (event.target.closest('[data-remove-activity]')) {
                if (list.querySelectorAll('[data-activity]').length === 1) { alert('A KRA needs at least one activity.'); return; }
                card.remove();
                refresh();
            }
            const add = event.target.closest('[data-add-row]');
            if (add) addRow(card, add.dataset.addRow);
            const remove = event.target.closest('[data-remove-row]');
            if (remove) {
                const group = remove.closest('[data-list]');
                remove.parentElement.remove();
                if (!group.children.length) addRow(card, group.dataset.list);
            }
        });
        list.addEventListener('input', (event) => {
            const card = event.target.closest('[data-activity]');
            if (!card) return;
            if (event.target.matches('[data-quarter]')) refresh();
            if (event.target.matches('[data-fund-other]')) syncFund(card);
        });
        list.addEventListener('change', (event) => {
            const card = event.target.closest('[data-activity]');
            if (card && event.target.matches('[data-fund-select]')) { syncFund(card); if (event.target.value === 'Others') card.querySelector('[data-fund-other]').focus(); }
        });

        if (!list.children.length) document.getElementById('add-activity').click();
        refresh();
    })();
</script>
@endpush
