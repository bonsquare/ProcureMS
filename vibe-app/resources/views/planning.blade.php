@extends('layouts.budget')

@section('title', 'Planning')
@section('section', 'Planning')
@section('crumb', 'SIP · AIP · SOB · APP')

@section('content')
@php
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary';
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
@endphp
<div class="mb-4 flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
    <div>
        <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary"><span class="material-symbols-outlined text-base">account_tree</span> Integrated School Planning</div>
        <h1 class="text-2xl font-semibold leading-8 tracking-tight">Planning</h1>
        <p class="mt-0.5 text-sm text-on-surface-variant">SIP → AIP → SOB → APP. The APP-CSE is a separate plan from the Procurement Service catalog.</p>
    </div>
    <form method="GET" action="{{ route('planning') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-outline-variant/40 bg-white p-2.5 shadow-sm">
        <label class="text-xs font-semibold text-on-surface-variant">School
            <select name="school_id" class="mt-1 min-w-52 rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm" onchange="this.form.submit()">
                @foreach($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchool->id === $school->id)>{{ $school->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-on-surface-variant">Fiscal Year
            <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="mt-1 w-24 rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm">
        </label>
        <button class="rounded-lg border border-primary px-4 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white">View</button>
        @if(auth()->user()->hasPermission('planning.manage'))<button type="button" data-tab="settings" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-on-surface-variant hover:bg-surface-low" title="{{ $fundSources->count() }} fund source(s) · fiscal year"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">tune</span>Settings</button>@endif
    </form>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

@php
        // One line per step of the planning flow: SIP -> AIP -> SOB -> APP, with what exists for the selected school and year.
        $latestSipYear = $sipProjects->max('school_year');
        $sipNow = $sipProjects->where('school_year', $latestSipYear);
        $sipActivities = $sipNow->flatMap->activities;
        $sipYearTotals = [$sipActivities->sum('financial_year1'), $sipActivities->sum('financial_year2'), $sipActivities->sum('financial_year3')];
        $sipTotal = array_sum($sipYearTotals);
        $sipPeak = max(1, max($sipYearTotals));
        // The template has one pillar for Resiliency and Well-Being, whichever of the two names a program was saved with.
        $sipPillar = fn ($name) => in_array(strtolower(trim((string) $name)), ['resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being'], true) ? 'Resiliency and Well-Being' : ((string) $name ?: 'Unassigned');
        $pillarCounts = $sipNow->groupBy(fn ($p) => $sipPillar($p->pillar))->map->count();
        $pillarTones = ['Access' => 'bg-sky-100 text-sky-800', 'Equity' => 'bg-teal-100 text-teal-800', 'Quality' => 'bg-violet-100 text-violet-800', 'Resiliency and Well-Being' => 'bg-emerald-100 text-emerald-800', 'Enabling Mechanism' => 'bg-amber-100 text-amber-800'];

        $aipsYear = $aips->where('fiscal_year', $year);
        $aipTotal = $aipsYear->sum(fn ($a) => $a->activities->sum(fn ($activity) => $activity->total));
        $sobYear = $sobPlans->where('fiscal_year', $year);
        $sobTotal = $sobYear->sum(fn ($plan) => $plan->items->sum('amount'));
        $appTotal = $appPlan ? $appPlan->items->sum('estimated_total_cost') : 0;

        // state: done (green), current (amber: started, not approved yet), todo (grey)
        $steps = [
            ['sip', 'flag', 'School Improvement Plan', 'SIP', $sipNow->count().' program'.($sipNow->count() === 1 ? '' : 's'), $sipActivities->count().' activities · '.$peso($sipTotal).' over 3 years', $sipNow->isNotEmpty() ? 'done' : 'todo', $sipNow->isNotEmpty() ? 'Entered' : 'Not started'],
            ['aip', 'event_note', 'Annual Implementation Plan', 'AIP · FY '.$year, $aipsYear->count().' plan'.($aipsYear->count() === 1 ? '' : 's'), $aipsYear->where('status', 'approved')->count().' approved · '.$peso($aipTotal), $aipsYear->where('status', 'approved')->isNotEmpty() ? 'done' : ($aipsYear->isNotEmpty() ? 'current' : 'todo'), $aipsYear->where('status', 'approved')->isNotEmpty() ? 'Approved' : ($aipsYear->isNotEmpty() ? 'Awaiting approval' : 'Not started')],
            ['sob', 'account_balance_wallet', 'School Operating Budget', 'SOB · FY '.$year, $sobYear->count().' quarter'.($sobYear->count() === 1 ? '' : 's'), $sobYear->where('status', 'approved')->count().' approved · '.$peso($sobTotal), $sobYear->where('status', 'approved')->isNotEmpty() ? 'done' : ($sobYear->isNotEmpty() ? 'current' : 'todo'), $sobYear->where('status', 'approved')->isNotEmpty() ? 'Approved' : ($sobYear->isNotEmpty() ? 'Awaiting approval' : 'Not started')],
            ['app', 'fact_check', 'Annual Procurement Plan', 'APP · FY '.$year, ($appPlan?->items->count() ?? 0).' item'.(($appPlan?->items->count() ?? 0) === 1 ? '' : 's'), $appPlan ? $peso($appTotal) : 'Not generated yet', $appPlan?->status === 'approved' ? 'done' : ($appPlan ? 'current' : 'todo'), $appPlan?->status === 'approved' ? 'Approved' : ($appPlan ? ucfirst($appPlan->status) : 'Not started')],
        ];
        $doneCount = collect($steps)->where(6, 'done')->count();
        $nextStep = collect($steps)->first(fn ($step) => $step[6] !== 'done');
        $guidance = [
            'sip' => ['Start with the SIP', "Enter the school's programs and their activities for the three-year plan."],
            'aip' => $aipsYear->isNotEmpty() ? ['Approve the AIP for FY '.$year, 'Approving it lets you start the SOB. The AIP is for reports and is not connected to the Budget.'] : ['Create the AIP for FY '.$year, 'Turn the SIP programs into the annual plan with a budget per quarter and source of fund.'],
            'sob' => $sobYear->isNotEmpty() ? ['Approve the SOB for FY '.$year, 'Each approved quarter creates its Budget allotments and feeds the Annual Procurement Plan.'] : ['Prepare the SOB for FY '.$year, 'For each quarter, pick the AIP activities that have an actual budget and add their items.'],
            'app' => $appPlan ? ['Approve the APP for FY '.$year, 'The approved APP is the source of every Purchase Request.'] : ['Choose the APP items for FY '.$year, 'Pick the items of the approved SOBs that go into the Annual Procurement Plan.'],
        ];
        $stateStyle = [
            'done' => ['bg-secondary/10 text-secondary', 'check_circle', 'border-secondary/40'],
            'current' => ['bg-amber-100 text-amber-800', 'pending', 'border-amber-300'],
            'todo' => ['bg-surface-high text-on-surface-variant', 'radio_button_unchecked', 'border-outline-variant/50'],
        ];
    @endphp

    {{-- The flow, always in view: SIP -> AIP -> SOB -> APP (each one opens its panel), and the separate APP-CSE --}}
    <nav id="planning-stepper" aria-label="Planning steps" class="sticky top-16 z-20 -mx-4 mb-4 overflow-hidden border-b border-outline-variant/30 bg-surface/95 px-4 py-2 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
        <div class="flex items-stretch gap-2 overflow-x-auto pb-0.5">
            <style>#planning-stepper [data-overview][hidden]{display:none}</style><button type="button" data-overview hidden class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-outline-variant/60 bg-white px-3 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>Overview</button>
            <ol class="flex shrink-0 items-stretch gap-1.5 md:min-w-0 md:flex-1">
                @foreach($steps as $n => [$key, $icon, $title, $short, $line, $sub, $state, $stateLabel])
                    @php
                        [$chip, $stateIcon, $edge] = $stateStyle[$state];
                        $dot = ['done' => 'bg-secondary text-white', 'current' => 'bg-amber-400 text-white', 'todo' => 'bg-surface-high text-on-surface-variant'][$state];
                        $before = $n > 0 ? $steps[$n - 1] : null;
                        $wait = $state === 'done' ? null : (($before && $before[6] !== 'done') ? 'Waits for: '.$guidance[$before[0]][0] : 'Next: '.$guidance[$key][0]);
                    @endphp
                    <li class="min-w-[9.75rem] flex-none md:flex-1">
                        <button type="button" data-tab="{{ $key }}" aria-label="{{ $title }}: {{ $stateLabel }}" @if($wait) title="{{ $wait }}" @endif class="flex h-full w-full items-center gap-2.5 rounded-xl border-2 {{ $edge }} bg-white px-3 py-2 text-left shadow-sm transition hover:shadow-md">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $dot }}" aria-hidden="true">@if($state === 'done')<span class="material-symbols-outlined text-[18px]">check</span>@else{{ $n + 1 }}@endif</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Step {{ $n + 1 }} · {{ $short }}</span>
                                <span class="block truncate text-sm font-semibold text-primary">{{ $line }}</span>
                                <span class="mt-0.5 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $chip }}"><span class="material-symbols-outlined text-[12px]" aria-hidden="true">{{ $stateIcon }}</span>{{ $stateLabel }}</span>
                                @if($wait)<span class="sr-only">{{ $wait }}</span>@endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>
            <span class="hidden w-px shrink-0 bg-outline-variant/50 md:block" aria-hidden="true"></span>
            <button type="button" data-goto="app-cse" title="A separate plan from the Procurement Service catalog" class="flex min-w-[8.5rem] shrink-0 items-center gap-2 rounded-xl border-2 border-dashed border-outline-variant/60 bg-surface-low/60 px-3 py-2 text-left hover:border-primary hover:bg-white">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant" aria-hidden="true">inventory_2</span>
                <span><span class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Separate plan</span><span class="block text-sm font-semibold">APP-CSE</span></span>
            </button>
        </div>
    </nav>

