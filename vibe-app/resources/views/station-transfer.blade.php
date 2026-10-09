@extends('layouts.procurement')
@section('title', 'Station transfer') @section('page-title', 'Station transfer')
@section('workspace-label', 'Account') @section('hide-module-tabs', '1')
@section('content')
@php $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20'; $tones = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-secondary/10 text-secondary', 'declined' => 'bg-error/10 text-error', 'cancelled' => 'bg-surface-high text-on-surface-variant']; @endphp
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Official Station</p>
    <h1 class="mt-2 text-3xl font-bold">Station transfer</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Your current station is <strong>{{ $station?->name ?? '—' }}</strong>. If you are reassigned, ask the master user to move you. You keep your account, username, role and subscription; you will work in the new school's data and the school you leave keeps its own.</p>
</header>
<div class="grid items-start gap-5 xl:grid-cols-[1fr_360px]">
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">My requests</h2></div>
        <ul class="divide-y divide-outline-variant/30">
            @forelse($requests as $item)
                <li class="px-5 py-3.5 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ $item->fromSchool?->name ?? '—' }} <span class="text-on-surface-variant">→</span> {{ $item->destinationName() }}@if(! $item->to_school_id) <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">New school</span>@endif</p>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tones[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant">Requested {{ $item->requested_at?->format('M d, Y') }} · {{ $item->reason }}</p>
                    @if($item->decision_note)<p class="mt-1 text-xs"><strong>Master note:</strong> {{ $item->decision_note }}</p>@endif
                    @if($item->status === 'pending')
                        <form method="POST" action="{{ route('station-transfer.cancel', $item) }}" class="mt-2">@csrf<button class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Cancel request</button></form>
                    @endif
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-on-surface-variant">You have not sent a transfer request.</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-5">
        <h2 class="font-bold">Request a station transfer</h2>
        @if($errors->any())<div class="mt-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs text-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('station-transfer.store') }}" class="mt-3 space-y-3 text-xs font-bold" id="transfer-form">
            @csrf
            <label class="block">Destination<select name="destination" id="transfer-destination" class="{{ $field }}"><option value="registered" @selected(old('destination', 'registered') === 'registered')>A registered school</option><option value="new" @selected(old('destination') === 'new')>My school isn't listed</option></select></label>
            <label class="block" data-when="registered">School <span class="font-normal text-on-surface-variant">(vacant schools only)</span><select name="to_school_id" class="{{ $field }}"><option value="">Choose a school</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) old('to_school_id') === $school->id)>{{ $school->name }}</option>@endforeach</select></label>
            <div class="space-y-3" data-when="new">
                <label class="block">School name<input name="new_school[name]" value="{{ old('new_school.name') }}" class="{{ $field }}"></label>
                <label class="block">School type<input name="new_school[school_type]" value="{{ old('new_school.school_type') }}" class="{{ $field }}"></label>
                <div class="grid grid-cols-2 gap-3"><label class="block">Region<input name="new_school[region]" value="{{ old('new_school.region') }}" class="{{ $field }}"></label><label class="block">Division<input name="new_school[division]" value="{{ old('new_school.division') }}" class="{{ $field }}"></label></div>
                <label class="block">District<input name="new_school[district]" value="{{ old('new_school.district') }}" class="{{ $field }}"></label>
                <label class="block">Address<input name="new_school[address]" value="{{ old('new_school.address') }}" class="{{ $field }}"></label>
                <div class="grid grid-cols-2 gap-3"><label class="block">School email<input type="email" name="new_school[contact_email]" value="{{ old('new_school.contact_email') }}" class="{{ $field }}"></label><label class="block">Contact number<input name="new_school[contact_number]" value="{{ old('new_school.contact_number') }}" class="{{ $field }}"></label></div>
                <p class="rounded-lg bg-surface-low px-3 py-2 font-normal text-on-surface-variant">The master user registers this school when approving your request.</p>
            </div>
            <label class="block">Reason<textarea name="reason" rows="3" required class="{{ $field }}">{{ old('reason') }}</textarea></label>
            <button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-container">Send request</button>
        </form>
    </section>
</div>
<script>
    (() => {
        const select = document.getElementById('transfer-destination');
        const sync = () => document.querySelectorAll('#transfer-form [data-when]').forEach((box) => { box.hidden = box.dataset.when !== select.value; });
        select.addEventListener('change', sync); sync();
    })();
</script>
@endsection
