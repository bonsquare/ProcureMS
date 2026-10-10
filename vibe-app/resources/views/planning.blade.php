@extends('layouts.budget')

@section('title', 'Planning')
@section('section', 'Planning')
@section('crumb', 'SIP · AIP · SOB · APP')

@section('content')
@php
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary';
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
@endphp
<div class="mb-7 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary"><span class="material-symbols-outlined text-base">account_tree</span> Integrated School Planning</div>
        <h1 class="text-3xl font-semibold tracking-tight">Planning & Procurement Programs</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Connect SIP priorities to AIP funding, the quarterly School Operating Budget, APP approval, and transaction history.</p>
    </div>
    <form method="GET" action="{{ route('planning') }}" class="flex flex-wrap gap-2 rounded border border-outline-variant/40 bg-white p-3">
        <label class="text-xs font-semibold text-on-surface-variant">School
            <select name="school_id" class="mt-1 min-w-52 rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm" onchange="this.form.submit()">
                @foreach($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchool->id === $school->id)>{{ $school->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-on-surface-variant">Fiscal Year
            <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="mt-1 w-28 rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm">
        </label>
        <button class="self-end rounded bg-primary px-4 py-2 text-xs font-semibold text-white">View</button>
    </form>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<section id="dashboard" class="mb-6">
    @php
        // One line per step of the planning flow: SIP -> AIP -> SOB -> APP, with what exists for the selected school and year.
        $latestSipYear = $sipProjects->max('school_year');
        $sipNow = $sipProjects->where('school_year', $latestSipYear);
        $sipActivities = $sipNow->flatMap->activities;
        $sipYearTotals = [$sipActivities->sum('financial_year1'), $sipActivities->sum('financial_year2'), $sipActivities->sum('financial_year3')];
        $sipTotal = array_sum($sipYearTotals);
        $sipPeak = max(1, max($sipYearTotals));
        $pillarCounts = $sipNow->groupBy(fn ($p) => $p->pillar ?: 'Unassigned')->map->count();
        $pillarTones = ['Access' => 'bg-sky-100 text-sky-800', 'Equity' => 'bg-teal-100 text-teal-800', 'Quality' => 'bg-violet-100 text-violet-800', 'Resiliency' => 'bg-orange-100 text-orange-800', 'Well-Being' => 'bg-emerald-100 text-emerald-800', 'Enabling Mechanism' => 'bg-amber-100 text-amber-800'];

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
            'app' => $appPlan ? ['Approve the APP for FY '.$year, 'The approved APP is the source of every Purchase Request.'] : ['Generate the APP for FY '.$year, 'Collects the items of the approved SOBs into the Annual Procurement Plan.'],
        ];
        $stateStyle = [
            'done' => ['bg-secondary/10 text-secondary', 'check_circle', 'border-secondary/40'],
            'current' => ['bg-amber-100 text-amber-800', 'pending', 'border-amber-300'],
            'todo' => ['bg-surface-high text-on-surface-variant', 'radio_button_unchecked', 'border-outline-variant/50'],
        ];
    @endphp

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

    {{-- The flow: SIP -> AIP -> SOB -> APP --}}
    <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr_auto_auto] xl:items-stretch xl:gap-2">
        @foreach($steps as $n => [$key, $icon, $title, $short, $line, $sub, $state, $stateLabel])
            @php [$chip, $stateIcon, $edge] = $stateStyle[$state]; @endphp
            <li class="contents">
                <button type="button" data-tab="{{ $key }}" aria-label="{{ $title }}: {{ $stateLabel }}" class="group flex h-full flex-col rounded-xl border-2 {{ $edge }} bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ $icon }}</span></span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $chip }}"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $stateIcon }}</span>{{ $stateLabel }}</span>
                    </div>
                    <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Step {{ $n + 1 }} · {{ $short }}</p>
                    <p class="mt-0.5 text-sm font-semibold leading-snug">{{ $title }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-primary">{{ $line }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ $sub }}</p>
                </button>
                @if($n < 3)<span class="hidden items-center justify-center text-outline-variant xl:flex" aria-hidden="true"><span class="material-symbols-outlined text-[26px]">arrow_forward</span></span>@endif
            </li>
        @endforeach
        @if(auth()->user()->hasPermission('planning.manage'))
            <li class="contents">
                <span class="hidden w-px bg-outline-variant/40 xl:block" aria-hidden="true"></span>
                <button type="button" data-tab="settings" class="group flex flex-col items-start justify-between rounded-xl border-2 border-dashed border-outline-variant/60 bg-surface-low/60 p-4 text-left transition hover:border-primary hover:bg-white xl:w-36">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-on-surface-variant ring-1 ring-outline-variant/40"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">tune</span></span>
                    <span class="mt-3"><span class="block text-sm font-semibold">Settings</span><span class="mt-0.5 block text-xs text-on-surface-variant">{{ $fundSources->count() }} fund source(s) · fiscal year</span></span>
                </button>
            </li>
        @endif
    </ol>

    {{-- SIP at a glance: the three-year budget and the programs per pillar --}}
    @if($sipNow->isNotEmpty())
        <div class="mt-4 grid gap-3 lg:grid-cols-[1.1fr_1fr]">
            <div class="rounded-xl border border-outline-variant/40 bg-white p-4 shadow-sm">
                <div class="flex items-baseline justify-between"><p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">SIP financial target · {{ $latestSipYear }}-{{ $latestSipYear + 2 }}</p><p class="text-sm font-semibold text-primary">{{ $peso($sipTotal) }}</p></div>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    @foreach($sipYearTotals as $i => $amount)
                        <div><div class="flex h-20 items-end rounded-lg bg-surface-low px-2"><div class="w-full rounded-t bg-gradient-to-t from-primary to-action" style="height: {{ max(6, round($amount / $sipPeak * 100)) }}%" title="{{ $peso($amount) }}"></div></div><p class="mt-1.5 text-center text-[11px] font-bold text-on-surface-variant">Year {{ $i + 1 }}<span class="block text-xs font-semibold text-on-surface">{{ $peso($amount) }}</span></p></div>
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
    @endif
</section>

<div data-panel="sip" class="hidden">
<datalist id="sip-funds">
    <option value="Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE"></option>
    <option value="MOOE"></option><option value="SEF"></option><option value="IGP"></option><option value="PTA Fund"></option><option value="BLGU"></option><option value="Provincial Government"></option><option value="Municipal SEF"></option><option value="Others"></option>
</datalist>
@php $sipGroups = $sipProjects->groupBy('school_year')->sortKeysDesc(); @endphp
<section class="mb-6 rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-4">
        <div><h2 class="font-semibold">School Improvement Plan (SIP)</h2><p class="mt-1 text-xs text-on-surface-variant">Three-year plan: each program lists its activities with Year 1-3 physical and financial targets. Print it on the official SIP form.</p></div>
        @if(auth()->user()->hasPermission('planning.manage'))
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('planning.sip.import.preview') }}" enctype="multipart/form-data" class="inline-flex">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><label class="cursor-pointer rounded border border-primary px-4 py-2 text-xs font-semibold text-primary hover:bg-primary hover:text-white" title="Upload the school's SIP Excel file (.xlsx), check it, then confirm"><span class="material-symbols-outlined mr-1 align-middle text-[16px]">upload_file</span>Import from Excel<input type="file" name="file" accept=".xlsx" class="sr-only" onchange="this.form.submit()"></label></form>
            <button type="button" data-toggle-form="sip" class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">+ Add SIP Program</button>
        </div>
        @endif
    </div>
    @if(auth()->user()->hasPermission('planning.manage'))
    <form data-form="sip" data-display="grid" method="POST" action="{{ route('planning.sip.store') }}" class="hidden gap-3 border-b border-outline-variant/20 bg-surface-low/60 p-5 sm:grid-cols-2 lg:grid-cols-3">@csrf
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <label class="text-xs font-semibold">Plan Start Year (Year 1)<input required type="number" name="school_year" value="{{ $year }}" min="2000" max="2100" class="{{ $inputClass }}" title="The SIP covers this year and the next two, e.g. 2026 means FY 2026-2028."></label>
        <label class="text-xs font-semibold">Pillar / Enabling Mechanism<select required name="pillar" class="{{ $inputClass }}"><option value="">Select pillar</option>@foreach(\App\Models\Aip::PILLARS as $pillar)<option>{{ $pillar }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold">KRA<input required name="kra" maxlength="255" placeholder="e.g. KRA 3: Learner Formation and Development" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold sm:col-span-2 lg:col-span-3">DepEd Organizational Outcomes<textarea name="organizational_outcome" rows="2" maxlength="1000" placeholder="e.g. Percentage of School-age Children in School - Net Enrollment Rate (NER) in Elementary and 6-Year Target" class="{{ $inputClass }}"></textarea></label>
        <label class="text-xs font-semibold">Strategy (Processes)<input name="strategy" maxlength="255" placeholder="e.g. Learner Support Management" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">5-Point Agenda<input name="five_point_agenda" maxlength="255" placeholder="e.g. Enhanced Governance structure..." class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Specific Program / Project<input required name="project" maxlength="255" placeholder="e.g. Papel mo Kinabukasan Ko!" class="{{ $inputClass }}"></label>
        <div class="sm:col-span-2 lg:col-span-3"><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white">Save SIP Program</button> <span class="ml-2 text-xs text-on-surface-variant">Add its activities after saving.</span></div>
    </form>
    @endif

    @forelse($sipGroups as $startYear => $programs)
    <div class="border-b border-outline-variant/20 bg-surface-low/60 px-5 py-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><span class="text-sm font-semibold">SIP FY {{ $startYear }}-{{ $startYear + 2 }}</span><span class="ml-2 text-xs text-on-surface-variant">{{ $programs->count() }} program(s) · Total {{ $peso($programs->sum('estimated_budget')) }}</span></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('planning.sip.print', ['school_id' => $selectedSchool->id, 'start_year' => $startYear]) }}" target="_blank" rel="noopener" class="rounded bg-secondary px-3 py-2 text-xs font-semibold text-white">Print SIP</a>
                @if(auth()->user()->hasPermission('planning.manage'))<button type="button" data-toggle-form="sip-sign-{{ $startYear }}" class="rounded border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold">Signatories</button>@endif
            </div>
        </div>
        <div class="mt-3 rounded-lg border border-outline-variant/40 bg-white p-3">
            <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Break into AIP</p><p class="text-[11px] text-on-surface-variant">Each year of the SIP becomes the AIP of that fiscal year. The quarterly amounts are split evenly until the SOB template is provided.</p></div>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                @foreach([1, 2, 3] as $yearNo)
                    @php $fy = $startYear + $yearNo - 1; $yearAip = $aips->firstWhere('fiscal_year', $fy); $yearTotal = $programs->sum(fn ($p) => $p->activities->sum('financial_year'.$yearNo)); @endphp
                    <div class="rounded-lg border p-3 {{ $yearAip ? 'border-secondary/40 bg-secondary/5' : 'border-outline-variant/50 bg-surface-low/60' }}">
                        <div class="flex items-center justify-between gap-2"><p class="text-xs font-bold">Year {{ $yearNo }} · FY {{ $fy }}</p>@if($yearAip)<span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $yearAip->status === 'approved' ? 'bg-secondary/15 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($yearAip->status) }}</span>@endif</div>
                        <p class="mt-0.5 text-sm font-semibold text-primary">{{ $peso($yearTotal) }}</p>
                        @if($yearAip)
                            <a href="{{ route('aip.show', $yearAip) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">AIP FY {{ $fy }}: Open / Edit<span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span></a>
                        @elseif(auth()->user()->hasPermission('planning.manage') && auth()->user()->canManageBudget())
                            <form method="POST" action="{{ route('planning.sip.generate-aip') }}" class="mt-2">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="start_year" value="{{ $startYear }}"><input type="hidden" name="year_no" value="{{ $yearNo }}"><button class="inline-flex items-center gap-1 rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">event_note</span>Create AIP FY {{ $fy }}</button></form>
                        @else
                            <p class="mt-2 text-[11px] text-on-surface-variant">No AIP yet</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @if(auth()->user()->hasPermission('planning.manage'))
        @php $signPlan = $sipPlans[$startYear] ?? null; @endphp
        <form data-form="sip-sign-{{ $startYear }}" data-display="grid" method="POST" action="{{ route('planning.sip.signatories') }}" class="mt-3 hidden gap-3 rounded border border-outline-variant/30 bg-white p-4 md:grid-cols-3">@csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="start_year" value="{{ $startYear }}">
            @foreach([['prepared_by', 'Prepared by', $selectedSchool->school_head, 'School Head/Team Leader'], ['recommended_by', 'Recommending Approval', null, 'Chief, School Governance Operation Division'], ['approved_by', 'Approved by', null, 'Schools Division Superintendent']] as [$key, $label, $defaultName, $defaultPosition])
            <div class="space-y-2"><p class="text-xs font-semibold">{{ $label }}</p>
                <input name="{{ $key }}_name" maxlength="255" placeholder="Full name" value="{{ $signPlan?->{$key.'_name'} ?? $defaultName }}" class="{{ $inputClass }}">
                <input name="{{ $key }}_position" maxlength="255" placeholder="Position" value="{{ $signPlan?->{$key.'_position'} ?? $defaultPosition }}" class="{{ $inputClass }}"></div>
            @endforeach
            <div class="md:col-span-3"><button class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white">Save Signatories</button></div>
        </form>
        @endif
    </div>
    <div class="divide-y divide-outline-variant/20">
        @foreach($programs as $sip)
        <div class="px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold">{{ $sip->project }}</h3>@if($sip->pillar)<span class="rounded bg-primary/10 px-2 py-1 text-[10px] font-semibold text-primary">{{ $sip->pillar }}</span>@endif @if($sip->transaction)<a class="text-[11px] font-semibold text-primary underline" href="{{ route('transactions.show', $sip->transaction) }}">{{ $sip->transaction->transaction_number }}</a>@endif</div>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ $sip->kra ?: 'No KRA entered' }}@if($sip->strategy) · {{ $sip->strategy }}@endif</p>
                    @if($sip->organizational_outcome)<p class="mt-1 text-xs text-on-surface-variant">{{ $sip->organizational_outcome }}</p>@endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(auth()->user()->hasPermission('planning.manage'))<button type="button" data-toggle-form="sip-act-{{ $sip->id }}" class="rounded bg-primary px-3 py-2 text-xs font-semibold text-white">+ Add Activity</button>@endif
                    @if(auth()->user()->hasPermission('planning.manage'))
                        <button type="button" data-edit-sip data-edit-dialog="dlg-sip" data-action="{{ route('planning.sip.update', $sip) }}" data-payload="{{ json_encode($sip->only(['school_year', 'planning_period', 'pillar', 'kra', 'organizational_outcome', 'strategy', 'five_point_agenda', 'project'])) }}" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">edit</span>Edit</button>
                        <form method="POST" action="{{ route('planning.sip.destroy', $sip) }}" data-confirm="Delete the program '{{ $sip->project }}' and its {{ $sip->activities->count() }} activities? This cannot be undone.">@csrf @method('DELETE')<button class="inline-flex items-center gap-1 rounded border border-error/40 bg-white px-3 py-2 text-xs font-semibold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">delete</span>Delete</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('planning.manage') && $aips->whereNull('sip_project_id')->isNotEmpty())<form method="POST" action="{{ route('planning.sip.link-aip', $sip) }}" class="flex gap-2">@csrf<select required name="aip_id" class="max-w-44 rounded border border-outline-variant/50 bg-white px-2 py-2 text-xs"><option value="">Link AIP</option>@foreach($aips->whereNull('sip_project_id') as $aip)<option value="{{ $aip->id }}">FY {{ $aip->fiscal_year }} · {{ ucfirst($aip->status) }}</option>@endforeach</select><button class="rounded border border-primary px-3 py-2 text-xs font-semibold text-primary">Link</button></form>@endif
                </div>
            </div>
            @if(auth()->user()->hasPermission('planning.manage'))
            <form data-form="sip-act-{{ $sip->id }}" data-display="grid" method="POST" action="{{ route('planning.sip.activities.store', $sip) }}" class="mt-3 hidden gap-3 rounded border border-outline-variant/30 bg-surface-low/60 p-4 sm:grid-cols-3 lg:grid-cols-6">@csrf
                <label class="text-xs font-semibold sm:col-span-3 lg:col-span-6">Activity<input required name="activity" maxlength="1000" class="{{ $inputClass }}"></label>
                @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Physical Y{{ $y }}<input type="number" min="0" step="0.01" name="physical_year{{ $y }}" class="{{ $inputClass }}"></label>@endforeach
                @foreach([1, 2, 3] as $y)<label class="text-xs font-semibold">Financial Y{{ $y }} (₱)<input type="number" min="0" step="0.01" name="financial_year{{ $y }}" class="{{ $inputClass }}"></label>@endforeach
                <label class="text-xs font-semibold sm:col-span-3">Source of Fund<input name="source_of_fund" list="sip-funds" maxlength="255" class="{{ $inputClass }}"></label>
                <label class="text-xs font-semibold sm:col-span-3">Responsible Person<input name="responsible_person" maxlength="1000" placeholder="e.g. School Head, Teachers, PTA Officials" class="{{ $inputClass }}"></label>
                <label class="text-xs font-semibold sm:col-span-3 lg:col-span-6">Remarks (Important Notes)<input name="remarks" maxlength="1000" class="{{ $inputClass }}"></label>
                <div class="sm:col-span-3 lg:col-span-6"><button class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white">Save Activity</button></div>
            </form>
            @endif
            @if($sip->activities->isNotEmpty())
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs"><thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-3 py-2">Activity</th><th class="px-2 py-2 text-center">Physical Y1 / Y2 / Y3</th><th class="px-2 py-2 text-right">Financial Y1</th><th class="px-2 py-2 text-right">Y2</th><th class="px-2 py-2 text-right">Y3</th><th class="px-3 py-2">Source of Fund</th><th class="px-3 py-2">Responsible</th><th class="px-2 py-2"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">
                @foreach($sip->activities as $act)
                <tr><td class="px-3 py-2">{{ $act->activity }}</td><td class="px-2 py-2 text-center tabular-nums">{{ (float) $act->physical_year1 ?: '–' }} / {{ (float) $act->physical_year2 ?: '–' }} / {{ (float) $act->physical_year3 ?: '–' }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year1) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year2) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($act->financial_year3) }}</td><td class="px-3 py-2">{{ $act->source_of_fund ?: '—' }}</td><td class="px-3 py-2">{{ $act->responsible_person ?: '—' }}</td>
                    <td class="whitespace-nowrap px-2 py-2 text-right">@if(auth()->user()->hasPermission('planning.manage'))<div class="flex items-center justify-end gap-3"><button type="button" data-edit-activity data-edit-dialog="dlg-activity" data-action="{{ route('planning.sip.activities.update', $act) }}" data-payload="{{ json_encode($act->only(['activity', 'physical_year1', 'physical_year2', 'physical_year3', 'financial_year1', 'financial_year2', 'financial_year3', 'source_of_fund', 'responsible_person', 'remarks'])) }}" class="text-[11px] font-semibold text-primary hover:underline">Edit</button><form method="POST" action="{{ route('planning.sip.activities.destroy', $act) }}" data-confirm="Delete this activity? This cannot be undone.">@csrf @method('DELETE')<button class="text-[11px] font-semibold text-error hover:underline">Delete</button></form></div>@endif</td></tr>
                @endforeach
                <tr class="bg-surface-low/60 font-semibold"><td class="px-3 py-2" colspan="2">Program total</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year1')) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year2')) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ $peso($sip->activities->sum('financial_year3')) }}</td><td colspan="3"></td></tr>
            </tbody></table></div>
            @else<p class="mt-3 rounded border border-dashed border-outline-variant/50 px-3 py-3 text-center text-xs text-on-surface-variant">No activities yet. Add the activities with their Year 1-3 targets.</p>@endif
        </div>
        @endforeach
    </div>
    @empty
    <div class="px-5 py-8 text-center text-sm text-on-surface-variant">No SIP programs saved for this school yet.</div>
    @endforelse