<section id="dashboard" class="mb-6">
    {{-- Progress and the next step --}}
    <div id="planning-hint" class="mb-4 overflow-hidden rounded-xl border border-outline-variant/40 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent px-5 py-4">
            <div class="min-w-[220px] flex-1">
                <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $selectedSchool->name }} · FY {{ $year }}</p>
                @if($nextStep)
                    <p class="mt-1 text-lg font-semibold tracking-tight">Next: {{ $guidance[$nextStep[0]][0] }}</p>
                    <p class="mt-0.5 text-sm text-on-surface-variant">{{ $guidance[$nextStep[0]][1] }}</p>
                @else
                    <p class="mt-1 text-lg font-semibold tracking-tight text-secondary">All four plans for FY {{ $year }} are approved</p>
                    <p class="mt-0.5 text-sm text-on-surface-variant">Raise Purchase Requests from the Annual Procurement Plan.</p>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <div class="w-44">
                    <div class="flex items-baseline justify-between text-xs font-semibold"><span class="text-on-surface-variant">Plan progress</span><span class="text-primary">{{ $doneCount }} of 4</span></div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-high" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="{{ $doneCount }}"><div class="h-full rounded-full bg-gradient-to-r from-primary to-secondary transition-all" style="width: {{ $doneCount * 25 }}%"></div></div>
                </div>
                @if($nextStep)
                    <button type="button" data-tab="{{ $nextStep[0] }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-primary-container">Open {{ strtoupper($nextStep[0]) }}<span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span></button>
                @else
                    <a href="{{ route('procurement') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-secondary px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:opacity-90">Go to Procurement<span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span></a>
                @endif
            </div>
        </div>
    </div>

    {{-- SIP at a glance: the three-year budget and the programs per pillar --}}
    @if($sipNow->isNotEmpty())
        <details id="planning-glance" class="group mt-4 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2 px-4 py-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant"><span>SIP at a glance · {{ $sipNow->count() }} programs · {{ $sipActivities->count() }} activities · {{ $peso($sipTotal) }}</span><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></summary>
        <div class="grid gap-3 border-t border-outline-variant/30 p-3 lg:grid-cols-[1.1fr_1fr]">
            <div class="rounded-2xl border border-outline-variant/40 bg-white p-4 shadow-sm">
                <div class="flex items-baseline justify-between"><p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">SIP financial target · {{ $latestSipYear }}-{{ $latestSipYear + 2 }}</p><p class="text-sm font-semibold text-primary">{{ $peso($sipTotal) }}</p></div>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    @foreach($sipYearTotals as $i => $amount)
                        <div><div class="flex h-24 items-end rounded-lg bg-surface-low px-3"><div class="w-full rounded-t-md {{ $amount === max($sipYearTotals) ? 'bg-primary' : 'bg-primary/55' }}" style="height: {{ max(6, round($amount / $sipPeak * 100)) }}%" title="{{ $peso($amount) }}"></div></div><p class="mt-1.5 text-center text-[11px] font-bold text-on-surface-variant">Year {{ $i + 1 }}<span class="block text-xs font-semibold text-on-surface">{{ $peso($amount) }}</span></p></div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-xl border border-outline-variant/40 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Programs per pillar</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($pillarCounts as $pillar => $count)
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $pillarTones[$pillar] ?? 'bg-surface-high text-on-surface-variant' }}">{{ $pillar }}<span class="rounded-full bg-white/70 px-1.5 text-[11px]">{{ $count }}</span></span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-on-surface-variant">{{ $sipNow->count() }} programs · {{ $sipActivities->count() }} activities. Open the SIP to add, edit or print.</p>
            </div>
        </div>
        </details>
    @endif
</section>

<div data-panel="sip" class="hidden">
<style>details[open] > summary .sum-chevron,[aria-expanded="true"] .sum-chevron{transform:rotate(180deg)}</style>
<datalist id="sip-funds">
    <option value="Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE"></option>
    <option value="MOOE"></option><option value="SEF"></option><option value="IGP"></option><option value="PTA Fund"></option><option value="BLGU"></option><option value="Provincial Government"></option><option value="Municipal SEF"></option><option value="Others"></option>
