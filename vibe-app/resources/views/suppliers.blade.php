@extends('layouts.procurement')
@section('title', 'Suppliers')
@section('page-title', 'Suppliers')
@section('header-actions')
@if(request()->user()->hasPermission('supplier.manage'))
<a href="{{ route('suppliers.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-4 text-xs font-bold text-white"><span class="material-symbols-outlined text-[18px]">add</span><span>Add supplier</span></a>
@endif
@endsection
@section('content')
@php
    $canManage = request()->user()->hasPermission('supplier.manage');
    $taxLabels = ['vat' => 'VAT', 'non_vat' => 'Non-VAT', 'vat_exempt' => 'VAT exempt'];
    $expiry = function ($date) {
        if (! $date) {
            return null;
        }
        if ($date->isPast()) {
            return ['Expired', 'bg-error/10 text-error'];
        }
        if ($date->diffInDays(now()) <= 30) {
            return ['Expires soon', 'bg-attention/15 text-attention'];
        }

        return null;
    };
@endphp
<header class="mb-4 flex flex-wrap items-end justify-between gap-2">
    <div>
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Supplier manager</p>
        <h1 class="mt-1 font-bold">Supplier directory</h1>
    </div>
    <p class="text-xs text-on-surface-variant">{{ $suppliers->total() }} {{ \Illuminate\Support\Str::plural('supplier', $suppliers->total()) }}@if($filtersApplied) · filtered @endif</p>
</header>
<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-outline-variant/50 bg-surface-low/60 px-3 py-2.5">
        @if($isMasterUser && request()->filled('school_id'))<input type="hidden" name="school_id" value="{{ request('school_id') }}">@endif
        <div class="relative min-w-52 flex-1"><span class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span><input name="search" value="{{ request('search') }}" aria-label="Search suppliers" class="w-full rounded-lg border border-outline-variant bg-white py-1.5 pl-8 pr-3 text-xs" placeholder="Search business, contact, TIN"></div>
        <select name="status" aria-label="Status" class="rounded-lg border border-outline-variant bg-white px-2.5 py-1.5 text-xs"><option value="">All status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
        <button class="rounded-lg bg-primary px-3.5 py-1.5 text-xs font-bold text-white">Apply</button>
        @if($filtersApplied)<a href="{{ route('suppliers') }}" class="px-1 text-xs font-bold text-action">Reset</a>@endif
    </form>
    @if($suppliers->isEmpty())
        <x-procurement.empty-state :title="$filtersApplied ? 'No suppliers match these filters' : 'No suppliers registered'" description="Add a supplier to use it in quotations and award documents." icon="storefront" />
    @else
        <div class="civic-desktop-table overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-xs">
                <thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-3 py-2">Business</th>@if($isMasterUser)<th class="px-3 py-2">School</th>@endif<th class="px-3 py-2">Contact</th><th class="px-3 py-2">Tax / TIN</th><th class="px-3 py-2">Registrations</th><th class="px-3 py-2">Status</th><th class="w-px px-3 py-2"></th></tr></thead>
                <tbody class="divide-y divide-outline-variant/40">
                @foreach($suppliers as $supplier)
                    @php $permit = $expiry($supplier->business_permit_expiry); $philgeps = $expiry($supplier->philgeps_expiry); @endphp
                    <tr class="hover:bg-surface-low/50">
                        <td class="max-w-[22rem] px-3 py-2"><strong class="block truncate">{{ $supplier->business_name }}</strong><span class="block truncate text-on-surface-variant" title="{{ $supplier->business_address }}">{{ $supplier->business_address ?: 'Address not set' }}</span></td>
                        @if($isMasterUser)<td class="px-3 py-2 text-on-surface-variant">{{ $supplier->school?->name ?? 'Agency-wide' }}</td>@endif
                        <td class="px-3 py-2"><span class="block">{{ $supplier->contact_person ?: '—' }}</span><span class="block text-on-surface-variant">{{ collect([$supplier->phone, $supplier->email])->filter()->implode(' · ') ?: 'No contact details' }}</span></td>
                        <td class="px-3 py-2"><span class="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-bold">{{ $taxLabels[$supplier->tax_type] ?? '—' }}</span><span class="mt-0.5 block text-on-surface-variant">{{ $supplier->tin ?: 'No TIN' }}</span></td>
                        <td class="px-3 py-2"><span class="block">Permit {{ $supplier->business_permit_no ?: '—' }} @if($permit)<em class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold not-italic {{ $permit[1] }}">{{ $permit[0] }}</em>@endif</span><span class="block text-on-surface-variant">PhilGEPS {{ $supplier->philgeps_no ?: '—' }} @if($philgeps)<em class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold not-italic {{ $philgeps[1] }}">{{ $philgeps[0] }}</em>@endif</span></td>
                        <td class="px-3 py-2"><x-procurement.status-badge :label="str($supplier->status)->title()" :tone="$supplier->status === 'active' ? 'verified' : 'neutral'" /></td>
                        <td class="px-3 py-2 text-right">@if($canManage)<a href="{{ route('suppliers.edit', $supplier) }}" class="inline-grid h-8 w-8 place-items-center rounded-lg border border-primary/40 text-primary transition hover:bg-primary hover:text-white" aria-label="Edit supplier {{ $supplier->business_name }}" title="Edit supplier"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">edit</span></a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="civic-mobile-cards p-3">
            @foreach($suppliers as $supplier)
                <article class="rounded-lg border border-outline-variant/60 bg-white p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0"><h2 class="truncate text-sm font-bold">{{ $supplier->business_name }}</h2><p class="truncate text-xs text-on-surface-variant">{{ $isMasterUser ? ($supplier->school?->name ?? 'Agency-wide') : ($supplier->business_address ?: 'Address not set') }}</p></div>
                        <x-procurement.status-badge :label="str($supplier->status)->title()" :tone="$supplier->status === 'active' ? 'verified' : 'neutral'" />
                    </div>
                    <p class="mt-2 text-xs"><span class="font-semibold">{{ $supplier->contact_person ?: 'No contact' }}</span> · {{ $supplier->phone ?: 'No phone' }}</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">{{ $taxLabels[$supplier->tax_type] ?? '—' }} · TIN {{ $supplier->tin ?: '—' }} · PhilGEPS {{ $supplier->philgeps_no ?: '—' }}</p>
                    @if($canManage)<a href="{{ route('suppliers.edit', $supplier) }}" class="mt-2 inline-flex items-center rounded-lg border border-primary px-2.5 py-1.5 text-[11px] font-bold text-primary">Edit supplier</a>@endif
                </article>
            @endforeach
        </div>
        <div class="border-t border-outline-variant/50 px-3 py-2">{{ $suppliers->links() }}</div>
    @endif
</section>
@endsection