</section>
</div>

<div data-panel="aip" class="hidden">
<section id="aip" class="mb-6 rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-4"><div><h2 class="font-semibold">Annual Implementation Plan</h2><p class="mt-1 text-xs text-on-surface-variant">Yearly activities and financial targets per quarter and fund. An approved AIP is used for reports and to start a PPMP.</p></div>
        @if(auth()->user()->canManageBudget())
        <button type="button" data-toggle-form="aip" class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">+ New AIP</button>
        <form data-form="aip" data-display="flex" method="POST" action="{{ route('aip.store') }}" class="hidden w-full flex-wrap items-center justify-end gap-2 border-t border-outline-variant/20 pt-3">@csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <label class="flex items-center gap-2 text-xs font-semibold text-on-surface-variant">Fiscal Year<input type="number" name="fiscal_year" value="{{ $year }}" min="2000" max="2100" required class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></label>
            <button class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Create AIP</button>
        </form>
        @endif
    </div>
    <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-xs"><thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-5 py-3">Fiscal Year</th><th class="px-4 py-3 text-right">Activities</th><th class="px-4 py-3 text-right">Total Financial Target</th><th class="px-4 py-3">SIP</th><th class="px-4 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @forelse($aips as $aip)
        <tr><td class="px-5 py-3 font-semibold">FY {{ $aip->fiscal_year }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $aip->activities->count() }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $peso($aip->activities->sum(fn ($a) => $a->total)) }}</td><td class="px-4 py-3">@if($aip->sip_start_year)<span class="font-semibold text-primary">from SIP {{ $aip->sip_start_year }}-{{ $aip->sip_start_year + 2 }} · Year {{ $aip->sip_year_no }}</span>@else{{ $aip->sipProject?->project ?: '—' }}@endif</td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $aip->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($aip->status) }}</span></td><td class="px-5 py-3 text-right font-semibold"><a href="{{ route('aip.show', $aip) }}" class="text-primary hover:underline">Open / Edit</a> · <a href="{{ route('aip.print', $aip) }}" target="_blank" rel="noopener" class="text-primary hover:underline">Print</a>
            @if(auth()->user()->canManageBudget())
                @if($aip->status === 'draft' && $ppmpPlans->where('aip_id', $aip->id)->isEmpty() && $sobPlans->where('aip_id', $aip->id)->isEmpty())
                    · <form method="POST" action="{{ route('aip.destroy', $aip) }}" class="inline" data-delete-aip onsubmit="return confirm('Delete the AIP for FY {{ $aip->fiscal_year }}? Its KRAs and activities are removed. This cannot be undone.')">@csrf @method('DELETE')<button class="font-semibold text-error hover:underline">Delete</button></form>
                @else
                    · <span class="cursor-not-allowed font-semibold text-on-surface-variant/50" title="Only a draft AIP with no SOB, PPMP or budget built on it can be deleted.">Delete</span>
                @endif
            @endif</td></tr>
        @empty<tr><td colspan="6" class="px-5 py-8 text-center text-on-surface-variant">No AIP for this school yet.@if(auth()->user()->canManageBudget()) Enter the fiscal year and click New AIP.@endif</td></tr>@endforelse
    </tbody></table></div>
