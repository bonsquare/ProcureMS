@extends('layouts.budget')
@section('title', 'AIP FY ' . $aip->fiscal_year)
@section('section', 'Planning')
@section('crumb', 'AIP')
@section('content')
@php
    $th = 'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $summary = $aip->fundSummary();
    $grand = ['q1' => $summary->sum('q1'), 'q2' => $summary->sum('q2'), 'q3' => $summary->sum('q3'), 'q4' => $summary->sum('q4'), 'total' => $summary->sum('total')];
    $icon = 'inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container';
    $field = $inputClass;
    $label = 'block text-xs font-semibold text-on-surface-variant';
    // The template has one pillar for Resiliency and Well-Being, whichever of the two names a KRA was saved with.
    $pillarGroups = $aip->kras->groupBy(fn ($k) => in_array(strtolower(trim((string) $k->pillar)), ['resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being'], true) ? 'Resiliency and Well-Being' : ($k->pillar ?: 'No pillar selected'));
@endphp
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        @include('partials.planning-jump', ['schoolId' => $aip->school_id, 'year' => $aip->fiscal_year, 'backTo' => 'aip'])
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Annual Implementation Plan · FY {{ $aip->fiscal_year }}</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $aip->school?->name }} ·
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $aip->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($aip->status) }}</span>
            @if($aip->approved_at) · approved {{ $aip->approved_at->format('M d, Y') }}@endif
        </p>
        @if($aip->transaction)<a href="{{ route('transactions.show', $aip->transaction) }}" class="mt-2 inline-flex text-xs font-semibold text-primary underline">Transaction timeline · {{ $aip->transaction->transaction_number }}</a>@endif
        @if($aip->sipProject)<p class="mt-1 text-xs text-on-surface-variant">SIP priority: {{ $aip->sipProject->project }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($canManage && $aip->status !== 'approved')
            <form method="POST" action="{{ route('aip.approve', $aip) }}" onsubmit="return confirm('Approve this AIP? It is used for reports and to start an SOB. It does not touch the Budget.')">@csrf<button class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">{{ $aip->status === 'revised' ? 'Approve the revised AIP' : 'Approve AIP' }}</button></form>
        @elseif($aip->status === 'approved')
            <a href="{{ route('planning', ['school_id' => $aip->school_id, 'year' => $aip->fiscal_year]) }}#sob" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Start the SOB →</a>
        @endif
        @if($canManage)<a href="{{ route('aip.kras.create', $aip) }}" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">+ Add KRA and Activities</a>@endif
        <a href="{{ route('aip.print', $aip) }}" target="_blank" rel="noopener" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-low">Print AIP</a>
    </div>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@include('partials.document-strip', ['strip' => \App\Support\DocumentStrip::aip($aip)])

<section class="mb-6 flex flex-col gap-3 rounded border border-outline-variant/30 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
    <form method="POST" action="{{ route('aip.update', $aip) }}" class="flex flex-wrap items-center gap-3">
        @csrf @method('PUT')
        <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="fiscal_year">Fiscal Year</label>
        <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100" value="{{ $aip->fiscal_year }}" @disabled(!$canManage) title="Type the fiscal year this plan is for, then press Enter or click away." onchange="this.form.submit()" class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
        <span class="hidden text-outline-variant sm:inline">|</span>
        <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="entity">Governing Entity</label>
        <select id="entity" name="entity" title="The office that prepares this AIP. It decides the Source of Fund choices." onchange="this.form.submit()" @disabled(!$canManage) class="rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
            @foreach(array_keys(\App\Models\Aip::FUNDS) as $entity)<option @selected($aip->entity === $entity) title="{{ $entity === 'School' ? 'The school prepares this AIP' : 'The Schools Division Office prepares this AIP' }}">{{ $entity }}</option>@endforeach
        </select>
    </form>
    <div class="flex flex-wrap items-center gap-3">
        <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="fund-filter">Filter by Source of Fund</label>
        <select id="fund-filter" class="rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
            <option value="">All sources of fund</option>
            @foreach($fundOptions as $option)<option>{{ $option }}</option>@endforeach
        </select>
    </div>
</section>
@php
    $entityHelp = [
        'School' => ['Choose this when the AIP is prepared by a school (elementary, junior or senior high, integrated school) for its own activities. This is the usual choice for a school head or school budget officer.', 'MOOE, SEF, IGP, Others'],
        'Division Office Proper' => ['Choose this only when the AIP is prepared by the Schools Division Office itself (its sections and units), not by an individual school.', 'MOOE-GASS, MOOE-HRTD, MOOE-Sub-ARO, School MOOE, SEF, Others'],
    ];
@endphp
<details class="group -mt-3 mb-6 rounded border border-primary/20 bg-primary/5 px-4 py-3 text-xs text-on-surface-variant">
    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-2 font-semibold text-primary"><span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">info</span>What is the Governing Entity?</span><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></summary>
    <p class="mt-1">It is the office that owns and prepares this AIP. It decides which <strong>Source of Fund</strong> choices appear on each activity.</p>
    <ul class="mt-2 space-y-1.5">
        @foreach($entityHelp as $name => [$meaning, $funds])
            <li class="rounded px-2 py-1.5 {{ $aip->entity === $name ? 'bg-white ring-1 ring-primary/30' : '' }}"><strong class="text-on-surface">{{ $name }}</strong>@if($aip->entity === $name) <span class="rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-white">Selected</span>@endif — {{ $meaning }} <span class="sm:whitespace-nowrap">Funds: <strong>{{ $funds }}</strong>.</span></li>
        @endforeach
    </ul>
    <p class="mt-2">Changing the entity only changes the fund choices for new or edited activities. Activities already saved keep the fund they have, so review them after switching.</p>
</details>

<style>details[open] > summary .sum-chevron{transform:rotate(180deg)}</style>
@if($pillarGroups->isNotEmpty())
<div class="mb-3 flex justify-end gap-2"><button type="button" data-acc-all="1" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Expand all</button><button type="button" data-acc-all="0" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Collapse all</button></div>
@endif

@forelse($pillarGroups as $pillarName => $kras)
    <details data-pillar-card data-acc="p-{{ \Illuminate\Support\Str::slug($pillarName) }}" class="group mb-6 overflow-hidden rounded-lg border border-outline-variant/40 bg-white">
        <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-outline-variant/30 bg-primary px-5 py-3 text-white">
            <span class="flex items-center gap-3"><span class="material-symbols-outlined text-[20px]">flag</span>
            <span><span class="block text-[10px] font-semibold uppercase tracking-widest text-white/70">Pillar / Enabling Mechanism</span><h2 class="text-base font-semibold">{{ $pillarName }}</h2></span></span>
            <span class="flex items-center gap-2 text-xs text-white/80 max-sm:basis-full max-sm:pl-9"><span>{{ $kras->count() }} KRA · {{ $kras->sum(fn ($k) => $k->activities->count()) }} activities</span><span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span>
        </summary>

        <div class="space-y-4 p-4">
            {{-- The same Key Result Area is one box: each of its programs (with its own details and activities) is listed inside it. --}}
            @foreach($kras->groupBy(fn ($k) => trim((string) $k->kra)) as $kraGroup)
                @php $groupActivities = $kraGroup->sum(fn ($k) => $k->activities->count()); @endphp
                <details data-kra-card data-acc="k-{{ $kraGroup->first()->id }}" class="group rounded-lg border border-outline-variant/40 border-l-4 border-l-secondary bg-surface-low/40">
                    <summary class="flex cursor-pointer list-none [&::-webkit-details-marker]:hidden items-center justify-between gap-3 p-4">
                        <span class="min-w-0"><span class="block text-[10px] font-semibold uppercase tracking-widest text-secondary">Key Result Area</span><h3 class="text-base font-semibold">{{ $kraGroup->first()->kra }}</h3></span>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-on-surface-variant">@if($kraGroup->count() > 1){{ $kraGroup->count() }} programs · @endif{{ $groupActivities }} activit{{ $groupActivities === 1 ? 'y' : 'ies' }}<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span>
                    </summary>
                    @foreach($kraGroup as $kra)
                    <div class="{{ $loop->first ? '' : 'border-t-4 border-outline-variant/30' }}">
                    <div class="flex flex-col gap-3 border-y border-outline-variant/30 p-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Intermediate Outcome</dt><dd>{{ $kra->intermediate_outcome ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Strategy</dt><dd>{{ $kra->strategy ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">5-Point Agenda</dt><dd>{{ $kra->five_point_agenda ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Specific Program / Project</dt><dd class="font-semibold">{{ $kra->program ?: '—' }}</dd></div>
                            </dl>
                        </div>
                        @if($canManage)
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('aip.kras.edit', [$aip, $kra]) }}" class="mr-1 flex items-center gap-1 rounded bg-secondary px-3 py-2 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[16px]">edit</span>Edit KRA</a>
                                <form method="POST" action="{{ route('aip.kras.destroy', [$aip, $kra]) }}" class="inline" onsubmit="return confirm('Remove this KRA and all of its activities?')">@csrf @method('DELETE')<button title="Delete KRA" aria-label="Delete KRA" class="{{ $icon }} text-error"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3 p-4">
                        @forelse($kra->activities as $a)
                            <div data-fund="{{ $a->source_of_fund }}" class="rounded-lg border border-outline-variant/40 border-l-4 border-l-primary bg-white shadow-sm">
                                <div class="flex items-start justify-between gap-3 border-b border-primary/15 bg-primary/5 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary">Activity</p>
                                        <p class="font-semibold">{{ $a->activity }}</p>
                                        <p class="mt-1 flex flex-wrap gap-2 text-xs text-on-surface-variant"><span class="rounded bg-surface-container px-2 py-0.5">Target: {{ $a->physical_target }}</span>@if($a->timeline)<span class="rounded bg-surface-container px-2 py-0.5">{{ $a->timeline }}</span>@endif</p>
                                    </div>
                                    @if($canManage)
                                        <div class="flex shrink-0 items-center gap-1">
                                            <a href="{{ route('aip.kras.edit', [$aip, $kra]) }}#activity-{{ $loop->index }}" class="flex items-center gap-1 rounded bg-secondary px-3 py-2 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[16px]">edit</span>Edit</a>
                                            <form method="POST" action="{{ route('aip.activities.destroy', [$aip, $a]) }}" class="inline" onsubmit="return confirm('Remove this activity?')">@csrf @method('DELETE')<button title="Delete activity" aria-label="Delete activity" class="{{ $icon }} text-error"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-5">
                                    <div class="min-w-0 lg:col-span-3">
                                        @if($canManage)
                                            @php
                                                $fund = (string) $a->source_of_fund;
                                                $listed = in_array($fund, $fundOptions, true);
                                                $suggested = \App\Models\AipActivity::timelineFor([$a->q1_amount, $a->q2_amount, $a->q3_amount, $a->q4_amount]);
                                                $quarterMonths = ['Jan – Mar', 'Apr – Jun', 'Jul – Sep', 'Oct – Dec'];
                                            @endphp
                                            <div class="space-y-4">
                                                <form method="POST" action="{{ route('aip.activities.fund', [$aip, $a]) }}" data-fund-form class="space-y-1">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="source_of_fund" value="{{ $fund }}">
                                                    <label class="block text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Source of fund
                                                        <select data-fund-select aria-label="Source of fund" class="{{ $field }} !mt-1.5 !py-2 text-sm font-semibold normal-case tracking-normal text-on-surface">
                                                            <option value="">Select source of fund</option>
                                                            @if($fund && !$listed)<option selected>{{ $fund }}</option>@endif
                                                            @foreach($fundOptions as $option)<option @selected($fund === $option)>{{ $option }}</option>@endforeach
                                                        </select>
                                                    </label>
                                                    <input data-fund-other placeholder="Specify source of fund, then press Enter" class="{{ $field }} !mt-0 !py-2 hidden text-sm">
                                                    <p data-fund-status class="hidden text-xs font-medium"></p>
                                                </form>

                                                <form id="budget-{{ $a->id }}" method="POST" action="{{ route('aip.activities.budget', [$aip, $a]) }}" data-budget-form @if(! $a->timeline || $a->timeline === $suggested) data-timeline-auto @endif>
                                                    @csrf @method('PUT')
                                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Financial target per quarter (₱)</p>
                                                    <div class="mt-1.5 grid grid-cols-2 items-end gap-3 sm:grid-cols-5">
                                                        @foreach(['q1_amount', 'q2_amount', 'q3_amount', 'q4_amount'] as $q)
                                                            <label class="block text-xs font-semibold text-on-surface-variant">{{ ['1st', '2nd', '3rd', '4th'][$loop->index] }} quarter<span class="block text-[11px] font-normal">{{ $quarterMonths[$loop->index] }}</span>
                                                                <span class="relative mt-1 block">
                                                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-normal text-on-surface-variant">₱</span>
                                                                    <input type="number" step="0.01" min="0" data-q name="{{ $q }}" value="{{ (float) $a->$q ? number_format((float) $a->$q, 2, '.', '') : '' }}" placeholder="0.00" class="{{ $field }} !mt-0 !py-2 !pl-7 text-right text-sm font-normal tabular-nums">
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                        <div class="block text-xs font-semibold text-on-surface-variant">Total
                                                            <p class="mt-1 flex h-[2.4rem] items-center justify-end rounded border border-outline-variant/40 bg-surface-low px-3 text-sm font-bold tabular-nums text-on-surface">₱<span data-total class="ml-1">{{ $a->total ? number_format($a->total, 2) : '0.00' }}</span></p>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap items-end gap-3">
                                                        <label class="min-w-[14rem] flex-1 text-xs font-semibold text-on-surface-variant">Frequency (timeline)
                                                            <input name="timeline" data-timeline value="{{ $a->timeline ?: $suggested }}" placeholder="e.g. January - December" class="{{ $field }} !mt-1 !py-2 text-sm font-normal">
                                                        </label>
                                                    </div>
                                                    <p data-budget-status class="mt-2 hidden text-xs font-medium"></p>
                                                </form>
                                            </div>
                                        @else
                                            <table class="w-full text-sm">
                                                <thead><tr class="text-left"><th class="{{ $th }} px-2">Source</th><th class="{{ $th }} px-2 text-right">1st</th><th class="{{ $th }} px-2 text-right">2nd</th><th class="{{ $th }} px-2 text-right">3rd</th><th class="{{ $th }} px-2 text-right">4th</th><th class="{{ $th }} px-2 text-right">Total</th></tr></thead>
                                                <tbody><tr class="border-t border-outline-variant/20"><td class="px-2 py-2 font-semibold">{{ $a->source_of_fund ?: '—' }}</td>@foreach(['q1_amount', 'q2_amount', 'q3_amount', 'q4_amount'] as $q)<td class="px-2 py-2 text-right tabular-nums">{{ (float) $a->$q ? number_format($a->$q, 2) : '—' }}</td>@endforeach<td class="px-2 py-2 text-right font-semibold tabular-nums">{{ $a->total ? number_format($a->total, 2) : '—' }}</td></tr></tbody>
                                            </table>
                                        @endif
                                        <p class="mt-3 text-xs text-on-surface-variant">Account code:
                                            @if($a->account)<span class="font-semibold tabular-nums text-on-surface">{{ $a->account->code }}</span> · {{ $a->account->title }}
                                            @else <span class="text-on-surface-variant">not set (optional)</span>@endif
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-2">
                                        <div class="rounded border border-outline-variant/30 bg-surface-low/50 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Responsible Persons</p>
                                            <ul class="mt-2 space-y-1 text-sm">@forelse($a->responsible_persons ?? [] as $person)<li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>{{ $person }}</li>@empty<li class="text-on-surface-variant">—</li>@endforelse</ul>
                                        </div>
                                        <div class="rounded border border-outline-variant/30 bg-surface-low/50 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Remarks</p>
                                            <ul class="mt-2 space-y-1 text-sm">@forelse($a->remarks_list ?? [] as $remark)<li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary"></span>{{ $remark }}</li>@empty<li class="text-on-surface-variant">—</li>@endforelse</ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="rounded border border-dashed border-outline-variant/60 px-4 py-6 text-center text-sm text-on-surface-variant">No activities under this KRA yet. @if($canManage)Use “Edit KRA &amp; Activities” to add them.@endif</p>
                        @endforelse
                    </div>
                    </div>
                    @endforeach
                </details>
            @endforeach
        </div>
    </details>
@empty
    <p class="mb-6 rounded-lg border border-dashed border-outline-variant/60 bg-white px-4 py-12 text-center text-on-surface-variant">No KRA yet. @if($canManage)Click “+ Add KRA and Activities”.@endif</p>
@endforelse

<div class="mb-6">
    <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
        <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Financial Target per Source of Fund</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="{{ $th }}">Source of Fund</th><th class="{{ $th }} text-right">1st Qtr</th><th class="{{ $th }} text-right">2nd Qtr</th><th class="{{ $th }} text-right">3rd Qtr</th><th class="{{ $th }} text-right">4th Qtr</th><th class="{{ $th }} text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @foreach($summary as $fund => $row)<tr><td class="px-3 py-2.5 font-semibold">{{ $fund }}</td>@foreach(['q1', 'q2', 'q3', 'q4'] as $q)<td class="px-3 py-2.5 text-right tabular-nums">{{ $row[$q] ? number_format($row[$q], 2) : '' }}</td>@endforeach<td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($row['total'], 2) }}</td></tr>@endforeach
            </tbody>
            <tfoot class="border-t-2 border-outline-variant/40 bg-surface-low/60 font-bold"><tr><td class="px-3 py-2.5">TOTAL</td>@foreach(['q1', 'q2', 'q3', 'q4'] as $q)<td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($grand[$q], 2) }}</td>@endforeach<td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($grand['total'], 2) }}</td></tr></tfoot>
        </table>
    </section>
