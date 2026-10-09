@extends('layouts.procurement')
@section('title',$procurementRequest->request_number) @section('page-title',$procurementRequest->request_number)
@section('header-actions')<a href="{{ route('procurement.edit',$procurementRequest) }}" class="inline-flex min-h-10 items-center rounded-lg border border-outline-variant bg-white px-3 text-xs font-bold text-primary">Edit request</a>@endsection
@section('content')
<a href="{{ route('procurement.requests') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-bold text-action"><span class="material-symbols-outlined text-[17px]">arrow_back</span>All requests</a>
<header class="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><div class="flex flex-wrap items-center gap-2"><p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">{{ $procurementRequest->request_number }}</p><x-procurement.status-badge :label="str($procurementRequest->status)->replace('_',' ')->title()" :tone="in_array($procurementRequest->status,['returned','rejected'])?'exception':'action'"/></div><h1 class="mt-2 text-3xl font-bold">{{ $procurementRequest->title }}</h1><p class="mt-2 text-sm text-on-surface-variant">{{ $procurementRequest->school?->name }} · Owner: {{ $procurementRequest->requester?->name ?? 'System' }} · <strong class="text-on-surface">₱{{ number_format((float)$procurementRequest->amount,2) }}</strong></p></div></header>
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
<section class="mb-5 rounded-xl border border-outline-variant/60 bg-white px-4"><x-procurement.stage-rail :stages="$workspace['stages']"/></section>
<nav class="mb-4 flex flex-wrap gap-2" aria-label="Request sections">@foreach(['summary'=>'Summary','items'=>'Items','documents'=>'Documents','activity'=>'Activity'] as $key=>$label)<a href="{{ route('procurement.show',[$procurementRequest,'section'=>$key]) }}" @if($activeSection===$key) aria-current="page" @endif class="min-h-11 rounded-lg px-4 py-3 text-xs font-bold {{ $activeSection===$key?'bg-primary text-white':'bg-white text-primary' }}">{{ $label }}</a>@endforeach</nav>
<div class="grid gap-5 xl:grid-cols-[1fr_300px]"><section class="rounded-xl border border-outline-variant/60 bg-white p-5">@include('partials.procurement.request-'.$activeSection)</section><aside class="rounded-xl border border-outline-variant/60 bg-white p-5"><h2 class="font-bold">Requirements</h2><p class="mt-1 text-xs text-on-surface-variant">{{ $workspace['documents']['completed'] }} of {{ $workspace['documents']['total'] }} core documents complete.</p><div class="mt-4 space-y-2">@forelse($workspace['documents']['missing'] as $missing)<div class="flex gap-2 rounded-lg bg-attention/10 p-3 text-xs"><span class="material-symbols-outlined text-[17px] text-attention">warning</span><span><strong class="block">{{ $missing['label'] }}</strong><small class="text-on-surface-variant">Required to continue the workflow</small></span></div>@empty<div class="rounded-lg bg-secondary/10 p-3 text-xs font-bold text-secondary">Core documents complete</div>@endforelse</div></aside></div>
@endsection
