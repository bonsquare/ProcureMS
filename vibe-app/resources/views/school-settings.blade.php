@extends('layouts.procurement')
@section('title', 'School Settings')
@section('page-title', 'School Settings')
@section('hide-module-tabs', '1')
@section('flash-handled', '1')
@section('content')
@php
    $selectedSchool = $selectedSchool ?? null;
    $selectedSchoolId = $selectedSchool?->id;
    $employeeRoleCount = $staff->sum(fn ($member) => collect(array_keys($roleGroups))->sum(fn ($group) => count($member->rolesFor($group))));
    $bacMembers = $staff->filter(fn ($member) => count($member->rolesFor('bac_role')) > 0)->count();
    $activeUsers = $systemUsers->where('status', 'active')->count();
    $tabs = [
        'info' => ['School Information', 'domain'],
        'users' => ['System Users', 'manage_accounts'],
        'staff' => ['Employees & Roles', 'badge'],
    ];
    $stats = [
        ['Profile complete', $profileCompleteness.'%', $profileCompleteness >= 80 ? 'Ready for official documents' : 'Fill in the missing details', 'task_alt', $profileCompleteness >= 80 ? 'green' : 'amber', $profileCompleteness],
        ['System users', $systemUsers->count(), $activeUsers.' active', 'manage_accounts', 'blue', null],
        ['Employees', $staff->count(), $bacMembers.' in the BAC', 'badge', 'blue', null],
        ['Roles assigned', $employeeRoleCount, 'BAC, procurement and document roles', 'verified_user', 'green', null],
        ['Logos set', $logoCount.' of 3', 'Agency, division and school', 'image', $logoCount === 3 ? 'green' : 'amber', null],
    ];
    $palette = ['blue' => ['#e2edf7', '#286da8'], 'green' => ['#ddf0e8', '#2a7f64'], 'amber' => ['#fff0dc', '#b46f1f']];
    $input = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $readonly = 'mt-1 w-full rounded-lg border border-outline-variant bg-surface-low px-3 py-2 text-sm font-semibold text-on-surface-variant';
    $logoUrl = fn (?string $path) => $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? asset('storage/'.$path) : null;
    $tabUrl = fn (string $key) => route('school-settings', array_filter(['ui' => 'staff-save-v7', 'school_id' => $selectedSchoolId, 'tab' => $key]));
@endphp

<header class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Organization configuration</p>
        <h1 class="mt-1 font-bold">School Settings</h1>
        <p class="mt-1 text-sm text-on-surface-variant">{{ $selectedSchool?->name ?? 'Choose a school to manage' }} · agency identity, system users and who does what.</p>
    </div>
    @if($isMasterUser)
        <form method="GET" action="{{ route('school-settings') }}" class="rounded-xl border border-outline-variant/60 bg-white p-2.5">
            <input type="hidden" name="ui" value="staff-save-v7"><input type="hidden" name="tab" value="{{ $tab }}">
            <label class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">School
                <select name="school_id" onchange="this.form.submit()" class="ml-2 rounded-lg border border-outline-variant bg-white px-2.5 py-1.5 text-xs font-semibold normal-case tracking-normal text-on-surface">
                    <option value="">Select a school</option>
                    @foreach($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach
                </select>
            </label>
        </form>
    @endif
</header>

@if(session('success'))<div role="status" class="civic-alert civic-alert--success">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="civic-alert" style="border-color:#e4b4b7;background:#f8e2e4;color:#8a2f35"><strong>Please fix the following:</strong><ul class="mt-1 list-disc pl-5 text-xs">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

<section class="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-5" aria-label="Settings overview">
    @foreach($stats as [$label, $value, $note, $icon, $tone, $bar])
        @php [$soft, $strong] = $palette[$tone]; @endphp
        <div class="relative overflow-hidden rounded-xl border border-outline-variant/50 bg-white p-3.5 pl-4">
            <span class="absolute inset-y-0 left-0 w-1" style="background:{{ $strong }}" aria-hidden="true"></span>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $label }}</p><p class="mt-1 text-xl font-bold leading-none text-primary">{{ $value }}</p></div>
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" style="background:{{ $soft }};color:{{ $strong }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span></span>
            </div>
            @if($bar !== null)<div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-surface-container"><div class="h-full rounded-full" style="width:{{ $bar }}%;background:{{ $strong }}"></div></div>@endif
            <p class="mt-2 truncate text-[11px] text-on-surface-variant">{{ $note }}</p>
        </div>
    @endforeach
</section>

<nav class="civic-module-tabs" aria-label="School settings sections">
    @foreach($tabs as $key => [$label, $icon])
        <a href="{{ $tabUrl($key) }}" @if($tab === $key) aria-current="page" @endif class="civic-module-tab {{ $tab === $key ? 'civic-module-tab--active' : '' }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span></a>
    @endforeach
</nav>

@if(! $selectedSchool)
    <div class="rounded-xl border border-outline-variant/60 bg-white p-10 text-center text-sm text-on-surface-variant">Choose a school above to manage its information, users and employees.</div>
@elseif($tab === 'users')
    @include('partials.settings.users')
@elseif($tab === 'staff')
    @include('partials.settings.staff')
@else
    @include('partials.settings.info')
@endif

<script>
    // Native dialogs: any button with data-open opens the dialog with that id, data-close closes it.
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open]');
        if (opener) { document.getElementById(opener.dataset.open)?.showModal(); return; }
        const closer = event.target.closest('[data-close]');
        if (closer) { closer.closest('dialog')?.close(); return; }
        if (event.target instanceof HTMLDialogElement) { event.target.close(); }
    });
    // Logo previews before saving, with the file details and a check of the 2 MB / image-type limits.
    document.querySelectorAll('input[type=file][data-preview]').forEach((input) => input.addEventListener('change', () => {
        const target = document.getElementById(input.dataset.preview);
        const note = document.querySelector('[data-logo-info="' + input.dataset.preview + '"]');
        const file = input.files && input.files[0];
        if (!target || !note) return;
        const reset = () => { note.textContent = note.dataset.default; note.classList.remove('text-error'); };
        const reject = (message) => { input.value = ''; note.textContent = message; note.classList.add('text-error'); };
        if (!file) { reset(); return; }
        if (!['image/png', 'image/jpeg', 'image/webp', 'image/gif'].includes(file.type)) { reject('Not accepted: use a PNG, JPG, WebP or GIF image.'); return; }
        if (file.size > 2 * 1024 * 1024) { reject('Too large (' + (file.size / 1048576).toFixed(1) + ' MB). The limit is 2 MB.'); return; }
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            target.innerHTML = '<img src="' + url + '" alt="" class="h-full w-full object-contain">';
            const small = image.naturalWidth < 300 || image.naturalHeight < 300;
            note.classList.toggle('text-error', false);
            note.textContent = file.name + ' · ' + (file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB') + ' · ' + image.naturalWidth + ' × ' + image.naturalHeight + ' px' + (small ? ' (small: it may print blurry)' : '') + ' · new, not saved yet';
        };
        image.onerror = () => reject('This file could not be read as an image.');
        image.src = url;
    }));
</script>
@endsection