</datalist>
@php $sipGroups = $sipProjects->groupBy('school_year')->sortKeysDesc(); @endphp
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::sip($selectedSchool, (int) ($latestSipYear ?: $year))])
<section class="mb-6 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">
        @include('partials.planning-panel-head', ['icon' => 'flag', 'title' => 'School Improvement Plan (SIP)', 'text' => 'Three-year plan: each program lists its activities with Year 1-3 physical and financial targets. Print it on the official SIP form.', 'chips' => [$sipNow->count().' program'.($sipNow->count() === 1 ? '' : 's'), $sipActivities->count().' activities', $peso($sipTotal).' over 3 years']])
        @if(auth()->user()->hasPermission('planning.manage'))
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('planning.sip.import.preview') }}" enctype="multipart/form-data" class="inline-flex">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><label class="cursor-pointer rounded border border-primary px-4 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white" title="Upload the school's SIP Excel file (.xlsx), check it, then confirm"><span class="material-symbols-outlined mr-1 align-middle text-[16px]">upload_file</span>Import from Excel<input type="file" name="file" accept=".xlsx" class="sr-only" onchange="this.form.submit()"></label></form>
            <button type="button" data-edit-dialog="dlg-sip-new" data-action="{{ route('planning.sip.store') }}" data-payload="{{ json_encode(['school_id' => $selectedSchool->id, 'school_year' => $year]) }}" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">+ Add SIP Program</button>
        </div>
        @endif
    </div>
    {{-- Break into AIP: each year of the SIP becomes the AIP of that fiscal year (the quarterly amounts are split evenly until the SOB template is provided). A year that already has its AIP is a blue chip. --}}
    @foreach($sipGroups as $startYear => $programs)
    <div class="flex flex-wrap items-center gap-2 border-b border-outline-variant/20 px-5 py-3">
        <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant" title="Each year of the SIP becomes the AIP of that fiscal year. The quarterly amounts are split evenly until the SOB template is provided.">Break into AIP · SIP FY {{ $startYear }}-{{ $startYear + 2 }}</span>
        @foreach([1, 2, 3] as $yearNo)
            @php $fy = $startYear + $yearNo - 1; $yearAip = $aips->firstWhere('fiscal_year', $fy); $yearTotal = $programs->sum(fn ($p) => $p->activities->sum('financial_year'.$yearNo)); @endphp
            @if($yearAip)
                <a href="{{ route('aip.show', $yearAip) }}" title="Year {{ $yearNo }}: open or edit the AIP" class="inline-flex items-center gap-1.5 rounded-full border border-blue-300 bg-blue-100 px-3 py-1 text-[11px] font-semibold text-blue-800 hover:bg-blue-200"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>AIP FY {{ $fy }} · {{ $peso($yearTotal) }} · {{ \Illuminate\Support\Str::headline($yearAip->status) }}</a>
            @elseif(auth()->user()->hasPermission('planning.manage') && auth()->user()->canManageBudget())
                <form method="POST" action="{{ route('planning.sip.generate-aip') }}" class="inline-flex">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="start_year" value="{{ $startYear }}"><input type="hidden" name="year_no" value="{{ $yearNo }}"><button title="Year {{ $yearNo }}: create the AIP" class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant/60 bg-white px-3 py-1 text-[11px] font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">event_note</span>Create AIP FY {{ $fy }} · {{ $peso($yearTotal) }}</button></form>
            @else
                <span class="inline-flex items-center rounded-full border border-outline-variant/50 bg-surface-low/60 px-3 py-1 text-[11px] text-on-surface-variant">FY {{ $fy }} · {{ $peso($yearTotal) }} · No AIP yet</span>
            @endif
        @endforeach
    </div>
    @endforeach
    @if($sipProjects->isNotEmpty())
        {{-- Find a program: by pillar and by any word of its program, KRA, strategy or activities. --}}
        <div data-sip-filter class="flex flex-wrap items-center gap-3 border-b border-outline-variant/20 px-5 py-3">
            <label class="flex items-center gap-2 text-xs font-semibold text-on-surface-variant">Pillar
                <select data-sip-pillar class="{{ $inputClass }} !mt-0 !w-56 !py-1.5"><option value="">All pillars</option>@foreach($sipProjects->map(fn ($p) => $sipPillar($p->pillar))->unique()->values() as $pillarName)<option value="{{ $pillarName }}">{{ $pillarName }}</option>@endforeach</select>
            </label>
            <input type="search" data-sip-search placeholder="Search a program, KRA or activity" aria-label="Search the SIP programs" class="{{ $inputClass }} !mt-0 !w-72 max-w-full !py-1.5">
            <p data-sip-count class="text-xs text-on-surface-variant" aria-live="polite"></p>
            <div class="ml-auto flex gap-2"><button type="button" data-sip-expand="1" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Expand all</button><button type="button" data-sip-expand="0" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Collapse all</button></div>
        </div>
    @endif

    @forelse($sipGroups as $startYear => $programs)
    <div class="border-b border-outline-variant/20 bg-surface-low/60 px-5 py-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><span class="text-sm font-semibold">SIP FY {{ $startYear }}-{{ $startYear + 2 }}</span><span class="ml-2 text-xs text-on-surface-variant">{{ $programs->count() }} program(s) · Total {{ $peso($programs->sum('estimated_budget')) }}</span></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('planning.sip.print', ['school_id' => $selectedSchool->id, 'start_year' => $startYear]) }}" target="_blank" rel="noopener" class="rounded bg-secondary px-3 py-2 text-xs font-semibold text-white">Print SIP</a>
                
            </div>
        </div>
    </div>
    @php
        $pillarOrder = ['Access', 'Equity', 'Quality', 'Resiliency and Well-Being', 'Enabling Mechanism'];
        $pillarGroups = $programs->groupBy(fn ($p) => $sipPillar($p->pillar))->sortBy(fn ($group, $name) => (($i = array_search($name, $pillarOrder, true)) === false ? count($pillarOrder) : $i));
    @endphp
    @foreach($pillarGroups as $pillarName => $pillarPrograms)
    <details open class="group mx-4 mb-4 mt-2 overflow-hidden rounded-lg border border-outline-variant/40 bg-white" data-sip-pillar-group>
        <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden flex-wrap items-center justify-between gap-x-3 gap-y-1 bg-primary px-5 py-3 text-white">
            <span class="flex items-center gap-3"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">flag</span>
                <span><span class="block text-[10px] font-semibold uppercase tracking-widest text-white/70">Pillar / Enabling Mechanism</span><span class="block text-base font-semibold">{{ $pillarName }}</span></span></span>
            <span class="flex items-center gap-2 text-xs text-white/80 max-sm:basis-full max-sm:pl-9"><span>{{ $pillarPrograms->count() }} program(s) · <strong class="text-white">{{ $peso($pillarPrograms->sum(fn ($p) => $p->activities->sum(fn ($a) => (float) $a->financial_year1 + (float) $a->financial_year2 + (float) $a->financial_year3))) }}</strong></span><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span>
        </summary>
    <div class="divide-y divide-outline-variant/20">
        @foreach($pillarPrograms as $sip)
        <details class="group px-5 py-4" data-sip-program data-pillar="{{ $sipPillar($sip->pillar) }}" data-search="{{ \Illuminate\Support\Str::lower(collect([$sip->project, $sip->kra, $sip->strategy, $sip->organizational_outcome, $sip->five_point_agenda])->merge($sip->activities->pluck('activity'))->filter()->implode(' ')) }}">
            <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-3">
                <span class="flex min-w-0 flex-wrap items-center gap-2"><h3 class="text-sm font-semibold">{{ $sip->project }}</h3></span>
                <span class="flex shrink-0 items-center gap-3 text-xs text-on-surface-variant"><span>{{ $sip->activities->count() }} activit{{ $sip->activities->count() === 1 ? 'y' : 'ies' }} · <strong class="text-on-surface">{{ $peso($sip->activities->sum(fn ($a) => (float) $a->financial_year1 + (float) $a->financial_year2 + (float) $a->financial_year3)) }}</strong></span><span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant sum-chevron transition-transform" aria-hidden="true">expand_more</span></span>
            </summary>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">@if($sip->transaction)<a class="text-[11px] font-semibold text-primary underline" href="{{ route('transactions.show', $sip->transaction) }}">{{ $sip->transaction->transaction_number }}</a>@endif</div>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ $sip->kra ?: 'No KRA entered' }}@if($sip->strategy) · {{ $sip->strategy }}@endif</p>
                    @if($sip->organizational_outcome)<p class="mt-1 text-xs text-on-surface-variant">{{ $sip->organizational_outcome }}</p>@endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(auth()->user()->hasPermission('planning.manage'))<button type="button" data-edit-dialog="dlg-activity-new" data-action="{{ route('planning.sip.activities.store', $sip) }}" data-payload="{{ json_encode(['program' => $sip->project]) }}" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white">+ Add Activity</button>@endif
                    @if(auth()->user()->hasPermission('planning.manage'))
                        <button type="button" data-edit-sip data-edit-dialog="dlg-sip" data-action="{{ route('planning.sip.update', $sip) }}" data-payload="{{ json_encode($sip->only(['school_year', 'planning_period', 'pillar', 'kra', 'organizational_outcome', 'strategy', 'five_point_agenda', 'project'])) }}" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">edit</span>Edit</button>
                        <form method="POST" action="{{ route('planning.sip.destroy', $sip) }}" data-confirm="Delete the program '{{ $sip->project }}' and its {{ $sip->activities->count() }} activities? This cannot be undone.">@csrf @method('DELETE')<button class="inline-flex items-center gap-1 rounded border border-error/40 bg-white px-3 py-2 text-xs font-semibold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">delete</span>Delete</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('planning.manage') && $aips->whereNull('sip_project_id')->isNotEmpty())<form method="POST" action="{{ route('planning.sip.link-aip', $sip) }}" class="flex gap-2">@csrf<select required name="aip_id" class="max-w-44 rounded border border-outline-variant/50 bg-white px-2 py-2 text-xs"><option value="">Link AIP</option>@foreach($aips->whereNull('sip_project_id') as $aip)<option value="{{ $aip->id }}">FY {{ $aip->fiscal_year }} · {{ ucfirst($aip->status) }}</option>@endforeach</select><button class="rounded border border-primary px-3 py-2 text-xs font-semibold text-primary">Link</button></form>@endif
                </div>
            </div>
            @if($sip->activities->isNotEmpty())
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-3 py-2">Activity</th><th class="px-2 py-2 text-center">Physical Y1 / Y2 / Y3</th><th class="px-2 py-2 text-right">Financial Y1</th><th class="px-2 py-2 text-right">Y2</th><th class="px-2 py-2 text-right">Y3</th><th class="px-3 py-2">Source of Fund</th><th class="px-3 py-2">Responsible</th><th class="px-2 py-2"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">
                @foreach($sip->activities as $act)
                <tr><td class="px-3 py-2">{{ $act->activity }}</td><td class="px-2 py-2 text-center tabular-nums">{{ (float) $act->physical_year1 ?: '–' }} / {{ (float) $act->physical_year2 ?: '–' }} / {{ (float) $act->physical_year3 ?: '–' }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year1) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year2) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year3) }}</td><td class="px-3 py-2">{{ $act->source_of_fund ?: '—' }}</td><td class="px-3 py-2">{{ $act->responsible_person ?: '—' }}</td>
                    <td class="whitespace-nowrap px-2 py-2 text-right">@if(auth()->user()->hasPermission('planning.manage'))<div class="flex items-center justify-end gap-3"><button type="button" data-edit-activity data-edit-dialog="dlg-activity" data-action="{{ route('planning.sip.activities.update', $act) }}" data-payload="{{ json_encode($act->only(['activity', 'physical_year1', 'physical_year2', 'physical_year3', 'financial_year1', 'financial_year2', 'financial_year3', 'source_of_fund', 'responsible_person', 'remarks'])) }}" class="text-[11px] font-semibold text-primary hover:underline">Edit</button><form method="POST" action="{{ route('planning.sip.activities.destroy', $act) }}" data-confirm="Delete this activity? This cannot be undone.">@csrf @method('DELETE')<button class="text-[11px] font-semibold text-error hover:underline">Delete</button></form></div>@endif</td></tr>
                @endforeach
                <tr class="bg-surface-low/60 font-semibold"><td class="px-3 py-2" colspan="2">Program total</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year1')) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year2')) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year3')) }}</td><td colspan="3"></td></tr>
            </tbody></table></div>
            @else<p class="mt-3 rounded border border-dashed border-outline-variant/50 px-3 py-3 text-center text-xs text-on-surface-variant">No activities yet. Add the activities with their Year 1-3 targets.</p>@endif
        </details>
        @endforeach
    </div>
    </details>
    @endforeach
    <div class="border-t border-outline-variant/20 bg-surface-low/60 px-5 py-3">
        @php
            $signPlan = $sipPlans[$startYear] ?? null;
            $sipSlots = \App\Support\PlanningSignatories::slots([['prepared_by', 'Prepared by', 'Prepared by', $selectedSchool->school_head, 'School Head/Team Leader', null, null], ['recommended_by', 'Recommending Approval', 'Recommending Approval', null, 'Chief, School Governance Operation Division', null, null], ['approved_by', 'Approved by', 'Approved by', null, 'Schools Division Superintendent', null, null]], $signPlan, auth()->user()->hasPermission('planning.manage') ? \App\Support\PlanningSignatories::lastUsed($selectedSchool->id, ['prepared_by', 'recommended_by', 'approved_by']) : []);
        @endphp
        <form method="POST" action="{{ route('planning.sip.signatories') }}" class="mb-3">@csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="start_year" value="{{ $startYear }}">
            @include('partials.signatory-editor', ['slots' => $sipSlots, 'editable' => auth()->user()->hasPermission('planning.manage'), 'document' => 'SIP', 'saveLabel' => 'Save signatories', 'horizontal' => true])
        </form>
    </div>
    @empty
    <div class="px-5 py-8 text-center text-sm text-on-surface-variant">No SIP programs saved for this school yet.</div>
    @endforelse
    <p data-sip-none hidden class="px-5 py-8 text-center text-sm text-on-surface-variant">No program matches the pillar and search.</p>
