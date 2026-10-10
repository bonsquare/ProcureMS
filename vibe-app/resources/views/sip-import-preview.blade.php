@extends('layouts.budget')

@section('title', 'Import SIP from Excel')
@section('section', 'Planning')
@section('crumb', 'SIP · Import from Excel')

@section('content')
@php
    $box = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
@endphp
<div class="mb-5">
    <a href="{{ route('planning', ['school_id' => $school->id]) }}#sip" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    <h1 class="mt-2 text-2xl font-bold">Import SIP from Excel</h1>
    <p class="mt-1 text-sm text-on-surface-variant">{{ $school->name }} · SIP {{ $state['plan']['planning_period'] ?? '' }}. Check what was read, correct anything that is wrong, then confirm. Nothing is saved until you confirm.</p>
</div>
@if(session('error'))<div class="mb-4 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@if($schoolNameWarning)<div class="mb-4 rounded border border-attention/30 bg-attention/10 px-4 py-3 text-sm">{{ $schoolNameWarning }}</div>@endif

@if($state['issues'])
<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4" id="sip-import-issues">
    <h2 class="font-semibold">Found in the file</h2>
    <p class="text-xs text-on-surface-variant">These were found when the file was read. Fix them in the fields below, or remove the row.</p>
    <ul class="mt-2 space-y-1 text-sm">
        @foreach($state['issues'] as $issue)
            <li class="{{ $issue['level'] === 'error' ? 'text-error' : 'text-attention' }}">{{ $issue['level'] === 'error' ? 'Error' : 'Warning' }}@if($issue['row']) · Excel row {{ $issue['row'] }}@endif: {{ $issue['message'] }}</li>
        @endforeach
    </ul>
</section>
@endif

<section class="mb-5 grid gap-3 rounded border border-outline-variant/30 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" id="sip-import-totals" aria-live="polite">
    <div><p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Programs · activities</p><p class="mt-1 text-lg font-bold" data-total="counts">0 · 0</p></div>
    <div><p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Year 1 financial</p><p class="mt-1 text-lg font-bold" data-total="y1">₱0.00</p></div>
    <div><p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Year 2 financial</p><p class="mt-1 text-lg font-bold" data-total="y2">₱0.00</p></div>
    <div><p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Year 3 financial</p><p class="mt-1 text-lg font-bold" data-total="y3">₱0.00</p></div>
</section>

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Signatories</h2>
    <div class="mt-2 grid gap-3 md:grid-cols-3" id="sip-signatories"></div>
</section>

<div id="sip-programs" class="space-y-4"></div>
<p id="sip-empty" class="hidden rounded border border-outline-variant/30 bg-white p-6 text-center text-sm text-on-surface-variant">No programs to import. Upload the Excel file again, or add programs in the SIP list after fixing the file.</p>

