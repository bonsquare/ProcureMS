@extends('layouts.procurement')
@section('title', $school->name) @section('page-title', 'School Management')
@section('workspace-label', 'Master account') @section('hide-module-tabs', '1') @section('flash-handled', '1')
@section('content')
@php
    $dialog = 'w-[min(94vw,28rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40';
    $schoolActive = $school->status === 'active';
    $detail = fn (string $label, $value, bool $wide = false) => compact('label', 'value', 'wide');
    $schoolFacts = [
        $detail('School ID', $school->code), $detail('School type', $school->school_type), $detail('Region', $school->region),
        $detail('Division', $school->division), $detail('District', $school->district), $detail('School head', $school->school_head),
        $detail('Email', $school->contact_email), $detail('Contact number', $school->contact_number), $detail('Registered', $school->created_at?->format('M d, Y')),
        $detail('Address', $school->address, true),
    ];
@endphp
@if(session('success'))<div role="status" class="civic-alert civic-alert--success">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="civic-alert civic-alert--error">{{ $errors->first() }}</div>@endif

<a href="{{ route('school-management') }}" class="mb-3 inline-flex items-center gap-1 text-xs font-bold text-action hover:underline"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>All schools</a>

@foreach($handovers as $handover)
    <section class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-secondary/40 bg-secondary/5 px-5 py-3.5">
        <div class="flex gap-3"><span class="material-symbols-outlined text-[24px] text-secondary" aria-hidden="true">supervisor_account</span><div><p class="font-bold text-secondary">Handover until {{ $handover->handover_ends_at?->format('M d, Y') }}</p><p class="text-xs text-on-surface-variant">{{ $handover->user?->name }} and {{ $handover->handoverUser?->name }} both have access to {{ $school->name }}. Then {{ $handover->handoverUser?->name }} is set inactive (Transferred).</p></div></div>
        <form method="POST" action="{{ route('school-management.handover.end', $handover) }}" onsubmit="return confirm('End the handover now? {{ $handover->handoverUser?->name }} will be set inactive.')">@csrf<button class="rounded-lg border border-error/40 bg-white px-4 py-2 text-xs font-bold text-error hover:bg-error/10">End handover now</button></form>
    </section>
@endforeach