</section>
<script>
    (() => {
        const pillar = document.querySelector('[data-sip-pillar]');
        const search = document.querySelector('[data-sip-search]');
        if (!pillar || !search) return;
        const programs = Array.from(document.querySelectorAll('[data-sip-program]'));
        const count = document.querySelector('[data-sip-count]');
        const none = document.querySelector('[data-sip-none]');
        const apply = () => {
            const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;
            programs.forEach((program) => {
                const match = (!pillar.value || program.dataset.pillar === pillar.value) && words.every((word) => program.dataset.search.includes(word));
                program.hidden = !match;
                if (match) shown++;
            });
            count.textContent = shown === programs.length ? `${programs.length} programs` : `Showing ${shown} of ${programs.length} programs`;
            none.hidden = shown > 0;
            document.querySelectorAll('[data-sip-pillar-group]').forEach((group) => { group.hidden = ! group.querySelector('[data-sip-program]:not([hidden])'); });
        };
        document.querySelectorAll('[data-sip-expand]').forEach((button) => button.addEventListener('click', () => { const open = button.dataset.sipExpand === '1'; document.querySelectorAll('[data-sip-pillar-group]').forEach((group) => { group.open = true; }); programs.forEach((program) => { program.open = open; }); }));
        pillar.addEventListener('change', apply);
        search.addEventListener('input', apply);
        apply();
    })();
