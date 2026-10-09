@extends('layouts.budget')

@section('title', 'Transaction '.$transaction->transaction_number)
@section('section', 'Transaction Record')
@section('crumb', $transaction->transaction_number)

@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
@endphp
<div class="mb-6 flex flex-wrap items-end justify-between gap-4"><div><a href="{{ route('planning', ['school_id' => $transaction->school_id, 'year' => $transaction->fiscal_year]) }}" class="mb-3 inline-flex items-center gap-1 text-xs font-semibold text-primary"><span class="material-symbols-outlined text-base">arrow_back</span>Planning</a><h1 class="text-3xl font-semibold tracking-tight">{{ $transaction->title }}</h1><p class="mt-2 text-sm text-on-surface-variant">{{ $transaction->transaction_number }} · {{ $transaction->school?->name }} · FY {{ $transaction->fiscal_year }}</p></div><span class="rounded bg-primary/10 px-3 py-2 text-xs font-semibold text-primary">{{ str($transaction->status)->replace('_', ' ')->title() }}</span></div>

<div class="mb-6 grid gap-4 md:grid-cols-4">
    <div class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-xs text-on-surface-variant">AIP</p><p class="mt-2 text-sm font-semibold">{{ $transaction->aip ? ucfirst($transaction->aip->status).' · '.$transaction->aip->fiscal_year : 'Not linked' }}</p>@if($transaction->aip)<a href="{{ route('aip.show', $transaction->aip) }}" class="mt-2 inline-block text-xs text-primary underline">Open AIP</a>@endif</div>
    <div class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-xs text-on-surface-variant">Budget Lines</p><p class="mt-2 text-xl font-semibold">{{ $transaction->budgetAllocations->count() }}</p><p class="text-xs text-on-surface-variant">{{ $peso($transaction->budgetAllocations->sum('amount')) }} total allocation</p></div>
    <div class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-xs text-on-surface-variant">Procurement Requests</p><p class="mt-2 text-xl font-semibold">{{ $transaction->procurementRequests->count() }}</p><p class="text-xs text-on-surface-variant">{{ $peso($transaction->procurementRequests->sum('amount')) }} total PR</p></div>
    <div class="rounded border border-outline-variant/30 bg-white p-4"><p class="text-xs text-on-surface-variant">Liquidation / ORS</p><p class="mt-2 text-xl font-semibold">{{ $transaction->liquidationReports->count() }}</p><p class="text-xs text-on-surface-variant">{{ $peso($transaction->liquidationReports->sum('amount')) }} total ORS</p></div>
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
    <section class="rounded border border-outline-variant/30 bg-white"><div class="border-b border-outline-variant/20 px-5 py-4"><h2 class="font-semibold">Linked Records</h2></div><div class="divide-y divide-outline-variant/20">
        @forelse($transaction->sipProjects as $sip)<div class="px-5 py-3"><p class="text-sm font-semibold">SIP · {{ $sip->project }}</p><p class="mt-1 text-xs text-on-surface-variant">SY {{ $sip->school_year }} · {{ $sip->goal }}</p></div>@empty<div class="px-5 py-3 text-xs text-on-surface-variant">No SIP project linked.</div>@endforelse
        @if($transaction->aip?->sipProject)<div class="px-5 py-3"><p class="text-sm font-semibold">SIP Priority · {{ $transaction->aip->sipProject->project }}</p><p class="mt-1 text-xs text-on-surface-variant">Linked through the FY {{ $transaction->aip->fiscal_year }} AIP.</p></div>@endif
        @foreach($transaction->budgetAllocations as $line)<div class="px-5 py-3"><p class="text-sm font-semibold">Budget · {{ $line->particulars }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $line->budget_ref_no }} · {{ $line->source_of_fund }} · {{ $peso($line->amount) }}</p></div>@endforeach
        @foreach($transaction->procurementRequests as $pr)<div class="px-5 py-3"><p class="text-sm font-semibold">PR · {{ $pr->request_number }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $pr->title }} · {{ ucfirst(str_replace('_', ' ', $pr->status)) }} · {{ $peso($pr->amount) }}</p><a href="{{ route('procurement.show', $pr) }}" class="mt-2 inline-flex min-h-11 items-center text-xs font-semibold text-primary underline">Open Procurement workspace</a></div>@endforeach
        @foreach($transaction->liquidationReports as $report)<div class="px-5 py-3"><p class="text-sm font-semibold">ORS · {{ $report->ors_number ?: $report->report_number }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $report->purpose }} · {{ ucfirst(str_replace('_', ' ', $report->status)) }} · {{ $peso($report->amount) }}@if($report->dv_number) · DV {{ $report->dv_number }}@endif</p></div>@endforeach
        @if(!$transaction->budgetAllocations->count() && !$transaction->procurementRequests->count() && !$transaction->liquidationReports->count() && !$transaction->sipProjects->count())<div class="px-5 py-8 text-center text-sm text-on-surface-variant">No linked downstream records yet.</div>@endif
    </div></section>
    <section class="rounded border border-outline-variant/30 bg-white"><div class="border-b border-outline-variant/20 px-5 py-4"><h2 class="font-semibold">Transaction Timeline</h2><p class="mt-1 text-xs text-on-surface-variant">Recorded workflow events across modules</p></div><div class="divide-y divide-outline-variant/20">
        @forelse($transaction->events as $event)<div class="flex gap-3 px-5 py-4"><div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-primary"></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-baseline justify-between gap-2"><p class="text-sm font-semibold">{{ str($event->module)->replace('_', ' ')->title() }} · {{ str($event->action)->replace('_', ' ')->title() }}</p><time class="text-[11px] text-on-surface-variant">{{ $event->created_at->format('M d, Y · g:i A') }}</time></div><p class="mt-1 text-xs text-on-surface-variant">{{ $event->remarks ?: ($event->previous_status && $event->new_status ? ucfirst($event->previous_status).' → '.ucfirst($event->new_status) : 'Status recorded') }} · {{ $event->user?->name ?? 'System' }}</p></div></div>@empty<div class="px-5 py-8 text-center text-sm text-on-surface-variant">No timeline events yet.</div>@endforelse
    </div></section>
</div>
@endsection
