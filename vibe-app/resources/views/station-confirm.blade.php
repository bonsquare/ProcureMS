@extends('layouts.procurement')
@section('title', 'Confirm station') @section('page-title', 'Confirm station')
@section('hide-module-tabs', '1')
@section('content')
<div class="mx-auto mt-6 max-w-xl rounded-xl border border-outline-variant/60 bg-white p-6 text-center">
    <span class="material-symbols-outlined text-[40px] text-secondary" aria-hidden="true">swap_horiz</span>
    <h1 class="mt-2 text-2xl font-bold">Confirm your new station</h1>
    <p class="mt-2 text-sm text-on-surface-variant">The master user approved your transfer. From now on you work in the new school's data.</p>
    <div class="mt-5 flex items-center justify-center gap-3 text-sm">
        <div class="rounded-lg bg-surface-low px-4 py-3"><p class="text-[11px] font-bold uppercase text-on-surface-variant">From</p><p class="font-bold">{{ $transfer->fromSchool?->name }}</p></div>
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        <div class="rounded-lg bg-primary/10 px-4 py-3"><p class="text-[11px] font-bold uppercase text-primary">To</p><p class="font-bold">{{ $transfer->toSchool?->name }}</p></div>
    </div>
    <p class="mt-4 text-xs text-on-surface-variant">Your system role stays <strong>{{ str($user->role)->replace('_', ' ')->title() }}</strong> and your subscription goes with you. Your employee roles at the new school are set in School Settings.</p>
    <form method="POST" action="{{ route('station.confirm.store') }}" class="mt-5">@csrf<button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-container">Confirm and continue</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-semibold hover:bg-surface-low">Log out</button></form>
</div>
@endsection