</script>
</div>

<div data-panel="aip" class="hidden">
<section id="aip" class="mb-6 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">@include('partials.planning-panel-head', ['icon' => 'event_note', 'title' => 'Annual Implementation Plan', 'text' => 'Yearly activities and financial targets per quarter and fund. An approved AIP is used for reports and to start an SOB.', 'chips' => [$aipsYear->count().' plan'.($aipsYear->count() === 1 ? '' : 's').' for FY '.$year, $aipsYear->where('status', 'approved')->count().' approved', $peso($aipTotal)]])
        @if(auth()->user()->canManageBudget())
        <button type="button" data-toggle-form="aip" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">+ New AIP</button>
        <form data-form="aip" data-display="flex" method="POST" action="{{ route('aip.store') }}" class="hidden w-full flex-wrap items-center justify-end gap-2 border-t border-outline-variant/20 pt-3">@csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <label class="flex items-center gap-2 text-xs font-semibold text-on-surface-variant">Fiscal Year<input type="number" name="fiscal_year" value="{{ $year }}" min="2000" max="2100" required class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></label>
            <button class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Create AIP</button>
        </form>
        @endif
    </div>
    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-xs"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">Fiscal Year</th><th class="px-4 py-3 text-right">Activities</th><th class="px-4 py-3 text-right">Total Financial Target</th><th class="px-4 py-3">SIP</th><th class="px-4 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($aips as $aip)
        <tr><td class="px-5 py-3 font-semibold">FY {{ $aip->fiscal_year }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $aip->activities->count() }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $peso($aip->activities->sum(fn ($a) => $a->total)) }}</td><td class="px-4 py-3">@if($aip->sip_start_year)<span class="font-semibold text-primary">from SIP {{ $aip->sip_start_year }}-{{ $aip->sip_start_year + 2 }} · Year {{ $aip->sip_year_no }}</span>@else{{ $aip->sipProject?->project ?: '—' }}@endif</td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $aip->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($aip->status) }}</span></td><td class="px-5 py-3 text-right"><div class="inline-flex items-center justify-end gap-1.5"><a href="{{ route('aip.show', $aip) }}" title="Open / Edit" aria-label="Open / Edit the AIP for FY {{ $aip->fiscal_year }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></a><a href="{{ route('aip.print', $aip) }}" target="_blank" rel="noopener" title="Print" aria-label="Print the AIP for FY {{ $aip->fiscal_year }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span></a>
            @if(auth()->user()->canManageBudget())
                @if($aip->status === 'draft' && ! in_array($aip->id, $legacyPpmpAipIds) && $sobPlans->where('aip_id', $aip->id)->isEmpty())
                    <form method="POST" action="{{ route('aip.destroy', $aip) }}" class="inline-flex" data-delete-aip onsubmit="return confirm('Delete the AIP for FY {{ $aip->fiscal_year }}? Its KRAs and activities are removed. This cannot be undone.')">@csrf @method('DELETE')<button title="Delete" aria-label="Delete the AIP for FY {{ $aip->fiscal_year }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-error/40 bg-white text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button></form>
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded border cursor-not-allowed border-outline-variant/40 bg-white text-on-surface-variant/40" title="Only a draft AIP with nothing built on it (an SOB or a budget) can be deleted." aria-label="Delete is not available for this AIP"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></span>
                @endif
            @endif</div></td></tr>
        @empty<tr><td colspan="6" class="px-5 py-8 text-center text-on-surface-variant">No AIP for this school yet.@if(auth()->user()->canManageBudget()) Enter the fiscal year and click New AIP.@endif</td></tr>@endforelse
    </tbody></table></div>
</section>

</div>

<div data-panel="sob" class="hidden">
<section class="mb-6 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">@include('partials.planning-panel-head', ['icon' => 'account_balance_wallet', 'title' => 'School Operating Budget (SOB) · FY '.$year, 'text' => 'One SOB for each fund in a quarter. Pick the AIP activities that have an actual budget in the quarter and add their items. Approving a quarter creates its Budget allotments.', 'chips' => [$sobYear->count().' quarter'.($sobYear->count() === 1 ? '' : 's'), $sobYear->where('status', 'approved')->count().' approved', $peso($sobTotal)]])</div>
    @php $aipApproved = $aips->where('fiscal_year', $year)->where('status', 'approved')->isNotEmpty(); @endphp
    @unless($aipApproved)<div class="border-b border-outline-variant/20 bg-amber-50 px-5 py-3 text-xs text-amber-900">Approve the AIP for FY {{ $year }} first. The SOB is built from it.</div>@endunless
    @php
        $canManageSob = auth()->user()->hasPermission('planning.manage');
        $canStart = $canManageSob && $aipApproved;
        $usedFunds = $sobYear->groupBy('quarter')->map(fn ($plans) => $plans->pluck('fund_source')->values())->all();
        $sobHistory = $sobYear->sortByDesc('created_at')->values();
    @endphp
    @if($canStart)
    <div class="flex flex-wrap items-center justify-end gap-3 border-b border-outline-variant/20 px-5 py-3">
        <button type="button" data-open-sob-create class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Create SOB</button>
    </div>
    @endif
    <details class="group" data-sob-history @if($sobHistory->count() <= 6) open @endif>
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-3">
        <h3 class="text-sm font-semibold">History of SOB created <span class="ml-1 text-xs font-normal text-on-surface-variant">{{ $sobHistory->count() }} SOB · {{ $peso($sobHistory->sum(fn ($sob) => $sob->items->sum('amount'))) }}</span></h3>
        <span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span>
    </summary>
    @if($sobHistory->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-on-surface-variant">No SOB created yet for FY {{ $year }}. @if($canStart)Use Create SOB to start the first one.@endif</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3 font-semibold">Quarter</th><th class="px-3 py-3 font-semibold">Source of fund</th><th class="px-3 py-3 font-semibold">Status</th><th class="px-3 py-3 text-right font-semibold">Items</th><th class="px-3 py-3 text-right font-semibold">Amount</th><th class="px-3 py-3 font-semibold">Created</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-outline-variant/20">
                    @foreach($sobHistory as $sob)
                        <tr>
                            <td class="px-5 py-3 font-semibold">{{ \App\Models\SobPlan::QUARTERS[$sob->quarter] ?? $sob->quarter }}</td>
                            <td class="px-3 py-3">{{ $sob->fund_source }}</td>
                            <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sob->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ $sob->status === 'approved' ? 'Approved' : 'Draft' }}</span></td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $sob->items->count() }}</td>
                            <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $peso($sob->items->sum('amount')) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-on-surface-variant">{{ $sob->created_at?->format('M d, Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($canManageSob)<button type="button" data-edit-dialog="dlg-sob" data-action="{{ route('planning.sob.update', $sob) }}" data-payload="{{ json_encode(['quarter' => $sob->quarter, 'fund_source' => $sob->fund_source]) }}" title="Edit" aria-label="Edit the {{ \App\Models\SobPlan::QUARTERS[$sob->quarter] ?? $sob->quarter }} SOB" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></button>
                                    <form method="POST" action="{{ route('planning.sob.destroy', $sob) }}" class="inline-flex" onsubmit="return confirm('{{ $sob->status === 'approved' ? 'Delete this approved SOB, its items and release its Budget allotments?' : 'Delete this draft SOB and its items?' }}')">@csrf @method('DELETE')<button title="Delete" aria-label="Delete the {{ \App\Models\SobPlan::QUARTERS[$sob->quarter] ?? $sob->quarter }} SOB" class="inline-flex h-8 w-8 items-center justify-center rounded border border-error/40 bg-white text-error hover:bg-error/10"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button></form>@endif
                                    <a href="{{ route('planning.sob.show', $sob) }}" title="Open" aria-label="Open the {{ \App\Models\SobPlan::QUARTERS[$sob->quarter] ?? $sob->quarter }} SOB" class="inline-flex h-8 w-8 items-center justify-center rounded border border-primary bg-white text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </details>

    @if($canStart)
        {{-- Create SOB: choose the quarter and the fund; a fund that already has an SOB in that quarter is not offered. --}}
        <dialog id="dlg-sob-create" data-used="{{ json_encode($usedFunds) }}" class="w-[min(94vw,28rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40">
            <form method="POST" action="{{ route('planning.sob.store') }}" class="grid gap-3 p-5">@csrf
                <h3 class="text-lg font-bold">Create SOB · FY {{ $year }}</h3>
                <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="fiscal_year" value="{{ $year }}">
                <label class="text-xs font-semibold">Quarter<select name="quarter" class="{{ $inputClass }}">@foreach(\App\Models\SobPlan::QUARTERS as $quarter => $quarterLabel)<option value="{{ $quarter }}">{{ $quarterLabel }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold">Source of fund<select name="fund_source" class="{{ $inputClass }}">@foreach($fundOptions as $fund)<option value="{{ $fund }}" @selected($fund === 'MOOE')>{{ $fund }}</option>@endforeach</select></label>
                <p data-all-used hidden class="text-xs text-on-surface-variant">Every fund already has an SOB this quarter.</p>
                <div class="mt-2 flex justify-end gap-2"><button type="button" data-close class="rounded border border-outline-variant px-4 py-2 text-xs font-semibold hover:bg-surface-low">Cancel</button><button data-create class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Create SOB</button></div>
            </form>
        </dialog>
        <script>
            (() => {
                const dialog = document.getElementById('dlg-sob-create');
                const quarter = dialog.querySelector('select[name=quarter]');
                const fund = dialog.querySelector('select[name=fund_source]');
                const used = JSON.parse(dialog.dataset.used || '{}');
                const note = dialog.querySelector('[data-all-used]');
                const create = dialog.querySelector('[data-create]');
                const refresh = () => {
                    const taken = used[quarter.value] || [];
                    Array.from(fund.options).forEach((option) => { option.disabled = taken.includes(option.value); });
                    if (fund.selectedOptions[0] && fund.selectedOptions[0].disabled) { const free = Array.from(fund.options).find((option) => !option.disabled); if (free) { fund.value = free.value; } }
                    const none = Array.from(fund.options).every((option) => option.disabled);
                    note.hidden = !none;
                    create.disabled = none;
                    create.classList.toggle('opacity-50', none);
                };
                quarter.addEventListener('change', refresh);
                document.querySelectorAll('[data-open-sob-create]').forEach((button) => button.addEventListener('click', () => { refresh(); dialog.showModal(); }));
            })();
        </script>
    @endif
</section>
</div>

<div data-panel="app" class="hidden">
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::app($selectedSchool, (int) $year, $appPlan)])
<section class="rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">@include('partials.planning-panel-head', ['icon' => 'fact_check', 'title' => 'Annual Procurement Plan · FY '.$year, 'text' => 'Built from the approved SOBs. Linked PR activity is visible per transaction.', 'chips' => $appPlan ? [$appPlan->items->count().' item'.($appPlan->items->count() === 1 ? '' : 's'), ucfirst($appPlan->status), $peso($appTotal)] : ['Not generated yet']])@if(auth()->user()->hasPermission('planning.manage') && ($appCandidates->flatten(1)->isNotEmpty() || ! $appPlan))<a href="#app-picker" class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white">Choose SOB items for the APP</a>@endif</div>
    @php $appCandidateCount = $appCandidates->flatten(1)->count(); @endphp
    @if(auth()->user()->hasPermission('planning.manage') && ($appCandidateCount > 0 || ! $appPlan))
    <form id="app-picker" method="POST" action="{{ route('planning.app.generate') }}" class="border-b border-outline-variant/20 bg-surface-low/40 px-5 py-4">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="fiscal_year" value="{{ $year }}">
        <details class="group" data-app-picker-box @if(! $appPlan || $appPlan->items->isEmpty()) open @endif>
        <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2"><span class="text-sm font-bold text-primary">Choose what goes into the APP</span><span class="flex items-center gap-2 text-xs text-on-surface-variant">{{ $appCandidateCount }} item(s) to choose from<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span></span></summary>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2"><p class="text-xs text-on-surface-variant">Only the items you tick are added. An item you remove from the APP comes back here to be chosen again.</p>@if($appCandidateCount > 0)<label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" data-app-pick-all class="h-4 w-4 rounded border-outline-variant">Select all</label>@endif</div>
        @forelse($sobPlans->where('status', 'approved')->where('fiscal_year', $year) as $candidatePlan)
            @php $candidateItems = $appCandidates->get($candidatePlan->id, collect()); @endphp
            @if($candidateItems->isNotEmpty())
            <p class="mt-3 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $candidatePlan->documentTitle() }}</p>
            <div class="mt-1 divide-y divide-outline-variant/15 rounded-lg border border-outline-variant/30 bg-white">
                @foreach($candidateItems as $candidate)
                    <label class="flex cursor-pointer items-start gap-3 px-3 py-2.5 text-sm"><input type="checkbox" name="sob_item_ids[]" value="{{ $candidate->id }}" data-app-pick class="mt-0.5 h-4 w-4 rounded border-outline-variant"><span class="min-w-0 flex-1"><span class="font-semibold">{{ $candidate->particulars }}</span><span class="block text-xs text-on-surface-variant">{{ $candidate->activity?->activity ?: 'Program' }} · {{ rtrim(rtrim(number_format((float) $candidate->frequency * (float) $candidate->quantity, 2), '0'), '.') }} {{ $candidate->unit }}</span></span><span class="text-xs font-semibold">{{ $peso($candidate->amount) }}</span></label>
                @endforeach
            </div>
            @endif
        @empty
            <p class="mt-3 text-xs text-on-surface-variant">No approved SOB yet for FY {{ $year }}. Approve a quarter of the SOB first.</p>
        @endforelse
        @if($appCandidateCount > 0)<div class="mt-3 flex justify-end"><button class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white">Add selected to the APP</button></div>@elseif($sobPlans->where('status', 'approved')->where('fiscal_year', $year)->isNotEmpty())<p class="mt-3 text-xs text-on-surface-variant">Every item of the approved SOBs is already in the APP.</p>@endif
        </details>
    </form>
    <script>document.querySelectorAll('a[href="#app-picker"]').forEach(function (link) { link.addEventListener('click', function () { document.querySelector('[data-app-picker-box]').open = true; }); });</script>
    <script>document.querySelector('[data-app-pick-all]')?.addEventListener('change', function () { document.querySelectorAll('[data-app-pick]').forEach(function (box) { box.checked = this.checked; }, this); });</script>
    @endif
    @if($appPlan)
        <div class="flex flex-wrap items-center justify-between gap-3 bg-surface-low/60 px-5 py-3"><span class="text-xs">APP status: <strong>{{ ucfirst($appPlan->status) }}</strong> · {{ $appPlan->items->count() }} item(s) · Planned total <strong>{{ $peso($appPlan->items->sum('estimated_total_cost')) }}</strong></span>@if(auth()->user()->hasPermission('planning.manage') && $appPlan->status !== 'approved')<form method="POST" action="{{ route('planning.app.approve', $appPlan) }}">@csrf<button class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Approve APP</button></form>@endif</div>
        <div class="flex flex-wrap items-start gap-3 border-b border-outline-variant/20 px-5 py-3">
            <a href="{{ route('planning.app.print', $appPlan) }}" target="_blank" rel="noopener" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">Print APP</a>
            @if(auth()->user()->hasPermission('planning.manage'))
            <div class="min-w-0 flex-1">
                <form method="POST" action="{{ route('planning.app.signatories', $appPlan) }}">@csrf @method('PUT')
                    @php $appSlots = \App\Support\PlanningSignatories::slots([['prepared_by', 'Prepared by', 'Prepared by', null, null, null, null], ['recommended_by', 'Recommending Approval', 'Recommending Approval', null, null, null, null], ['approved_by', 'Approved by', 'Approved by', null, null, null, null]], $appPlan, \App\Support\PlanningSignatories::lastUsed($appPlan->school_id, ['prepared_by', 'recommended_by', 'approved_by'])); @endphp
                    @include('partials.signatory-editor', ['slots' => $appSlots, 'editable' => true, 'document' => 'APP', 'saveLabel' => 'Save signatories'])
                </form>
            </div>
            @endif
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">Procurement Item</th><th class="px-4 py-3">Fund / Mode</th><th class="px-4 py-3 text-right">Planned Amount</th><th class="px-4 py-3">Requested (PR)</th><th class="px-5 py-3">Transaction / PR Status</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">@foreach(\App\Support\AppCategories::ALL as $categoryName)@php $categoryItems = $appPlan->items->filter(fn ($line) => \App\Support\AppCategories::of($line->category) === $categoryName); $catKey = \Illuminate\Support\Str::slug($categoryName); $catOpen = $appPlan->items->count() <= 10; @endphp @if($categoryItems->isNotEmpty())<tr class="cursor-pointer bg-primary text-white hover:bg-primary-container" data-app-cat="{{ $catKey }}" aria-expanded="{{ $catOpen ? 'true' : 'false' }}" tabindex="0" role="button"><td colspan="6" class="px-5 py-3"><span class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"><span class="flex items-center gap-3"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">flag</span><span><span class="block text-[10px] font-semibold uppercase tracking-widest text-white/70">Category</span><span class="block text-base font-semibold normal-case tracking-normal">{{ $categoryName }}</span></span></span><span class="flex items-center gap-2 text-xs font-normal normal-case tracking-normal text-white/80 max-sm:basis-full max-sm:pl-9">{{ $categoryItems->count() }} item(s) · {{ $peso($categoryItems->sum('estimated_total_cost')) }}<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span></span></span></td></tr>@foreach($categoryItems as $item)@php $transaction = $item->sobItem?->plan?->transaction ?? $item->ppmpItem?->plan?->transaction; $linkedPrs = $transaction?->procurementRequests ?? collect(); @endphp<tr data-app-cat-row="{{ $catKey }}" @if(! $catOpen) hidden @endif><td class="px-5 py-3"><span class="font-semibold">{{ $item->procurement_item }}</span><div class="mt-1 text-on-surface-variant">{{ $item->specifications ?: '—' }}</div></td><td class="px-4 py-3">{{ $item->fund_source ?: '—' }}<div class="mt-1 text-on-surface-variant">{{ $item->procurement_mode ?: 'Mode not set' }}</div></td><td class="px-4 py-3 text-right font-semibold">{{ $peso($item->estimated_total_cost) }}</td>@php $activeLines = $item->requestItems->filter(fn ($line) => $line->procurementRequest && ! in_array($line->procurementRequest->status, ['returned', 'rejected'])); @endphp<td class="px-4 py-3">{{ rtrim(rtrim(number_format((float) $activeLines->sum('quantity'), 2), '0'), '.') ?: '0' }} of {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}<div class="mt-1 text-on-surface-variant">@forelse($activeLines->pluck('procurementRequest')->unique('id') as $linkedPr)<a class="font-semibold text-primary underline" href="{{ route('procurement.print', $linkedPr) }}">{{ $linkedPr->request_number }}</a>@if(! $loop->last), @endif @empty No PR yet @endforelse</div></td><td class="px-5 py-3">@if($transaction)<a class="font-semibold text-primary underline" href="{{ route('transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a><div class="mt-1 text-on-surface-variant">{{ $linkedPrs->count() }} linked PR(s) · {{ str($transaction->status)->replace('_', ' ')->title() }}</div>@else<span class="text-on-surface-variant">No transaction link</span>@endif</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">@if(auth()->user()->hasPermission('planning.manage'))
                    @if($appPlan->status !== 'approved' && $activeLines->isEmpty())
                        <div class="flex items-center justify-end gap-3"><button type="button" data-edit-app-item data-edit-dialog="dlg-app-item" data-action="{{ route('planning.app.items.update', $item) }}" data-payload="{{ json_encode($item->only(['procurement_item', 'specifications', 'quantity', 'unit', 'estimated_unit_cost', 'procurement_mode', 'procurement_schedule', 'fund_source']) + ['category' => \App\Support\AppCategories::of($item->category)]) }}" class="text-[11px] font-semibold text-primary hover:underline">Edit</button><form method="POST" action="{{ route('planning.app.items.destroy', $item) }}" data-confirm="Remove '{{ $item->procurement_item }}' from the APP? Choose it again from the SOB items to bring it back.">@csrf @method('DELETE')<button class="text-[11px] font-semibold text-error hover:underline">Delete</button></form></div>
                    @else
                        <span class="text-[11px] font-semibold text-on-surface-variant/60" title="{{ $appPlan->status === 'approved' ? 'Approved plans are locked. Unlock the APP to edit its items.' : 'A Purchase Request already draws from this item.' }}">Locked</span>
                    @endif
                @endif</td></tr>@endforeach @endif @endforeach</tbody></table></div>
    <script>
        document.querySelectorAll('[data-app-cat]').forEach(function (head) {
            var toggle = function () {
                var open = head.getAttribute('aria-expanded') !== 'true';
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
                document.querySelectorAll('[data-app-cat-row="' + head.dataset.appCat + '"]').forEach(function (row) { row.hidden = !open; });
            };
            head.addEventListener('click', toggle);
            head.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(); } });
        });
    </script>
    @else<div class="px-5 py-8 text-center text-sm text-on-surface-variant">No APP yet for FY {{ $year }}. Approve an SOB, then choose its items for the APP.</div>@endif
    @if($appPlan && auth()->user()->hasPermission('planning.manage'))
    <details class="mx-5 mb-5 mt-4 rounded-lg border border-error/30 bg-error/5">
        <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center gap-2 px-4 py-3 text-xs font-bold uppercase tracking-wider text-error"><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span>Danger zone</summary>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-error/20 px-4 py-3">
            @if($appPlan->status === 'approved')
                <p class="text-xs text-on-surface-variant">Unlocking turns the approved APP back into a draft so its items can be edited or removed. You approve it again afterwards.</p>
                <form method="POST" action="{{ route('planning.app.unlock', $appPlan) }}" onsubmit="return confirm('Unlock this APP? It becomes a draft so its items can be edited or removed, and you approve it again afterwards.')">@csrf<button class="rounded-lg border border-error/50 bg-white px-4 py-2 text-xs font-semibold text-error hover:bg-error/10">Unlock APP</button></form>
            @else
                <p class="text-xs text-on-surface-variant">Deleting removes this draft APP and all of its items. Choose the SOB items again to build it.</p>
                <form method="POST" action="{{ route('planning.app.destroy', $appPlan) }}" onsubmit="return confirm('Delete this draft APP and all its items? Choose the SOB items again to build it.')">@csrf @method('DELETE')<button class="rounded-lg border border-error/50 bg-white px-4 py-2 text-xs font-semibold text-error hover:bg-error/10">Delete APP</button></form>
            @endif
        </div>
    </details>
    @endif

