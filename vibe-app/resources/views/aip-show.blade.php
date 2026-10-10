@extends('layouts.budget')
@section('title', 'AIP FY ' . $aip->fiscal_year)
@section('section', 'Reports')
@section('crumb', 'AIP')
@section('content')
@php
    $th = 'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant';
    $summary = $aip->fundSummary();
    $grand = ['q1' => $summary->sum('q1'), 'q2' => $summary->sum('q2'), 'q3' => $summary->sum('q3'), 'q4' => $summary->sum('q4'), 'total' => $summary->sum('total')];
    $icon = 'inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container';
    $field = $inputClass;
    $label = 'block text-xs font-semibold text-on-surface-variant';
    $pillarGroups = $aip->kras->groupBy(fn ($k) => $k->pillar ?: 'No pillar selected');
@endphp
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Annual Implementation Plan · FY {{ $aip->fiscal_year }}</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $aip->school?->name }} ·
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $aip->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($aip->status) }}</span>
            @if($aip->approved_at) · approved {{ $aip->approved_at->format('M d, Y') }}@endif
        </p>
        @if($aip->transaction)<a href="{{ route('transactions.show', $aip->transaction) }}" class="mt-2 inline-flex text-xs font-semibold text-primary underline">Transaction timeline · {{ $aip->transaction->transaction_number }}</a>@endif
        @if($aip->sipProject)<p class="mt-1 text-xs text-on-surface-variant">SIP priority: {{ $aip->sipProject->project }}</p>@endif
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('planning') }}#aip" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">← Planning</a>
        <a href="{{ route('aip.print', $aip) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Print AIP</a>
        @if($canManage)
            <a href="{{ route('aip.kras.create', $aip) }}" class="rounded border border-primary px-4 py-2.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white">+ Add KRA and Activities</a>
            <form method="POST" action="{{ route('aip.approve', $aip) }}" onsubmit="return confirm('Approve this AIP? It is used for reports and to start an SOB. It does not touch the Budget.')">@csrf<button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">{{ $aip->status === 'approved' ? 'Approved' : 'Approve AIP' }}</button></form>
        @endif
    </div>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

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
<div class="-mt-3 mb-6 rounded border border-primary/20 bg-primary/5 px-4 py-3 text-xs text-on-surface-variant">
    <p class="flex items-center gap-1.5 font-semibold text-primary"><span class="material-symbols-outlined text-[16px]">info</span>What is the Governing Entity?</p>
    <p class="mt-1">It is the office that owns and prepares this AIP. It decides which <strong>Source of Fund</strong> choices appear on each activity.</p>
    <ul class="mt-2 space-y-1.5">
        @foreach($entityHelp as $name => [$meaning, $funds])
            <li class="rounded px-2 py-1.5 {{ $aip->entity === $name ? 'bg-white ring-1 ring-primary/30' : '' }}"><strong class="text-on-surface">{{ $name }}</strong>@if($aip->entity === $name) <span class="rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-white">Selected</span>@endif — {{ $meaning }} <span class="whitespace-nowrap">Funds: <strong>{{ $funds }}</strong>.</span></li>
        @endforeach
    </ul>
    <p class="mt-2">Changing the entity only changes the fund choices for new or edited activities. Activities already saved keep the fund they have, so review them after switching.</p>
</div>

