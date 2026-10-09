@extends('layouts.procurement')
@section('title', $school->name) @section('page-title', 'School Management')
@section('workspace-label', 'Master account') @section('hide-module-tabs', '1') @section('flash-handled', '1')
@section('content')
@php
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $dialog = 'w-[min(94vw,28rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40';
    $schoolActive = $school->status === 'active';
@endphp
@if(session('success'))<div role="status" class="civic-alert civic-alert--success">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="civic-alert civic-alert--error">{{ $errors->first() }}</div>@endif

<a href="{{ route('school-management') }}" class="mb-3 inline-flex items-center gap-1 text-xs font-bold text-action hover:underline"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>All schools</a>
<header class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">{{ $school->code }}</p>
        <h1 class="mt-1 text-3xl font-bold">{{ $school->name }}</h1>
        <p class="mt-1 text-sm text-on-surface-variant">{{ collect([$school->division, $school->district, $school->region])->filter()->implode(' · ') ?: 'No division or district set' }}</p>
    </div>
    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold {{ $schoolActive ? 'bg-secondary/10 text-secondary' : 'bg-surface-high text-on-surface-variant' }}"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $schoolActive ? 'check_circle' : 'block' }}</span>{{ $schoolActive ? 'Active' : 'Inactive' }}</span>
</header>

<div class="space-y-5">
    <section class="rounded-xl border border-outline-variant/60 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
            <div><h2 class="font-bold text-primary">School status</h2><p class="text-xs text-on-surface-variant">An inactive school cannot sign in and cannot receive a transferred user. Its records are kept.</p></div>
            <form method="POST" action="{{ route('school-management.status', $school) }}" onsubmit="return confirm('{{ $schoolActive ? 'Set this school inactive? Its user will be signed out.' : 'Activate this school?' }}')">@csrf<input type="hidden" name="active" value="{{ $schoolActive ? 0 : 1 }}"><button class="rounded-lg px-4 py-2 text-xs font-bold {{ $schoolActive ? 'border border-error/40 text-error hover:bg-error/10' : 'bg-primary text-white hover:bg-primary-container' }}">{{ $schoolActive ? 'Set school inactive' : 'Activate school' }}</button></form>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><h2 class="font-bold text-primary">System user</h2><p class="text-xs text-on-surface-variant">One user manages one school. Setting the user inactive makes the school vacant.</p></div>
        <ul class="divide-y divide-outline-variant/30">
            @forelse($users as $user)
                @php $active = ($user->status ?: 'active') === 'active'; @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $user->name }} <span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $active ? 'bg-secondary/10 text-secondary' : 'bg-surface-high text-on-surface-variant' }}">{{ $active ? 'Active' : 'Inactive' }}</span></p>
                        <p class="text-xs text-on-surface-variant">{{ $user->email }} · {{ $user->position ?: str($user->role)->replace('_', ' ')->title() }}</p>
                        @unless($active)<p class="mt-1 text-xs text-on-surface-variant"><strong>{{ $user->deactivation_reason ?: 'Inactive' }}</strong>@if($user->deactivated_at) · {{ $user->deactivated_at->format('M d, Y') }}@endif @if($user->deactivation_note) · {{ $user->deactivation_note }}@endif</p>@endunless
                    </div>
                    @if($active)
                        <button type="button" data-open="user-off-{{ $user->id }}" class="rounded-lg border border-error/40 px-3 py-1.5 text-xs font-bold text-error hover:bg-error/10">Set inactive</button>
                    @else
                        <form method="POST" action="{{ route('school-management.users.reactivate', $user) }}">@csrf<button class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-bold text-primary hover:bg-surface-low">Reactivate</button></form>
                    @endif
                </li>
                @if($active)
                    <dialog id="user-off-{{ $user->id }}" class="{{ $dialog }}">
                        @include('partials.school-management.deactivate-form', ['action' => route('school-management.users.deactivate', $user), 'title' => 'Set '.$user->name.' inactive', 'help' => 'They cannot sign in any more and the school becomes vacant. Their subscription and the school records are kept.'])
                    </dialog>
                @endif
            @empty
                <li class="px-5 py-8 text-center text-sm text-on-surface-variant">This school has no user. Move one in with a station transfer.</li>
            @endforelse
        </ul>
    </section>

    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><h2 class="font-bold text-primary">Employees <span class="ml-1 text-xs font-normal text-on-surface-variant">{{ $employees->count() }} active</span></h2><p class="text-xs text-on-surface-variant">Inactive employees leave the lists and role choices; the record stays.</p></div>
        <ul class="divide-y divide-outline-variant/30">
            @forelse($employees as $employee)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                    <div><p class="font-semibold">{{ $employee->name }}</p><p class="text-xs text-on-surface-variant">{{ $employee->position ?: 'No position' }} · {{ $employee->employee_no }}</p></div>
                    <button type="button" data-open="emp-off-{{ $employee->id }}" class="rounded-lg border border-error/40 px-3 py-1.5 text-xs font-bold text-error hover:bg-error/10">Set inactive</button>
                </li>
                <dialog id="emp-off-{{ $employee->id }}" class="{{ $dialog }}">
                    @include('partials.school-management.deactivate-form', ['action' => route('school-management.employees.deactivate', $employee->id), 'title' => 'Set '.$employee->name.' inactive', 'help' => 'They drop out of the employee list and role choices. The record is kept.'])
                </dialog>
            @empty
                <li class="px-5 py-8 text-center text-sm text-on-surface-variant">No active employees.</li>
            @endforelse
        </ul>
    </section>

    @if($inactiveEmployees->isNotEmpty())
        <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
            <div class="border-b border-outline-variant/40 px-5 py-3"><h2 class="font-bold">Inactive employees <span class="ml-1 text-xs font-normal text-on-surface-variant">{{ $inactiveEmployees->count() }}</span></h2></div>
            <ul class="divide-y divide-outline-variant/30">
                @foreach($inactiveEmployees as $employee)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div><p class="font-semibold text-on-surface-variant">{{ $employee->name }}</p><p class="text-xs text-on-surface-variant"><strong>{{ $employee->end_reason ?: 'Transferred' }}</strong> · {{ $employee->ended_at->format('M d, Y') }}@if($employee->end_note) · {{ $employee->end_note }}@endif</p></div>
                        <form method="POST" action="{{ route('school-management.employees.reactivate', $employee->id) }}">@csrf<button class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-bold text-primary hover:bg-surface-low">Reactivate</button></form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>

<script>
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open]');
        if (opener) { document.getElementById(opener.dataset.open)?.showModal(); return; }
        const closer = event.target.closest('[data-close]');
        if (closer) { closer.closest('dialog')?.close(); return; }
        if (event.target instanceof HTMLDialogElement) { event.target.close(); }
    });
</script>
@endsection