</section>

<section id="app-cse" class="mt-5 rounded-xl border border-outline-variant/40 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">
        @include('partials.planning-panel-head', ['icon' => 'inventory_2', 'title' => 'APP-CSE (Procurement Service form)', 'text' => 'The Annual Procurement Plan for Common Supplies and Equipment: the Procurement Service catalog with a quantity for each month, printed as the official form. It is a separate plan: it does not come from the SOB and does not change the APP above.', 'chips' => []])
        @if(auth()->user()->hasPermission('planning.manage'))
            <form id="app-cse-create" method="POST" action="{{ route('planning.app-cse.store') }}" class="flex items-end gap-2">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
                <label class="text-xs font-semibold text-on-surface-variant">Fiscal year<input type="number" name="fiscal_year" value="{{ $year }}" min="2000" max="2100" required class="mt-1 block w-24 rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm outline-none focus:border-primary"></label>
                <button class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white hover:bg-primary-container">Create APP-CSE</button>
            </form>
        @endif
    </div>
    <details class="group" data-app-cse-list @if($appCsePlans->count() <= 1) open @endif>
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2 border-b border-outline-variant/10 px-5 py-3 text-xs font-semibold text-on-surface-variant"><span>{{ $appCsePlans->count() }} APP-CSE plan(s)</span><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] text-on-surface-variant transition-transform" aria-hidden="true">expand_more</span></summary>
    @forelse($appCsePlans as $csePlan)
        <a href="{{ route('planning.app-cse.show', $csePlan) }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant/10 px-5 py-3 text-sm last:border-0 hover:bg-surface-low/60"><span class="font-semibold">APP-CSE FY {{ $csePlan->fiscal_year }}</span><span class="text-xs text-on-surface-variant">{{ $csePlan->items_count }} item{{ $csePlan->items_count === 1 ? '' : 's' }} · {{ ucfirst($csePlan->status) }}</span></a>
    @empty
        <div class="px-5 py-6 text-center text-sm text-on-surface-variant">No APP-CSE yet for this school.</div>
    @endforelse
    </details>
