@extends('layouts.budget')

@section('title', 'Program of Works')
@section('section', 'Planning')
@section('crumb', 'SOB · Program of Works')

@section('content')
<div class="mb-5">
    @include('partials.planning-jump', ['schoolId' => $pow->school_id, 'year' => $plan?->fiscal_year, 'backTo' => 'sob'])
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Program of Works</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $school?->name }} · {{ $plan?->quarterLabel() }} FY {{ $plan?->fiscal_year }} · {{ $pow->program }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('planning.forms', ['sob_plan_id' => $pow->sob_plan_id]) }}" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold hover:bg-surface-low">Form Generator</a>
            <a href="{{ route('planning.pow.print', $pow) }}" target="_blank" rel="noopener" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container">Print</a>
        </div>
    </div>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<div class="mb-6">
    <form method="POST" action="{{ route('planning.pow.update', $pow) }}">@csrf @method('PUT')
        <fieldset @disabled(! $canManage) class="min-w-0">
            @include('partials.pow-fields')
        </fieldset>
        @if($canManage)<div class="mt-5 flex justify-end"><button class="rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-primary-container">Save changes</button></div>@endif
    </form>
    @if($canManage)
        <form method="POST" action="{{ route('planning.pow.destroy', $pow) }}" onsubmit="return confirm('Delete this Program of Works?')" class="mt-6 rounded-xl border border-error/30 bg-error/5 px-5 py-4">@csrf @method('DELETE')
            <button class="rounded-lg border border-error/40 bg-white px-3 py-2 text-xs font-semibold text-error hover:bg-error hover:text-white">Delete this Program of Works</button>
        </form>
    @endif
</div>
@endsection
