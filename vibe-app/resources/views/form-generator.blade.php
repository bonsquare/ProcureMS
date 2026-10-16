@extends('layouts.budget')

@section('title', 'Form Generator')
@section('section', 'Planning')
@section('crumb', 'SOB · Form Generator')

@section('content')
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $box = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary';
@endphp
<div class="mb-5">
    @include('partials.planning-jump', ['schoolId' => $school->id, 'year' => $year, 'backTo' => 'sob'])
    <h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-tight">Form Generator</h1>
    <p class="mt-1 text-sm text-on-surface-variant">Official forms made from the School Operating Budget. {{ $school->name }} · FY {{ $year }}</p>
</div>
@if(session('success'))<div class="mb-4 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<section class="mb-6 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="border-b border-outline-variant/20 px-5 py-4">
        <h2 class="text-base font-semibold">{{ $forms['pow']['title'] }}</h2>
        <p class="mt-1 text-xs text-on-surface-variant">{{ $forms['pow']['description'] }}</p>
    </div>
    <form method="GET" action="{{ route('planning.forms') }}" class="grid gap-3 px-5 py-4 md:grid-cols-5" id="pow-filters">
        @if($schools->count() > 1)
            <label class="text-xs font-semibold text-on-surface-variant">School<select name="school_id" onchange="this.form.sob_plan_id.value='';this.form.submit()" class="{{ $box }}">@foreach($schools as $option)<option value="{{ $option->id }}" @selected($option->id === $school->id)>{{ $option->name }}</option>@endforeach</select></label>
        @else
            <input type="hidden" name="school_id" value="{{ $school->id }}">
        @endif
        <label class="text-xs font-semibold text-on-surface-variant">Fiscal Year<input type="number" name="year" min="2000" max="2100" value="{{ $year }}" onchange="this.form.sob_plan_id.value='';this.form.submit()" class="{{ $box }}"></label>
        <label class="text-xs font-semibold text-on-surface-variant">SOB (quarter)<select name="sob_plan_id" onchange="this.form.program.value='';this.form.submit()" class="{{ $box }}"><option value="">Choose an SOB</option>@foreach($sobPlans as $option)<option value="{{ $option->id }}" @selected($plan?->id === $option->id)>{{ $option->quarterLabel() }} · {{ $option->fund_source }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Program<select name="program" onchange="this.form.activity_id.value='';this.form.submit()" class="{{ $box }}"><option value="">Choose a program</option>@foreach($programs as $option)<option value="{{ $option }}" @selected($program === $option)>{{ $option }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-on-surface-variant">Activity<select name="activity_id" onchange="this.form.submit()" class="{{ $box }}"><option value="all">All activities of the program</option>@foreach($activities as $option)<option value="{{ $option->id }}" @selected($activityId === $option->id)>{{ $option->activity }}</option>@endforeach</select></label>
    </form>

    @if($plan && $program !== '')
        @if($canManage)
        <form method="POST" action="{{ route('planning.pow.store') }}" class="border-t border-outline-variant/20 px-5 py-5">@csrf
            <input type="hidden" name="sob_plan_id" value="{{ $plan->id }}"><input type="hidden" name="program" value="{{ $program }}"><input type="hidden" name="activity_id" value="{{ $activityId }}">
            @include('partials.pow-fields', ['pow' => null, 'lines' => $materials->all(), 'labor' => $labor, 'signatories' => $signatories])
            <div class="mt-5 flex justify-end"><button class="rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Create POW</button></div>
        </form>
        @else
            <p class="border-t border-outline-variant/20 px-5 py-4 text-xs text-on-surface-variant">You can open and print saved POWs. Creating one needs permission to manage planning.</p>
        @endif
    @elseif($plan)
        <p class="border-t border-outline-variant/20 px-5 py-4 text-xs text-on-surface-variant">Choose a program (and an activity) to fill the Program of Works from its SOB items.</p>
    @else
        <p class="border-t border-outline-variant/20 px-5 py-4 text-xs text-on-surface-variant">Choose the SOB (quarter) first. Only the SOBs of this school and fiscal year are listed.</p>
    @endif
</section>

<section class="mb-6 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="border-b border-outline-variant/20 px-5 py-4"><h2 class="text-base font-semibold">Saved Programs of Works <span class="ml-1 text-xs font-normal text-on-surface-variant">{{ $savedPows->count() }}</span></h2></div>
    @if($savedPows->isEmpty())
        <p class="px-5 py-6 text-center text-xs text-on-surface-variant">No Program of Works yet.</p>
    @else
        <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">Project Title</th><th class="px-3 py-3">Program</th><th class="px-3 py-3">Quarter</th><th class="px-3 py-3 text-right">Total Project Cost</th><th class="px-3 py-3">Date</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
            @foreach($savedPows as $saved)
                <tr><td class="px-5 py-3 font-semibold">{{ $saved->project_title }}</td><td class="px-3 py-3">{{ $saved->program }}</td><td class="px-3 py-3">{{ $saved->sobPlan?->quarterLabel() }} {{ $saved->sobPlan?->fiscal_year }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $peso($saved->total) }}</td><td class="px-3 py-3">{{ ($saved->pow_date ?? $saved->created_at)?->format('M d, Y') }}</td>
                    <td class="px-3 py-3 text-right"><div class="inline-flex items-center gap-1.5">
                        <a href="{{ route('planning.pow.show', $saved) }}" title="Edit" aria-label="Edit Program of Works" class="grid h-8 w-8 place-items-center rounded-lg border border-outline-variant/60 hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]">edit</span></a>
                        <a href="{{ route('planning.pow.print', $saved) }}" target="_blank" rel="noopener" title="Print" aria-label="Print Program of Works" class="grid h-8 w-8 place-items-center rounded-lg border border-outline-variant/60 hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]">print</span></a>
                        @if(auth()->user()->hasPermission('planning.manage'))
                            <form method="POST" action="{{ route('planning.pow.destroy', $saved) }}" onsubmit="return confirm('Delete this Program of Works?')" class="inline">@csrf @method('DELETE')<button type="submit" title="Delete" aria-label="Delete Program of Works" class="grid h-8 w-8 place-items-center rounded-lg border border-error/40 text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
                        @endif
                    </div></td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</section>
@endsection