</section>
</div>

@if(auth()->user()->hasPermission('planning.manage'))
<div data-panel="settings" class="hidden">
<section class="rounded-xl border border-outline-variant/40 bg-white shadow-sm">
<div class="border-b border-outline-variant/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">@include('partials.planning-panel-head', ['icon' => 'tune', 'title' => 'Settings', 'text' => 'Fund sources and fiscal-year controls for this organization.', 'chips' => [$fundSources->count().' fund source'.($fundSources->count() === 1 ? '' : 's')]])</div>
    <div class="p-5">
        <div class="mb-4"><h2 class="font-semibold">Fund Sources & Fiscal Year</h2><p class="mt-1 text-xs text-on-surface-variant">These controls belong to {{ $selectedSchool->organization?->name ?? 'this organization' }}.</p></div>
        <form method="POST" action="{{ route('planning.funds.store') }}" class="mb-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <label class="text-xs font-semibold">Code<input name="code" required maxlength="50" placeholder="e.g. MOOE" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold">Fund Name<input name="name" required maxlength="255" placeholder="Maintenance and Other Operating Expenses" class="{{ $inputClass }}"></label>
            <button class="self-end rounded border border-primary px-4 py-2.5 text-xs font-semibold text-primary">Save Fund</button>
        </form>
        <div class="mb-5 flex flex-wrap gap-2">@forelse($fundSources as $fund)<span class="rounded bg-surface-low px-3 py-1.5 text-xs {{ $fund->is_active ? '' : 'opacity-50 line-through' }}">{{ $fund->code }} · {{ $fund->name }}</span>@empty<span class="text-xs text-on-surface-variant">No fund sources configured.</span>@endforelse</div>
        <form method="POST" action="{{ route('planning.fiscal-year.status') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <label class="text-xs font-semibold">Year<input type="number" name="year" required value="{{ $year }}" min="2000" max="2100" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold">Status<select name="status" class="{{ $inputClass }}"><option value="open">Open</option><option value="closed">Closed</option></select></label>
            <button class="self-end rounded border border-primary px-4 py-2.5 text-xs font-semibold text-primary">Update</button>
        </form>
        <div class="mt-3 flex flex-wrap gap-2">@foreach($fiscalYears as $fiscalYear)<span class="rounded px-2.5 py-1 text-[11px] {{ $fiscalYear->isOpen() ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error' }}">FY {{ $fiscalYear->year }} · {{ ucfirst($fiscalYear->status) }}</span>@endforeach</div>
    </div>
