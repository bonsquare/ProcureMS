@extends('layouts.procurement')
@section('title', 'School Management') @section('page-title', 'School Management')
@section('workspace-label', 'Master account') @section('hide-module-tabs', '1')
@section('content')
@php
    $states = ['active' => ['Active', 'bg-secondary/10 text-secondary', 'check_circle'], 'vacant' => ['Vacant', 'bg-amber-100 text-amber-800', 'person_off'], 'inactive' => ['Inactive', 'bg-surface-high text-on-surface-variant', 'block']];
    $filters = [null => ['All', $total], 'active' => ['Active', $counts['active'] ?? 0], 'vacant' => ['Vacant', $counts['vacant'] ?? 0], 'inactive' => ['Inactive', $counts['inactive'] ?? 0]];
@endphp
<header class="mb-5">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Master account</p>
    <h1 class="mt-2 text-3xl font-bold">School Management</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Every school and its user. When someone retires, resigns or transfers, set them inactive. Nothing is deleted, and everything can be reactivated.</p>
</header>

<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <nav class="flex flex-wrap gap-1.5" aria-label="Filter schools">
            @foreach($filters as $key => [$label, $count])
                <a href="{{ route('school-management', array_filter(['status' => $key, 'q' => $search])) }}" @if(($filter ?? null) === $key) aria-current="page" @endif class="rounded-full px-3 py-1.5 text-xs font-bold {{ ($filter ?? null) === $key ? 'bg-primary text-white' : 'bg-white text-on-surface-variant hover:text-primary' }}">{{ $label }} <span class="opacity-70">{{ $count }}</span></a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('school-management') }}" class="flex gap-2">
            @if($filter)<input type="hidden" name="status" value="{{ $filter }}">@endif
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name or School ID" class="w-56 rounded-lg border border-outline-variant bg-white px-3 py-1.5 text-xs outline-none focus:border-action">
            <button class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-container">Search</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-surface-low text-[11px] uppercase tracking-wide text-on-surface-variant">
                <tr><th class="px-5 py-2.5">School</th><th class="px-3 py-2.5">User</th><th class="px-3 py-2.5">Division</th><th class="px-3 py-2.5 text-center">Transfers</th><th class="px-3 py-2.5">Status</th><th class="px-5 py-2.5"></th></tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30">
                @forelse($schools as $school)
                    @php [$label, $tone, $icon] = $states[$school->state]; @endphp
                    <tr class="hover:bg-surface-low/60">
                        <td class="px-5 py-3"><p class="font-semibold">{{ $school->name }}</p><p class="text-xs text-on-surface-variant">{{ $school->code }}</p></td>
                        <td class="px-3 py-3">@if($school->manager)<p>{{ $school->manager->name }}</p><p class="text-xs text-on-surface-variant">{{ $school->manager->position ?: str($school->manager->role)->replace('_', ' ')->title() }}</p>@else<span class="text-xs text-on-surface-variant">No active user</span>@endif</td>
                        <td class="px-3 py-3 text-xs text-on-surface-variant">{{ $school->division ?: '—' }}</td>
                        <td class="px-3 py-3 text-center">@if($school->pending_transfers)<a href="{{ route('school-management.show', $school) }}#transfer" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 hover:bg-amber-200">{{ $school->pending_transfers }} pending</a>@else<span class="text-xs text-on-surface-variant">—</span>@endif</td>
                        <td class="px-3 py-3"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $tone }}"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $icon }}</span>{{ $label }}</span></td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('school-management.show', $school) }}" class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-bold text-primary hover:bg-surface-low">Manage<span class="material-symbols-outlined text-[15px]" aria-hidden="true">chevron_right</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-on-surface-variant">No schools match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
