@extends('layouts.procurement')
@section('title', $module) @section('page-title', $module)
@section('workspace-label', 'Workspace') @section('hide-module-tabs', '1')
@section('content')
<section class="mx-auto mt-10 max-w-xl rounded-xl border border-outline-variant/60 bg-white p-8 text-center">
    <span class="material-symbols-outlined text-[40px] text-outline-variant" aria-hidden="true">domain_disabled</span>
    <h1 class="mt-2 text-xl font-bold">No school to plan for yet</h1>
    @if(auth()->user()->seesAllSchools())
        <p class="mt-2 text-sm text-on-surface-variant">{{ $module }} works on a school's records, and no school is registered yet. A school appears here once a person registers or takes over a school, or when you add one in School Management or with a user in User Management.</p>
        <div class="mt-5 flex flex-wrap justify-center gap-2"><a href="{{ route('school-management') }}" class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">School Management</a><a href="{{ route('user-management') }}" class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold hover:bg-surface-low">User Management</a></div>
    @else
        <p class="mt-2 text-sm text-on-surface-variant">No school is assigned to your account, so there is nothing to plan yet. Ask the master user to give you an Official Station, or send a request from School Settings.</p>
    @endif
</section>
@endsection
