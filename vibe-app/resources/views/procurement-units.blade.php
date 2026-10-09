@extends('layouts.procurement')
@section('title', 'Units') @section('page-title', 'Units')
@section('content')
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Item setup</p>
    <h1 class="mt-2 text-3xl font-bold">Units of measure</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Every unit shown in the Unit dropdown of Purchase Request items. Add a new one when you need it.</p>
</header>
<div class="grid items-start gap-5 xl:grid-cols-[1fr_340px]">
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">All units</h2><p class="mt-1 text-xs text-on-surface-variant">{{ count($defaultUnits) + $customUnits->count() }} available</p></div>
        <ul class="divide-y divide-outline-variant/30">
            @foreach($defaultUnits as $unit)
                <li class="flex items-center justify-between gap-3 px-5 py-3"><span class="text-sm font-semibold">{{ $unit }}</span><span class="rounded-full bg-surface-container px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Built-in</span></li>
            @endforeach
            @foreach($customUnits as $unit)
                <li class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-sm font-semibold">{{ $unit->name }}</span>
                    @if(request()->user()->hasPermission('supplier.manage'))
                        <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Remove the unit {{ addslashes($unit->name) }}? Existing requests keep the unit they already use.')">@csrf @method('DELETE')<button class="rounded-lg border border-error/50 px-3 py-1.5 text-xs font-bold text-error hover:bg-error hover:text-white">Remove</button></form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @if(request()->user()->hasPermission('supplier.manage'))
        <aside class="rounded-xl border border-outline-variant/60 bg-white p-5">
            <h2 class="font-bold">Add a unit</h2>
            <p class="mt-1 text-xs text-on-surface-variant">For example: bottle, roll, gallon, pair, unit, lot.</p>
            <form method="POST" action="{{ route('units.store') }}" class="mt-4 space-y-3">@csrf
                <label class="block text-xs font-bold">Unit name<input name="name" value="{{ old('name') }}" required maxlength="50" autocomplete="off" placeholder="e.g. bottle" class="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 text-sm">@error('name')<span class="mt-1 block text-xs font-normal text-error">{{ $message }}</span>@enderror</label>
                <button class="w-full rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-white">Add unit</button>
            </form>
        </aside>
    @endif
</div>
@endsection