</div>

@if($allotments->isNotEmpty())
    <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
        <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Allotments created from this AIP</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="{{ $th }}">Ref</th><th class="{{ $th }}">Fund</th><th class="{{ $th }}">UACS Code</th><th class="{{ $th }}">Expense Item</th><th class="{{ $th }}">Program</th><th class="{{ $th }} text-right">Allotment</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">@foreach($allotments as $l)<tr><td class="px-3 py-2.5">{{ $l->budget_ref_no }}</td><td class="px-3 py-2.5">{{ $l->source_of_fund }}</td><td class="px-3 py-2.5 tabular-nums">{{ $l->uacs_code }}</td><td class="px-3 py-2.5">{{ $l->particulars }}</td><td class="px-3 py-2.5">{{ $l->program }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($l->amount) }}</td></tr>@endforeach</tbody>
        </table>
    </section>
@endif

<section class="mt-6 rounded border border-outline-variant/30 bg-white p-5">
    <h2 class="text-base font-semibold">Signatories</h2>
    <form method="POST" action="{{ route('aip.update', $aip) }}" class="mt-3">
        @csrf @method('PUT')
        @php $aipSlots = \App\Support\PlanningSignatories::slots([['prepared_by', 'Prepared by', 'Prepared by', null, null, null, null], ['noted_by', 'Noted by', 'Noted by', null, null, null, null], ['approved_by', 'Approved by', 'Approved by', null, null, null, null]], $aip, $canManage ? \App\Support\PlanningSignatories::lastUsed($aip->school_id, ['prepared_by', 'noted_by', 'approved_by']) : []); @endphp
        @include('partials.signatory-editor', ['slots' => $aipSlots, 'editable' => $canManage, 'document' => 'AIP', 'open' => $errors->any(), 'saveLabel' => 'Save signatories', 'horizontal' => true])
    </form>