@forelse($pillarGroups as $pillarName => $kras)
    <section data-pillar-card class="mb-6 overflow-hidden rounded-lg border border-outline-variant/40 bg-white">
        <header class="flex items-center justify-center gap-3 border-b border-outline-variant/30 bg-primary px-5 py-3 text-center text-white">
            <span class="material-symbols-outlined text-[20px]">flag</span>
            <div><p class="text-[10px] font-semibold uppercase tracking-widest text-white/70">Pillar / Enabling Mechanism</p><h2 class="text-base font-semibold">{{ $pillarName }}</h2></div>
        </header>

        <div class="space-y-4 p-4">
            @foreach($kras as $kra)
                <article data-kra-card class="rounded-lg border border-outline-variant/40 border-l-4 border-l-secondary bg-surface-low/40">
                    <div class="flex flex-col gap-3 border-b border-outline-variant/30 p-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-secondary">Key Result Area</p>
                            <h3 class="text-base font-semibold">{{ $kra->kra }}</h3>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Intermediate Outcome</dt><dd>{{ $kra->intermediate_outcome ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Strategy</dt><dd>{{ $kra->strategy ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">5-Point Agenda</dt><dd>{{ $kra->five_point_agenda ?: '—' }}</dd></div>
                                <div><dt class="text-[11px] font-semibold uppercase text-on-surface-variant">Specific Program / Project</dt><dd class="font-semibold">{{ $kra->program ?: '—' }}</dd></div>
                            </dl>
                        </div>
                        @if($canManage)
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('aip.kras.edit', [$aip, $kra]) }}" class="mr-1 flex items-center gap-1 rounded bg-secondary px-3 py-2 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[16px]">edit</span>Edit KRA &amp; Activities</a>
                                <form method="POST" action="{{ route('aip.kras.destroy', [$aip, $kra]) }}" class="inline" onsubmit="return confirm('Remove this KRA and all of its activities?')">@csrf @method('DELETE')<button title="Delete KRA" aria-label="Delete KRA" class="{{ $icon }} text-error"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3 p-4">
                        @forelse($kra->activities as $a)
                            <div data-fund="{{ $a->source_of_fund }}" class="rounded-lg border border-outline-variant/40 bg-white">
                                <div class="flex items-start justify-between gap-3 border-b border-outline-variant/20 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Activity</p>
                                        <p class="font-semibold">{{ $a->activity }}</p>
                                        <p class="mt-1 flex flex-wrap gap-2 text-xs text-on-surface-variant"><span class="rounded bg-surface-container px-2 py-0.5">Target: {{ $a->physical_target }}</span>@if($a->timeline)<span class="rounded bg-surface-container px-2 py-0.5">{{ $a->timeline }}</span>@endif</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-5">
                                    <div class="lg:col-span-3">
                                        <table class="w-full text-sm">
                                            <thead><tr class="text-left"><th class="{{ $th }} px-2">Source</th><th class="{{ $th }} px-2 text-right">1st</th><th class="{{ $th }} px-2 text-right">2nd</th><th class="{{ $th }} px-2 text-right">3rd</th><th class="{{ $th }} px-2 text-right">4th</th><th class="{{ $th }} px-2 text-right">Total</th></tr></thead>
                                            <tbody><tr class="border-t border-outline-variant/20"><td class="px-2 py-2 font-semibold">{{ $a->source_of_fund ?: '—' }}</td>@foreach(['q1_amount', 'q2_amount', 'q3_amount', 'q4_amount'] as $q)<td class="px-2 py-2 text-right tabular-nums">{{ (float) $a->$q ? number_format($a->$q, 2) : '—' }}</td>@endforeach<td class="px-2 py-2 text-right font-semibold tabular-nums">{{ $a->total ? number_format($a->total, 2) : '—' }}</td></tr></tbody>
                                        </table>
                                        <p class="mt-2 px-2 text-xs text-on-surface-variant">Account code:
                                            @if($a->account)<span class="font-semibold tabular-nums text-on-surface">{{ $a->account->code }}</span> · {{ $a->account->title }}
                                            @elseif($a->total > 0)<span class="font-semibold text-error">needed before approval</span>
                                            @else —@endif
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
                </article>
            @endforeach
        </div>
    </section>
@empty
    <p class="mb-6 rounded-lg border border-dashed border-outline-variant/60 bg-white px-4 py-12 text-center text-on-surface-variant">No KRA yet. @if($canManage)Click “+ Add KRA and Activities”.@endif</p>
@endforelse

<div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
        <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Financial Target per Source of Fund</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">Source of Fund</th><th class="{{ $th }} text-right">1st Qtr</th><th class="{{ $th }} text-right">2nd Qtr</th><th class="{{ $th }} text-right">3rd Qtr</th><th class="{{ $th }} text-right">4th Qtr</th><th class="{{ $th }} text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @foreach($summary as $fund => $row)<tr><td class="px-3 py-2.5 font-semibold">{{ $fund }}</td>@foreach(['q1', 'q2', 'q3', 'q4'] as $q)<td class="px-3 py-2.5 text-right tabular-nums">{{ $row[$q] ? number_format($row[$q], 2) : '' }}</td>@endforeach<td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($row['total'], 2) }}</td></tr>@endforeach
            </tbody>
            <tfoot class="border-t-2 border-outline-variant/40 bg-surface-low/60 font-bold"><tr><td class="px-3 py-2.5">TOTAL</td>@foreach(['q1', 'q2', 'q3', 'q4'] as $q)<td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($grand[$q], 2) }}</td>@endforeach<td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($grand['total'], 2) }}</td></tr></tfoot>
        </table>
    </section>

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-base font-semibold">Signatories</h2>
        <form method="POST" action="{{ route('aip.update', $aip) }}" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            @csrf @method('PUT')
            @foreach([['prepared_by', 'Prepared by'], ['noted_by', 'Noted by'], ['approved_by', 'Approved by']] as [$key, $text])
                <label class="{{ $label }}">{{ $text }}<input name="{{ $key }}_name" value="{{ $aip->{$key . '_name'} }}" @disabled(!$canManage) class="{{ $field }}"></label>
                <label class="{{ $label }}">Position<input name="{{ $key }}_position" value="{{ $aip->{$key . '_position'} }}" @disabled(!$canManage) class="{{ $field }}"></label>
            @endforeach
            @if($canManage)<button class="rounded border border-outline-variant/60 px-4 py-2.5 text-sm font-semibold hover:bg-surface-low md:col-span-2">Save Signatories</button>@endif
        </form>
    </section>
</div>

@if($allotments->isNotEmpty())
    <section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
        <h2 class="border-b border-outline-variant/30 px-5 py-3 text-base font-semibold">Allotments created from this AIP</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">Ref</th><th class="{{ $th }}">Fund</th><th class="{{ $th }}">UACS Code</th><th class="{{ $th }}">Expense Item</th><th class="{{ $th }}">Program</th><th class="{{ $th }} text-right">Allotment</th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">@foreach($allotments as $l)<tr><td class="px-3 py-2.5">{{ $l->budget_ref_no }}</td><td class="px-3 py-2.5">{{ $l->source_of_fund }}</td><td class="px-3 py-2.5 tabular-nums">{{ $l->uacs_code }}</td><td class="px-3 py-2.5">{{ $l->particulars }}</td><td class="px-3 py-2.5">{{ $l->program }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $peso($l->amount) }}</td></tr>@endforeach</tbody>
        </table>
    </section>
@endif

@endsection
@push('scripts')
<script>
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
                    if (!kra.hidden) pillarHasMatch = true;
                });
                pillar.hidden = !!wanted && !pillarHasMatch;
            });
        };
        select.addEventListener('change', apply);
    })();
</script>
@endpush
