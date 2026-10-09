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
            <p class="max-w-md text-xs text-on-surface-variant">An inactive school cannot sign in and cannot receive a transferred user. Its records are kept.@if($pendingTransfers) <strong class="text-amber-800">{{ $pendingTransfers }} transfer request pending.</strong>@endif</p>
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
