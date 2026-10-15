@extends('layouts.procurement')
@section('title',$procurementRequest->request_number) @section('page-title',$procurementRequest->request_number)
@section('header-actions')<a href="{{ route('procurement.edit',$procurementRequest) }}" class="inline-flex min-h-10 items-center rounded-lg border border-outline-variant bg-white px-3 text-xs font-bold text-primary">Edit request</a>@endsection
@section('content')
<a href="{{ route('procurement.requests') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-bold text-action"><span class="material-symbols-outlined text-[17px]">arrow_back</span>All requests</a>
<header class="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><div class="flex flex-wrap items-center gap-2"><p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">{{ $procurementRequest->request_number }}</p><x-procurement.status-badge :label="str($procurementRequest->status)->replace('_',' ')->title()" :tone="in_array($procurementRequest->status,['returned','rejected'])?'exception':'action'"/></div><h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-tight">{{ $procurementRequest->title }}</h1><p class="mt-2 text-sm text-on-surface-variant">{{ $procurementRequest->school?->name }} · Owner: {{ $procurementRequest->requester?->name ?? 'System' }} · <strong class="text-on-surface">₱{{ number_format((float)$procurementRequest->amount,2) }}</strong></p></div></header>
@if(in_array($procurementRequest->status, ['submitted', 'pending_approval'], true) && request()->user()->hasPermission('procurement.approve'))
    <section class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-outline-variant/60 bg-white p-4" aria-label="Procurement approval actions">
        <p class="mr-auto text-sm font-bold">Approval decision</p>
        <form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="returned"><button class="min-h-11 rounded-lg border border-attention px-4 text-xs font-bold text-attention">Return request</button></form>
        <form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="min-h-11 rounded-lg border border-error px-4 text-xs font-bold text-error">Reject request</button></form>
        <form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="min-h-11 rounded-lg bg-secondary px-4 text-xs font-bold text-white">Approve request</button></form>
    </section>
@elseif(in_array($procurementRequest->status, ['draft', 'returned'], true) && (request()->user()->hasPermission('procurement.edit') || request()->user()->hasPermission('procurement.create')))
    <section class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-variant/60 bg-white p-4" aria-label="Procurement submission action">
        <p class="text-sm font-bold">Ready for another approval review?</p>
        <form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="pending_approval"><button class="min-h-11 rounded-lg bg-primary px-4 text-xs font-bold text-white">Submit for approval</button></form>
    </section>
@endif
{{-- Obligation: it is the Purchase Order that obligates the budget; the ORS is made from the PO (it brings the payee, the awarded amount and the budget line). --}}
@unless(in_array($procurementRequest->status, ['returned', 'rejected'], true))
    @php $activeOrs = $procurementRequest->liquidationReports->whereNotIn('status', ['returned', 'rejected'])->first(); $purchaseOrder = $procurementRequest->purchaseOrder(); @endphp
    @if($activeOrs)
        <section class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-secondary/40 bg-secondary/5 p-4" aria-label="Obligation">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-secondary/15 text-secondary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">assignment_turned_in</span></span>
            <div class="mr-auto"><p class="text-[10px] font-bold uppercase tracking-wider text-secondary">Obligated</p><p class="text-sm font-bold">{{ $activeOrs->ors_number }} · ₱{{ number_format((float) $activeOrs->amount, 2) }} <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-secondary">{{ \Illuminate\Support\Str::headline($activeOrs->status) }}</span></p></div>
            <a href="{{ route('liquidation.print', $activeOrs) }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center rounded-lg border border-secondary px-4 text-xs font-bold text-secondary hover:bg-secondary hover:text-white">View ORS</a>
        </section>
    @elseif($purchaseOrder)
        <section class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4" aria-label="Obligation">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">pending_actions</span></span>
            <div class="mr-auto"><p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Not obligated yet</p><p class="text-sm font-bold">{{ $purchaseOrder->document_number }} is ready: create the ORS to obligate ₱{{ number_format($procurementRequest->obligationAmount(), 2) }} of the budget.</p></div>
            @if(request()->user()->canCreateObligation())<a href="{{ route('budget', ['pr' => $procurementRequest->id, 'new' => 1]) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-bold text-white shadow-md hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>Create ORS</a>@else<span class="text-xs text-on-surface-variant">Waiting for the ORS.</span>@endif
        </section>
    @else
        <p class="mb-5 flex items-center gap-2 rounded-xl border border-outline-variant/60 bg-white px-4 py-3 text-xs text-on-surface-variant" aria-label="Obligation"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">info</span>The budget is obligated by the Purchase Order: once the PO is made, the ORS is created from it.</p>
    @endif