</section>
</div>
@endif

@if(auth()->user()->hasPermission('planning.manage'))
    @include('partials.planning-edit-dialogs')
@endif

@push('scripts')
<script>
(() => {
    const tabs = [...document.querySelectorAll('[data-tab]')];
    const panels = [...document.querySelectorAll('[data-panel]')];
    const hint = document.getElementById('planning-hint');
    const names = tabs.map((tab) => tab.dataset.tab);
    const store = {
        get() { try { return sessionStorage.getItem('planning.tab'); } catch (e) { return null; } },
        set(value) { try { sessionStorage.setItem('planning.tab', value || ''); } catch (e) {} },
    };
    function show(name) {
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.panel !== name));
        tabs.forEach((tab) => {
            const on = tab.dataset.tab === name;
            tab.classList.toggle('border-primary', on);
            tab.classList.toggle('ring-2', on);
            tab.classList.toggle('ring-primary/30', on);
            tab.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        hint.classList.toggle('hidden', Boolean(name));
        document.getElementById('planning-glance')?.classList.toggle('hidden', Boolean(name));
        document.querySelector('[data-overview]')?.toggleAttribute('hidden', ! name);
        store.set(name);
        history.replaceState(null, '', name ? '#' + name : location.pathname + location.search);
    }
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.classList.contains('ring-2') ? '' : tab.dataset.tab)));
    document.querySelector('[data-overview]')?.addEventListener('click', () => show(''));
    document.querySelector('[data-goto="app-cse"]')?.addEventListener('click', () => { show('app'); document.getElementById('app-cse')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    document.querySelectorAll('[data-toggle-form]').forEach((button) => {
        button.dataset.label = button.textContent;
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-form="' + button.dataset.toggleForm + '"]');
            const display = form.dataset.display || 'block';
            const opening = form.classList.contains('hidden');
            form.classList.toggle('hidden', !opening);
            form.classList.toggle(display, opening);
            button.textContent = opening ? 'Close form' : button.dataset.label;
        });
    });
    const fromHash = location.hash.slice(1);
    show(names.includes(fromHash) ? fromHash : (names.includes(store.get()) ? store.get() : ''));
})();
</script>
@endpush
@endsection