@if($transferRequests->isNotEmpty())
    <section id="transfer" class="mb-5 overflow-hidden rounded-xl border-2 border-amber-300 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-gradient-to-r from-amber-100 to-amber-50 px-5 py-3.5">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-white text-amber-700 shadow-sm"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">swap_horiz</span></span>
                <div><h2 class="text-base font-bold text-amber-950">Transfer request <span class="ml-1 rounded-full bg-amber-300 px-2 py-0.5 text-xs">{{ $transferRequests->count() }}</span></h2><p class="text-xs text-amber-900/80">Waiting for your decision</p></div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-300"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>Pending</span>
        </div>
        <ul class="divide-y divide-outline-variant/30">
            @foreach($transferRequests as $item)
                @php $initials = str($item->user?->name)->explode(' ')->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''); @endphp
                <li class="space-y-4 px-5 py-5 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-primary text-sm font-bold text-white">{{ $initials }}</span>
                            <div><p class="font-bold">{{ $item->user?->name }}</p><p class="text-xs text-on-surface-variant">{{ $item->user?->position ?: str($item->user?->role)->replace('_', ' ')->title() }} · {{ str($item->user?->role)->replace('_', ' ')->title() }}</p></div>
                        </div>
                        <p class="text-xs text-on-surface-variant">Requested <strong class="text-on-surface">{{ $item->requested_at?->format('M d, Y') }}</strong> · {{ $item->requested_at?->diffForHumans() }}</p>
                    </div>

                    <div class="grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                        <div class="rounded-xl border border-outline-variant/60 bg-surface-low px-4 py-3"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">From</p><p class="mt-0.5 font-bold">{{ $item->fromSchool?->name ?? '—' }}</p><p class="text-xs text-on-surface-variant">{{ $item->fromSchool?->code }}</p></div>
                        <span class="material-symbols-outlined hidden text-[26px] text-action sm:block" aria-hidden="true">arrow_forward</span>
                        <div class="rounded-xl border border-primary/30 bg-primary/5 px-4 py-3"><p class="text-[10px] font-bold uppercase tracking-wide text-primary">To @if(! $item->to_school_id)<span class="ml-1 rounded bg-primary px-1.5 py-0.5 text-[9px] text-white">New school</span>@endif</p><p class="mt-0.5 font-bold">{{ $item->destinationName() }}</p><p class="text-xs text-on-surface-variant">{{ $item->toSchool?->code ?? 'Will be registered on approval' }}</p></div>
                    </div>

                    <div class="rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-3">
                        <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-amber-800"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">format_quote</span>Reason for transfer</p>
                        <p class="mt-1 text-base font-semibold leading-snug text-amber-950">{{ $item->reason }}</p>
                    </div>

                    @if($item->review_status === 'pending')
                        <p class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">hourglass_top</span>Waiting for {{ $item->destinationName() }} to accept, until {{ $item->review_expires_at?->format('M d, Y') }}. Approval unlocks when they accept.</p>
                    @elseif($item->review_status === 'accepted')
                        <p class="flex items-center gap-2 rounded-lg border border-secondary/40 bg-secondary/10 px-3 py-2 text-xs font-semibold text-secondary"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">handshake</span>Accepted by {{ $item->destinationName() }} on {{ $item->reviewed_at?->format('M d, Y') }}@if($item->review_note) · {{ $item->review_note }}@endif. Both users share the school for {{ \App\Services\StationTransferService::HANDOVER_DAYS }} days from that date.</p>
                    @endif
                    <p class="flex gap-2 rounded-lg bg-surface-low px-3 py-2.5 text-xs text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-action" aria-hidden="true">info</span><span>If approved, <strong>{{ $item->fromSchool?->name }}</strong> stays active and becomes vacant. The user keeps their role and subscription, and confirms the new station at next sign-in.</span></p>

                    <div class="flex flex-wrap items-center gap-2 border-t border-outline-variant/30 pt-4">
                        @if($item->canBeApproved())
                            <form method="POST" action="{{ route('transfer-requests.approve', $item) }}" class="flex flex-1 flex-wrap gap-2">@csrf<input type="hidden" name="back" value="school"><input name="decision_note" placeholder="Note to the user (optional)" class="min-w-[200px] flex-1 rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action"><button class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>Approve</button></form>
                        @else
                            <span class="flex-1 text-xs text-on-surface-variant">Approval is locked until the destination school accepts.</span>
                        @endif
                        <form method="POST" action="{{ route('transfer-requests.decline', $item) }}">@csrf<input type="hidden" name="back" value="school"><button class="inline-flex items-center gap-1.5 rounded-lg border border-error/40 px-5 py-2 text-xs font-bold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>Decline</button></form>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif

<div class="grid items-start gap-5 xl:grid-cols-2">
    {{-- School details --}}
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white text-primary shadow-sm"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">domain</span></span>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">School details</p><h1 class="truncate text-lg font-bold text-primary">{{ $school->name }}</h1></div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold {{ $schoolActive ? 'bg-secondary/10 text-secondary' : 'bg-surface-high text-on-surface-variant' }}"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $schoolActive ? 'check_circle' : 'block' }}</span>{{ $schoolActive ? 'Active' : 'Inactive' }}</span>
        </div>
        <dl class="grid gap-x-6 gap-y-3.5 px-5 py-4 sm:grid-cols-2">
            @foreach($schoolFacts as $fact)
                <div class="{{ $fact['wide'] ? 'sm:col-span-2' : '' }}"><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $fact['label'] }}</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ filled($fact['value']) ? $fact['value'] : '—' }}</dd></div>
            @endforeach
        </dl>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/30 bg-surface-low px-5 py-3">
            <p class="max-w-md text-xs text-on-surface-variant">An inactive school cannot sign in and cannot receive a transferred user. Its records are kept.</p>
            <form method="POST" action="{{ route('school-management.status', $school) }}" onsubmit="return confirm('{{ $schoolActive ? 'Set this school inactive? Its user will be signed out.' : 'Activate this school?' }}')">@csrf<input type="hidden" name="active" value="{{ $schoolActive ? 0 : 1 }}"><button class="rounded-lg px-4 py-2 text-xs font-bold {{ $schoolActive ? 'border border-error/40 bg-white text-error hover:bg-error/10' : 'bg-primary text-white hover:bg-primary-container' }}">{{ $schoolActive ? 'Set school inactive' : 'Activate school' }}</button></form>
        </div>
    </section>

    {{-- User details --}}
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3.5">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white text-primary shadow-sm"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">manage_accounts</span></span>
            <div><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">User details</p><h2 class="text-lg font-bold text-primary">System user</h2></div>
        </div>
        @forelse($users as $user)
            @php
                $active = ($user->status ?: 'active') === 'active';
                $plan = $user->activeSubscription();
                $facts = [
                    $detail('Username', $user->username), $detail('User ID', $user->user_code), $detail('Email', $user->email), $detail('Mobile number', $user->phone),
                    $detail('Position', $user->position), $detail('System role', str($user->role)->replace('_', ' ')->title()),
                    $detail('Last sign-in', $user->last_login_at?->format('M d, Y g:i A') ?? 'Never'),
                    $detail('Subscription', $plan ? ucfirst($plan->plan).' · '.ucfirst($plan->status ?: $plan->payment_status).($plan->subscription_end ? ' · until '.\Illuminate\Support\Carbon::parse($plan->subscription_end)->format('M d, Y') : '') : 'None'),
                ];
            @endphp
            <div class="px-5 py-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-base font-bold">{{ $user->name }}</p>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $active ? 'bg-secondary/10 text-secondary' : 'bg-surface-high text-on-surface-variant' }}"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $active ? 'check_circle' : 'block' }}</span>{{ $active ? 'Active' : 'Inactive' }}</span>
                </div>
                <dl class="grid gap-x-6 gap-y-3.5 sm:grid-cols-2">
                    @foreach($facts as $fact)
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $fact['label'] }}</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ filled($fact['value']) ? $fact['value'] : '—' }}</dd></div>
                    @endforeach
                </dl>
                @unless($active)
                    <p class="mt-3 rounded-lg bg-surface-low px-3 py-2 text-xs"><strong>{{ $user->deactivation_reason ?: 'Inactive' }}</strong>@if($user->deactivated_at) · {{ $user->deactivated_at->format('M d, Y') }}@endif @if($user->deactivation_note) · {{ $user->deactivation_note }}@endif</p>
                @endunless
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/30 bg-surface-low px-5 py-3">
                <p class="max-w-md text-xs text-on-surface-variant">One user manages one school. Setting the user inactive makes the school vacant; the subscription and records are kept.</p>
                @if($active)
                    <button type="button" data-open="user-off-{{ $user->id }}" class="rounded-lg border border-error/40 bg-white px-4 py-2 text-xs font-bold text-error hover:bg-error/10">Set user inactive</button>
                @else
                    <form method="POST" action="{{ route('school-management.users.reactivate', $user) }}">@csrf<button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Reactivate user</button></form>
                @endif
            </div>
            @if($active)
                <dialog id="user-off-{{ $user->id }}" class="{{ $dialog }}">
                    @include('partials.school-management.deactivate-form', ['action' => route('school-management.users.deactivate', $user), 'title' => 'Set '.$user->name.' inactive', 'help' => 'They cannot sign in any more and the school becomes vacant. Their subscription and the school records are kept.'])
                </dialog>
            @endif
        @empty
            <div class="px-5 py-12 text-center text-sm text-on-surface-variant"><span class="material-symbols-outlined mb-1 block text-[34px] text-outline-variant" aria-hidden="true">person_off</span>This school has no user. Move one in with a station transfer.</div>
        @endforelse
    </section>
</div>

<script>
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open]');
        if (opener) { document.getElementById(opener.dataset.open)?.showModal(); return; }
        const closer = event.target.closest('[data-close]');
        if (closer) { closer.closest('dialog')?.close(); return; }
        if (event.target instanceof HTMLDialogElement) { event.target.close(); }
    });
</script>
@endsection
