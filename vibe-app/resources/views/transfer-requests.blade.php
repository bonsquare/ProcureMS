@extends('layouts.procurement')
@section('title', 'Transfer requests') @section('page-title', 'Transfer requests')
@section('workspace-label', 'Account') @section('hide-module-tabs', '1')
@section('content')
@php $field = 'w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action'; $tones = ['approved' => 'bg-secondary/10 text-secondary', 'declined' => 'bg-error/10 text-error', 'cancelled' => 'bg-surface-high text-on-surface-variant', 'expired' => 'bg-surface-high text-on-surface-variant']; @endphp
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Master account</p>
    <h1 class="mt-2 text-3xl font-bold">Transfer requests</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Users who were reassigned and ask to move to another Official Station. Approving moves the account and its subscription; the school they leave keeps its data.</p>
</header>
<section class="mb-6 overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">Pending <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $pending->count() }}</span></h2></div>
    <ul class="divide-y divide-outline-variant/30">
        @forelse($pending as $item)
            <li class="px-5 py-4 text-sm">
                <p class="font-semibold">{{ $item->user?->name }} <span class="font-normal text-on-surface-variant">· {{ str($item->user?->role)->replace('_', ' ')->title() }}</span></p>
                <p class="mt-1">{{ $item->fromSchool?->name ?? '—' }} <span class="text-on-surface-variant">→</span> <strong>{{ $item->destinationName() }}</strong>@if(! $item->to_school_id) <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">New school</span>@endif</p>
                <p class="mt-1 text-xs text-on-surface-variant">Requested {{ $item->requested_at?->format('M d, Y') }}</p>
                <div class="mt-2 rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-amber-800">Reason for transfer</p><p class="mt-0.5 font-semibold text-amber-950">{{ $item->reason }}</p></div>
                @if($item->review_status === 'pending')
                    <p class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">hourglass_top</span>Waiting for {{ $item->destinationName() }} to accept, until {{ $item->review_expires_at?->format('M d, Y') }}. Approval unlocks when they accept.</p>
                @elseif($item->review_status === 'accepted')
                    <p class="flex items-center gap-2 rounded-lg border border-secondary/40 bg-secondary/10 px-3 py-2 text-xs font-semibold text-secondary"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">handshake</span>Accepted by {{ $item->destinationName() }} on {{ $item->reviewed_at?->format('M d, Y') }}@if($item->review_note) · {{ $item->review_note }}@endif. Both users share the school for {{ \App\Services\StationTransferService::HANDOVER_DAYS }} days from that date.</p>
                @endif
                <p class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900"><strong>Note:</strong> {{ $item->fromSchool?->name }} will have no user after this transfer. It keeps all its data and waits for its next user. The user's subscription goes with them.</p>
                @if(! $item->to_school_id)<p class="mt-2 rounded-lg bg-surface-low px-3 py-2 text-xs text-on-surface-variant">Approving registers <strong>{{ $item->proposed_school['name'] ?? '' }}</strong> as a new school. It has no plan of its own; the user's plan applies.</p>@endif
                <p class="mt-2 text-xs text-on-surface-variant">On approval: their employee record at {{ $item->fromSchool?->name }} is ended (kept as history), a new record with no roles is created at the new school, and their system role stays <strong>{{ str($item->user?->role)->replace('_', ' ')->title() }}</strong>.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if($item->canBeApproved())
                        <form method="POST" action="{{ route('transfer-requests.approve', $item) }}" class="flex flex-1 flex-wrap gap-2">@csrf<input name="decision_note" placeholder="Note (optional)" class="{{ $field }} min-w-[180px] flex-1"><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Approve</button></form>
                    @else
                        <span class="flex-1 text-xs text-on-surface-variant">Approval is locked until the destination school accepts.</span>
                    @endif
                    <form method="POST" action="{{ route('transfer-requests.decline', $item) }}">@csrf<button class="rounded-lg border border-error/40 px-4 py-2 text-xs font-bold text-error hover:bg-error/10">Decline</button></form>
                </div>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-on-surface-variant">No pending transfer requests.</li>
        @endforelse
    </ul>
</section>
<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">History</h2></div>
    <ul class="divide-y divide-outline-variant/30 text-sm">
        @forelse($history as $item)
            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                <div><p class="font-semibold">{{ $item->user?->name }}: {{ $item->fromSchool?->name ?? '—' }} → {{ $item->destinationName() }}</p><p class="text-xs text-on-surface-variant">{{ $item->decided_at?->format('M d, Y') ?? $item->requested_at?->format('M d, Y') }}@if($item->decider) · by {{ $item->decider->name }}@endif @if($item->decision_note) · {{ $item->decision_note }}@endif</p></div>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tones[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span>
            </li>
        @empty
            <li class="px-5 py-6 text-center text-sm text-on-surface-variant">Nothing decided yet.</li>
        @endforelse
    </ul>
</section>
@endsection