</section>

</div>

<div data-panel="sob" class="hidden">
<section class="mb-6 rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/20 px-5 py-4"><h2 class="font-semibold">School Operating Budget (SOB) · FY {{ $year }}</h2><p class="mt-1 text-xs text-on-surface-variant">One SOB for each quarter. Pick the AIP activities that have an actual budget in the quarter and add their items. Approving a quarter creates its Budget allotments.</p></div>
    @php $aipApproved = $aips->where('fiscal_year', $year)->where('status', 'approved')->isNotEmpty(); @endphp
    @unless($aipApproved)<div class="border-b border-outline-variant/20 bg-amber-50 px-5 py-3 text-xs text-amber-900">Approve the AIP for FY {{ $year }} first. The SOB is built from it.</div>@endunless
    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach(\App\Models\SobPlan::QUARTERS as $quarter => $quarterLabel)
            @php $quarterPlans = $sobYear->where('quarter', $quarter); @endphp
            <div class="flex flex-col rounded-lg border border-outline-variant/40 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-primary">{{ $quarterLabel }}</h3>
                @forelse($quarterPlans as $sob)
                    <div class="mt-3 rounded border border-outline-variant/30 bg-surface-low/50 p-3">
                        <div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold">{{ $sob->fund_source }}</span><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sob->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ $sob->status === 'approved' ? 'Approved' : 'Draft' }}</span></div>
                        <p class="mt-1 text-lg font-semibold text-primary">{{ $peso($sob->items->sum('amount')) }}</p>
                        <p class="text-xs text-on-surface-variant">{{ $sob->items->count() }} item{{ $sob->items->count() === 1 ? '' : 's' }}</p>
                        <a href="{{ route('planning.sob.show', $sob) }}" class="mt-2 inline-block rounded border border-primary px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Open</a>
                    </div>
                @empty
                    <p class="mt-3 text-xs text-on-surface-variant">Not started.</p>
                @endforelse
                @if(auth()->user()->hasPermission('planning.manage') && $aipApproved)
                    <form method="POST" action="{{ route('planning.sob.store') }}" class="mt-3 space-y-2">@csrf
                        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="fiscal_year" value="{{ $year }}"><input type="hidden" name="quarter" value="{{ $quarter }}">
                        <label class="block text-[11px] font-semibold text-on-surface-variant">Fund<input name="fund_source" value="MOOE" maxlength="100" class="{{ $inputClass }}"></label>
                        <button class="w-full rounded bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-container">Create SOB</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</section>