@endunless
@if(in_array($procurementRequest->status, ['draft', 'returned', 'approved', 'for_canvass', 'completed'], true))
    @php
        $canEditRequest = request()->user()->hasPermission('procurement.edit') || request()->user()->hasPermission('procurement.create');
        $stage = $workspace['current_stage'];
        $docsComplete = empty($workspace['documents']['missing']);
        $inProcurement = in_array($procurementRequest->status, ['approved', 'for_canvass'], true);
    @endphp
    <section class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-outline-variant/60 bg-white p-4" aria-label="Procurement request actions">
        <div class="mr-auto"><p class="text-[10px] font-bold uppercase tracking-wider text-action">Next action</p><p class="text-sm font-bold">{{ $workspace['next_action']['label'] }}</p></div>
        @if($canEditRequest && in_array($procurementRequest->status, ['draft', 'returned'], true))<a href="{{ route('procurement.edit', $procurementRequest) }}" class="inline-flex min-h-11 items-center rounded-lg border border-primary px-4 text-xs font-bold text-primary">Edit request</a>@endif
        {{-- Start canvass only while the request is really still at the canvass stage; later stages open their own documents. --}}
        @if($canEditRequest && $procurementRequest->status === 'approved' && $stage === 'canvass')<form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="for_canvass"><button class="min-h-11 rounded-lg bg-primary px-4 text-xs font-bold text-white">Start canvass</button></form>@endif
        @if($stage === 'receiving')<a href="{{ route('procurement.documents', [$procurementRequest, 'stage' => 'receiving']) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 text-xs font-bold text-white">Open receiving documents</a>
        @elseif(in_array($stage, ['award', 'purchase_order', 'complete'], true) || $procurementRequest->status === 'for_canvass')<a href="{{ route('procurement.documents', $procurementRequest) }}" class="inline-flex min-h-11 items-center rounded-lg {{ $stage === 'complete' || $stage !== 'canvass' ? 'bg-primary text-white' : 'border border-primary text-primary' }} px-4 text-xs font-bold">Open documents</a>@endif
        @if($canEditRequest && $inProcurement && $docsComplete)<form method="POST" action="{{ route('procurement.status', $procurementRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="min-h-11 rounded-lg bg-secondary px-4 text-xs font-bold text-white">Mark complete</button></form>@elseif($canEditRequest && $inProcurement && $stage !== 'canvass')<span class="text-xs font-semibold text-on-surface-variant">Complete all required documents to mark this request complete.</span>@endif
    </section>
@endif
<section id="stage-rail-section" data-live-url="{{ route('procurement.workspace', $procurementRequest) }}" data-live-version="{{ $workspaceVersion }}" class="mb-5 rounded-xl border border-outline-variant/60 bg-white px-4"><div data-stage-rail><x-procurement.stage-rail :stages="$workspace['stages']"/></div></section>
<p id="stage-rail-notice" role="status" class="mb-5 hidden rounded-lg border border-primary/30 bg-primary/5 px-4 py-2 text-xs font-semibold text-primary">This request was updated. <a href="{{ request()->fullUrl() }}" class="underline">Reload</a> to refresh the details below.</p>
<script>
    // Keeps the stage bar live: asks the server for the request's version every 15 seconds while this tab is visible, and redraws the bar
    // when it changed. The rest of the page holds forms and lists, so it is not swapped silently; a notice offers a reload instead.
    (() => {
        const host = document.getElementById('stage-rail-section');
        if (!host) return;
        const notice = document.getElementById('stage-rail-notice');
        let version = host.dataset.liveVersion;
        let failures = 0;
        let stopped = false;
        const poll = async () => {
            if (stopped || document.hidden) return;
            try {
                const response = await fetch(host.dataset.liveUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                if ([401, 403, 404].includes(response.status)) { stopped = true; return; }
                if (!response.ok) throw new Error('bad response');
                const data = await response.json();
                failures = 0;
                if (data.version !== version) {
                    version = data.version;
                    host.querySelector('[data-stage-rail]').innerHTML = data.rail_html;
                    notice.classList.remove('hidden');
                }
            } catch (error) {
                if (++failures >= 5) stopped = true;
            }
        };
        setInterval(poll, 15000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    })();
</script>
<nav class="mb-4 flex flex-wrap gap-2" aria-label="Request sections">@foreach(['summary'=>'Summary','items'=>'Items','documents'=>'Documents','activity'=>'Activity'] as $key=>$label)<a href="{{ route('procurement.show',[$procurementRequest,'section'=>$key]) }}" @if($activeSection===$key) aria-current="page" @endif class="min-h-11 rounded-lg px-4 py-3 text-xs font-bold {{ $activeSection===$key?'bg-primary text-white':'bg-white text-primary' }}">{{ $label }}</a>@endforeach</nav>
<div class="grid gap-5 xl:grid-cols-[1fr_300px]"><section class="rounded-xl border border-outline-variant/60 bg-white p-5">@include('partials.procurement.request-'.$activeSection)</section><aside class="rounded-xl border border-outline-variant/60 bg-white p-5"><h2 class="font-bold">Requirements</h2><p class="mt-1 text-xs text-on-surface-variant">{{ $workspace['documents']['completed'] }} of {{ $workspace['documents']['total'] }} core documents complete.</p><div class="mt-4 space-y-2">@forelse($workspace['documents']['missing'] as $missing)<div class="flex gap-2 rounded-lg bg-attention/10 p-3 text-xs"><span class="material-symbols-outlined text-[17px] text-attention">warning</span><span><strong class="block">{{ $missing['label'] }}</strong><small class="text-on-surface-variant">Required to continue the workflow</small></span></div>@empty<div class="rounded-lg bg-secondary/10 p-3 text-xs font-bold text-secondary">Core documents complete</div>@endforelse</div></aside></div>
@endsection