</section>

@include('partials.doc-preview-dock', ['key' => 'aip-'.$aip->id, 'title' => 'Official AIP', 'savedUrl' => route('aip.print', $aip), 'drafts' => '[data-budget-form], [data-fund-form]'])
@endsection
@push('scripts')
<script>
    (() => {
        // Source of Fund dropdown on each activity: a choice saves in the background so the page stays where it is; "Others" asks for the name first.
        document.querySelectorAll('[data-fund-form]').forEach((form) => {
            const select = form.querySelector('[data-fund-select]');
            const other = form.querySelector('[data-fund-other]');
            const value = form.querySelector('input[name="source_of_fund"]');
            const status = form.querySelector('[data-fund-status]');
            const say = (text, good) => {
                status.textContent = text;
                status.classList.remove('hidden', 'text-secondary', 'text-error');
                status.classList.add(good ? 'text-secondary' : 'text-error');
            };
            const save = async () => {
                say('Saving…', true);
                try {
                    const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) { throw new Error(response.status === 422 ? 'Check the source of fund.' : 'Not saved.'); }
                    const result = await response.json();
                    form.closest('[data-fund]').dataset.fund = result.source_of_fund || '';
                    say('Saved', true);
                    if (result.status !== @json($aip->status)) { window.location.reload(); }
                } catch (error) {
                    say(error.message || 'Not saved. Try again.', false);
                }
            };
            select.addEventListener('change', () => {
                if (select.value === 'Others') { other.classList.remove('hidden'); other.focus(); return; }
                other.classList.add('hidden');
                value.value = select.value;
                save();
            });
            other.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') { return; }
                event.preventDefault();
                if (!other.value.trim()) { return; }
                value.value = other.value.trim();
                save();
            });
        });
    })();
    (() => {
        // The amounts of each quarter and the timeline of an activity: whenever an amount changes, the timeline follows the quarters that have an amount (only the 1st =
        // January - March, up to the 3rd = January - September, all four = January - December); the amounts save by themselves when a box is left.
        const starts = ['January', 'April', 'July', 'October'];
        const ends = ['March', 'June', 'September', 'December'];
        const timelineFor = (values) => {
            const used = values.map((value, index) => (value > 0 ? index : -1)).filter((index) => index >= 0);
            return used.length ? `${starts[Math.min(...used)]} - ${ends[Math.max(...used)]}` : '';
        };
        document.querySelectorAll('[data-budget-form]').forEach((form) => {
            const card = form.closest('[data-fund]');
            const inputs = Array.from(card.querySelectorAll('[data-q]'));
            const timeline = form.querySelector('[data-timeline]');
            const total = card.querySelector('[data-total]');
            const status = form.querySelector('[data-budget-status]');
            const say = (text, good) => {
                status.textContent = text;
                status.classList.remove('hidden', 'text-secondary', 'text-error');
                status.classList.add(good ? 'text-secondary' : 'text-error');
            };
            const refresh = () => {
                const values = inputs.map((input) => parseFloat(input.value) || 0);
                const sum = values.reduce((carry, value) => carry + value, 0);
                total.textContent = sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const followed = timelineFor(values);
                if (followed) { timeline.value = followed; }
            };
            inputs.forEach((input) => input.addEventListener('input', refresh));
            // Like the Source of fund: the amounts and the timeline save by themselves when a box is left (one save at a time, the page stays where it is).
            let saving = Promise.resolve();
            const save = () => {
                saving = saving.then(async () => {
                    say('Saving…', true);
                    try {
                        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) { throw new Error(response.status === 422 ? 'Check the amounts.' : 'Not saved.'); }
                        const result = await response.json();
                        say('Saved', true);
                        if (result.status !== @json($aip->status)) { window.location.reload(); }
                    } catch (error) {
                        say(error.message || 'Not saved. Try again.', false);
                    }
                });
            };
            inputs.forEach((input) => input.addEventListener('change', save));
            timeline.addEventListener('change', save);
            form.addEventListener('submit', (event) => { event.preventDefault(); save(); });
        });
    })();
    (() => {
        const boxes = Array.from(document.querySelectorAll('details[data-acc]'));
        const key = 'aip-open-{{ $aip->id }}';
        const read = () => { try { return JSON.parse(sessionStorage.getItem(key) || '[]'); } catch (error) { return []; } };
        const write = () => { try { sessionStorage.setItem(key, JSON.stringify(boxes.filter((box) => box.open).map((box) => box.dataset.acc))); } catch (error) { /* storage can be blocked */ } };
        const remembered = read();
        boxes.forEach((box) => { box.open = remembered.includes(box.dataset.acc); box.addEventListener('toggle', write); });
        document.querySelectorAll('[data-acc-all]').forEach((button) => button.addEventListener('click', () => { boxes.forEach((box) => { box.open = button.dataset.accAll === '1'; }); write(); }));
    })();
    (() => {
        const select = document.getElementById('fund-filter');
        const apply = () => {
            const wanted = select.value;
            document.querySelectorAll('[data-pillar-card]').forEach((pillar) => {
                let pillarHasMatch = false;
                pillar.querySelectorAll('[data-kra-card]').forEach((kra) => {
                    let matches = 0;
                    kra.querySelectorAll('[data-fund]').forEach((card) => {
                        const show = !wanted || card.dataset.fund === wanted || (wanted === 'Others' && card.dataset.fund && !Array.from(select.options).some((o) => o.value === card.dataset.fund));
                        card.hidden = !show;
                        if (show) matches++;
                    });
                    kra.hidden = !!wanted && matches === 0;
                    if (wanted && matches > 0) { kra.open = true; }
                    if (!kra.hidden) pillarHasMatch = true;
                });
                pillar.hidden = !!wanted && !pillarHasMatch;
                if (wanted && pillarHasMatch) { pillar.open = true; }
            });
        };
        select.addEventListener('change', apply);
    })();
</script>
@endpush