</div>

<div data-panel="ppmp" class="hidden">
<section class="mb-6 rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-4"><div><h2 class="font-semibold">PPMP</h2><p class="mt-1 text-xs text-on-surface-variant">Draft each item against an approved AIP; approve the plan before adding it to APP.</p></div>@if(auth()->user()->hasPermission('planning.manage'))<button type="button" data-toggle-form="ppmp" class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">+ New PPMP Item</button>@endif</div>
    @if(auth()->user()->hasPermission('planning.manage'))
    <form data-form="ppmp" data-display="grid" method="POST" action="{{ route('planning.ppmp.store') }}" class="hidden gap-3 border-b border-outline-variant/20 bg-surface-low/60 p-5 md:grid-cols-3">@csrf
        <label class="text-xs font-semibold md:col-span-1">Approved AIP<select required name="aip_id" class="{{ $inputClass }}"><option value="">Select AIP</option>@foreach($aips->where('status', 'approved') as $aip)<option value="{{ $aip->id }}">FY {{ $aip->fiscal_year }} · {{ $aip->entity }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold md:col-span-2">Project Title<input name="project_title" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Procurement Item<input name="procurement_item" required maxlength="255" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Specifications<input name="specifications" maxlength="2000" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Fund Source<select name="fund_source" class="{{ $inputClass }}"><option value="">Select fund source</option>@foreach($fundOptions as $fund)<option value="{{ $fund }}">{{ $fund }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold">Quantity<input type="number" name="quantity" required min="0.01" step="0.01" value="1" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Unit<input name="unit" required maxlength="50" value="lot" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Estimated Unit Cost<input type="number" name="estimated_unit_cost" required min="0" step="0.01" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Procurement Mode<input name="procurement_mode" maxlength="100" placeholder="e.g. Small Value Procurement" class="{{ $inputClass }}"></label>
        <label class="text-xs font-semibold">Schedule<input name="procurement_schedule" maxlength="255" placeholder="e.g. Q1" class="{{ $inputClass }}"></label>
        <div class="self-end"><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white">Save PPMP Draft</button></div>
    </form>
    @endif
    <div class="divide-y divide-outline-variant/20">
        @forelse($ppmpPlans as $plan)<div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold">{{ $plan->project_title }}</h3><span class="rounded px-2 py-1 text-[10px] {{ $plan->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-surface-low text-on-surface-variant' }}">{{ ucfirst($plan->status) }}</span></div><p class="mt-1 text-xs text-on-surface-variant">FY {{ $plan->fiscal_year }} · {{ $plan->items->count() }} item(s) · {{ $peso($plan->items->sum('estimated_total_cost')) }}</p></div><div class="flex gap-3">@if($plan->transaction)<a href="{{ route('transactions.show', $plan->transaction) }}" class="self-center text-xs font-semibold text-primary underline">{{ $plan->transaction->transaction_number }}</a>@endif @if(auth()->user()->hasPermission('planning.manage') && $plan->status !== 'approved')<form method="POST" action="{{ route('planning.ppmp.approve', $plan) }}">@csrf<button class="rounded bg-secondary px-3 py-2 text-xs font-semibold text-white">Approve PPMP</button></form>@endif
            @if(auth()->user()->hasPermission('planning.manage'))
                @if($plan->status !== 'approved')
                    @php $planItem = $plan->items->count() === 1 ? $plan->items->first() : null; @endphp
                    <button type="button" data-edit-ppmp data-edit-dialog="dlg-ppmp" data-action="{{ route('planning.ppmp.update', $plan) }}" data-payload="{{ json_encode($plan->only(['project_title', 'procurement_mode', 'procurement_schedule', 'fund_source']) + ['single_item' => (bool) $planItem] + ($planItem ? $planItem->only(['procurement_item', 'specifications', 'quantity', 'unit', 'estimated_unit_cost']) : [])) }}" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">edit</span>Edit</button>
                    <form method="POST" action="{{ route('planning.ppmp.destroy', $plan) }}" data-confirm="Delete the PPMP draft '{{ $plan->project_title }}' and its items? This cannot be undone.">@csrf @method('DELETE')<button class="inline-flex items-center gap-1 rounded border border-error/40 bg-white px-3 py-2 text-xs font-semibold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">delete</span>Delete</button></form>
                @else
                    <span class="self-center text-[11px] font-semibold text-on-surface-variant/60" title="Approved plans are locked. An approved PPMP already feeds the APP.">Approved plans are locked</span>
                @endif
            @endif</div></div>@empty<div class="px-5 py-7 text-center text-sm text-on-surface-variant">No PPMP records yet.</div>@endforelse
    </div>
</section>

</div>

<div data-panel="app" class="hidden">
<section class="rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-4"><div><h2 class="font-semibold">Annual Procurement Plan · FY {{ $year }}</h2><p class="mt-1 text-xs text-on-surface-variant">Built from the approved SOBs. Linked PR activity is visible per transaction.</p></div>@if(auth()->user()->hasPermission('planning.manage'))<form method="POST" action="{{ route('planning.app.generate') }}" class="flex gap-2">@csrf<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><input type="hidden" name="fiscal_year" value="{{ $year }}"><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white">Generate / Refresh APP</button></form>@endif</div>
    @if($appPlan)
        <div class="flex flex-wrap items-center justify-between gap-3 bg-surface-low/60 px-5 py-3"><span class="text-xs">APP status: <strong>{{ ucfirst($appPlan->status) }}</strong> · {{ $appPlan->items->count() }} item(s) · Planned total <strong>{{ $peso($appPlan->items->sum('estimated_total_cost')) }}</strong></span>@if(auth()->user()->hasPermission('planning.manage') && $appPlan->status !== 'approved')<form method="POST" action="{{ route('planning.app.approve', $appPlan) }}">@csrf<button class="rounded bg-secondary px-3 py-2 text-xs font-semibold text-white">Approve APP</button></form>@endif</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs"><thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-5 py-3">Procurement Item</th><th class="px-4 py-3">Fund / Mode</th><th class="px-4 py-3 text-right">Planned Amount</th><th class="px-4 py-3">Requested (PR)</th><th class="px-5 py-3">Transaction / PR Status</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-outline-variant/20">@foreach($appPlan->items as $item)@php $transaction = $item->sobItem?->plan?->transaction ?? $item->ppmpItem?->plan?->transaction; $linkedPrs = $transaction?->procurementRequests ?? collect(); @endphp<tr><td class="px-5 py-3"><span class="font-semibold">{{ $item->procurement_item }}</span><div class="mt-1 text-on-surface-variant">{{ $item->specifications ?: '—' }}</div></td><td class="px-4 py-3">{{ $item->fund_source ?: '—' }}<div class="mt-1 text-on-surface-variant">{{ $item->procurement_mode ?: 'Mode not set' }}</div></td><td class="px-4 py-3 text-right font-semibold">{{ $peso($item->estimated_total_cost) }}</td>@php $activeLines = $item->requestItems->filter(fn ($line) => $line->procurementRequest && ! in_array($line->procurementRequest->status, ['returned', 'rejected'])); @endphp<td class="px-4 py-3">{{ rtrim(rtrim(number_format((float) $activeLines->sum('quantity'), 2), '0'), '.') ?: '0' }} of {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}<div class="mt-1 text-on-surface-variant">@forelse($activeLines->pluck('procurementRequest')->unique('id') as $linkedPr)<a class="font-semibold text-primary underline" href="{{ route('procurement.print', $linkedPr) }}">{{ $linkedPr->request_number }}</a>@if(! $loop->last), @endif @empty No PR yet @endforelse</div></td><td class="px-5 py-3">@if($transaction)<a class="font-semibold text-primary underline" href="{{ route('transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a><div class="mt-1 text-on-surface-variant">{{ $linkedPrs->count() }} linked PR(s) · {{ str($transaction->status)->replace('_', ' ')->title() }}</div>@else<span class="text-on-surface-variant">No transaction link</span>@endif</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">@if(auth()->user()->hasPermission('planning.manage'))
                    @if($appPlan->status !== 'approved' && $activeLines->isEmpty())
                        <div class="flex items-center justify-end gap-3"><button type="button" data-edit-app-item data-edit-dialog="dlg-app-item" data-action="{{ route('planning.app.items.update', $item) }}" data-payload="{{ json_encode($item->only(['procurement_item', 'specifications', 'quantity', 'unit', 'estimated_unit_cost', 'procurement_mode', 'procurement_schedule', 'fund_source'])) }}" class="text-[11px] font-semibold text-primary hover:underline">Edit</button><form method="POST" action="{{ route('planning.app.items.destroy', $item) }}" data-confirm="Remove '{{ $item->procurement_item }}' from the APP? Generate the APP again to bring it back.">@csrf @method('DELETE')<button class="text-[11px] font-semibold text-error hover:underline">Delete</button></form></div>
                    @else
                        <span class="text-[11px] font-semibold text-on-surface-variant/60" title="{{ $appPlan->status === 'approved' ? 'Approved plans are locked. An approved APP is the source of Purchase Requests.' : 'A Purchase Request already draws from this item.' }}">Locked</span>
                    @endif
                @endif</td></tr>@endforeach</tbody></table></div>
    @else<div class="px-5 py-8 text-center text-sm text-on-surface-variant">No APP yet for FY {{ $year }}. Approve an SOB, then generate the APP.</div>@endif
</section>
</div>

@if(auth()->user()->hasPermission('planning.manage'))
<div data-panel="settings" class="hidden">
<section class="rounded border border-outline-variant/30 bg-white">
<div class="border-b border-outline-variant/20 px-5 py-4"><h2 class="font-semibold">Settings</h2><p class="mt-1 text-xs text-on-surface-variant">Fund sources and fiscal-year controls for this organization.</p></div>
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
        store.set(name);
        history.replaceState(null, '', name ? '#' + name : location.pathname + location.search);
    }
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.classList.contains('ring-2') ? '' : tab.dataset.tab)));
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