<script type="application/json" id="sip-plan">@json($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>

<form method="POST" action="{{ route('planning.sip.import.store', $token) }}" id="sip-import-form" class="sticky bottom-0 z-10 mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/40 bg-white/95 px-1 py-3 backdrop-blur">
    @csrf
    <input type="hidden" name="payload" id="sip-import-payload" value="">
    <p class="text-xs text-on-surface-variant" id="sip-import-status" aria-live="polite"></p>
    <div class="flex gap-2">
        <a href="{{ route('planning', ['school_id' => $school->id]) }}#sip" class="rounded border border-outline-variant px-4 py-2 text-sm font-semibold">Cancel</a>
        <button type="submit" id="sip-import-confirm" disabled class="rounded bg-primary px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Confirm import</button>
    </div>
</form>

<script>
(() => {
    const state = JSON.parse(document.getElementById('sip-plan').textContent);
    const pillars = @json($pillars);
    const box = @json($box);
    const programsEl = document.getElementById('sip-programs');
    const signEl = document.getElementById('sip-signatories');
    const confirmBtn = document.getElementById('sip-import-confirm');
    const statusEl = document.getElementById('sip-import-status');
    const emptyEl = document.getElementById('sip-empty');
    const peso = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let checks = [];

    // Numbers: blank is allowed for a physical target (kept empty) and means 0 for money; "1,500" and "₱1,500" are numbers.
    const parseNumber = (text) => {
        const clean = String(text ?? '').replace(/[,₱\s]/g, '');
        if (clean === '') return { blank: true, value: null };
        const value = Number(clean);
        return Number.isFinite(value) ? { blank: false, value } : { blank: false, value: NaN };
    };

    const el = (tag, props = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(props).forEach(([key, value]) => {
            if (key === 'class') node.className = value; else if (key === 'text') node.textContent = value; else node.setAttribute(key, value);
        });
        children.forEach((child) => node.append(child));
        return node;
    };

    // A labelled input bound to state[key]; "check" returns an error text or ''.
    const textField = (label, holder, key, check = null, multiline = false, wide = false) => {
        const input = el(multiline ? 'textarea' : 'input', { class: box, ...(multiline ? { rows: '2' } : { type: 'text' }) });
        input.value = holder[key] ?? '';
        input.addEventListener('input', () => { holder[key] = input.value; refresh(); });
        if (check) checks.push({ input, check: () => check(holder[key]) });
        return el('label', { class: 'block text-xs font-semibold text-on-surface-variant' + (wide ? ' md:col-span-2' : '') }, [document.createTextNode(label), input]);
    };

    const numberField = (holder, index, kind, label) => {
        const input = el('input', { class: box + ' text-right', type: 'text', inputmode: 'decimal', 'aria-label': label, placeholder: kind === 'physical' ? '' : '0' });
        const current = holder[kind][index];
        input.value = current === null || current === undefined || current === 0 && kind === 'financial' ? '' : String(current);
        input.addEventListener('input', () => {
            const parsed = parseNumber(input.value);
            holder[kind][index] = parsed.blank ? (kind === 'physical' ? null : 0) : (Number.isNaN(parsed.value) ? input.value : parsed.value);
            refresh();
        });
        checks.push({ input, check: () => { const v = holder[kind][index]; return typeof v === 'string' ? 'Not a number' : (v !== null && v < 0 ? 'Must be 0 or more' : ''); } });
        return el('label', { class: 'block text-[11px] font-semibold text-on-surface-variant' }, [document.createTextNode(label), input]);
    };

    const render = () => {
        checks = [];
        signEl.replaceChildren();
        [['prepared_by', 'Prepared by'], ['recommended_by', 'Recommending approval'], ['approved_by', 'Approved by']].forEach(([prefix, title]) => {
            signEl.append(el('div', { class: 'space-y-2 rounded border border-outline-variant/30 p-3' }, [
                textField(title + ': name', state.signatories, prefix + '_name'),
                textField('Position', state.signatories, prefix + '_position'),
            ]));
        });

        programsEl.replaceChildren();
        state.projects.forEach((project, pIndex) => {
            const select = el('select', { class: box });
            pillars.concat(pillars.includes(project.pillar) ? [] : [project.pillar]).forEach((name) => select.append(el('option', { value: name, text: name || '(choose a pillar)' })));
            select.value = project.pillar;
            select.addEventListener('change', () => { project.pillar = select.value; refresh(); });
            checks.push({ input: select, check: () => pillars.includes(project.pillar) ? '' : 'Choose one of the pillars' });

            const head = el('div', { class: 'grid gap-3 md:grid-cols-3' }, [
                el('label', { class: 'block text-xs font-semibold text-on-surface-variant' }, [document.createTextNode('Pillar'), select]),
                textField('KRA', project, 'kra', (v) => String(v).trim() === '' ? 'The KRA is empty' : ''),
                textField('Specific program / project', project, 'project', (v) => String(v).trim() === '' ? 'The program name is empty' : ''),
                textField('Organizational outcome', project, 'organizational_outcome', null, true, true),
                textField('Strategy', project, 'strategy'),
                textField('5-point agenda', project, 'five_point_agenda', null, true, true),
                textField('Source of fund', project, 'source_of_fund'),
            ]);

            const removeProgram = el('button', { type: 'button', class: 'rounded border border-error/40 px-3 py-1.5 text-xs font-semibold text-error hover:bg-error/10' }, [document.createTextNode('Remove program')]);
            removeProgram.addEventListener('click', () => {
                if (!confirm('Remove "' + (project.project || 'this program') + '" and its ' + project.activities.length + ' activities from the import?')) return;
                state.projects.splice(pIndex, 1); render(); refresh();
            });

            const rows = project.activities.map((activity, aIndex) => {
                const removeActivity = el('button', { type: 'button', class: 'mt-5 rounded border border-error/40 px-2 py-1 text-[11px] font-semibold text-error hover:bg-error/10' }, [document.createTextNode('Remove')]);
                removeActivity.addEventListener('click', () => {
                    if (!confirm('Remove this activity from the import?')) return;
                    project.activities.splice(aIndex, 1); render(); refresh();
                });
                return el('div', { class: 'rounded border border-outline-variant/30 bg-surface-low/40 p-3' }, [
                    el('div', { class: 'flex items-start justify-between gap-2' }, [
                        el('div', { class: 'min-w-0 flex-1' }, [textField('Activity' + (activity.row ? ' (Excel row ' + activity.row + ')' : ''), activity, 'activity', (v) => String(v).trim() === '' ? 'Activity text is empty' : '', true)]),
                        removeActivity,
                    ]),
                    el('div', { class: 'mt-2 grid grid-cols-3 gap-2 md:grid-cols-6' }, [
                        numberField(activity, 0, 'physical', 'Physical Y1'), numberField(activity, 1, 'physical', 'Physical Y2'), numberField(activity, 2, 'physical', 'Physical Y3'),
                        numberField(activity, 0, 'financial', 'Financial Y1'), numberField(activity, 1, 'financial', 'Financial Y2'), numberField(activity, 2, 'financial', 'Financial Y3'),
                    ]),
                    el('div', { class: 'mt-2 grid gap-2 md:grid-cols-2' }, [textField('Responsible person', activity, 'responsible_person'), textField('Remarks', activity, 'remarks')]),
                ]);
            });

            programsEl.append(el('section', { class: 'rounded border border-outline-variant/30 bg-white p-4' }, [
                el('div', { class: 'mb-3 flex items-start justify-between gap-2' }, [
                    el('h3', { class: 'font-semibold', text: 'Program ' + (pIndex + 1) + (project.row ? ' (Excel row ' + project.row + ')' : '') }),
                    removeProgram,
                ]),
                head,
                el('div', { class: 'mt-3 space-y-2' }, rows.length ? rows : [el('p', { class: 'text-xs text-on-surface-variant', text: 'This program has no activities. You can add them in the SIP list after importing.' })]),
            ]));
        });
        emptyEl.classList.toggle('hidden', state.projects.length > 0);
    };

    // Re-checks every field, updates the totals and enables Confirm only when nothing is wrong.
    const refresh = () => {
        let errors = 0;
        checks.forEach(({ input, check }) => {
            const message = check();
            input.classList.toggle('border-error', message !== '');
            input.classList.toggle('bg-error/5', message !== '');
            input.title = message;
            if (message) errors++;
        });
        let activities = 0; const years = [0, 0, 0];
        state.projects.forEach((project) => project.activities.forEach((activity) => {
            activities++;
            activity.financial.forEach((value, i) => { if (typeof value === 'number') years[i] += value; });
        }));
        const totals = document.getElementById('sip-import-totals');
        totals.querySelector('[data-total="counts"]').textContent = state.projects.length + ' · ' + activities;
        ['y1', 'y2', 'y3'].forEach((key, i) => { totals.querySelector('[data-total="' + key + '"]').textContent = peso(years[i]); });
        confirmBtn.disabled = errors > 0 || state.projects.length === 0;
        statusEl.textContent = state.projects.length === 0 ? 'Nothing to import.' : (errors ? (errors === 1 ? '1 field still needs' : errors + ' fields still need') + ' fixing (marked in red).' : 'Everything looks valid. Confirm to save.');
    };

    document.getElementById('sip-import-form').addEventListener('submit', () => {
        document.getElementById('sip-import-payload').value = JSON.stringify({ signatories: state.signatories, projects: state.projects });
    });

    render();
    refresh();
})();
</script>
@endsection
