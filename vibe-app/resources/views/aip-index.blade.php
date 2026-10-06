@extends('layouts.budget')
@section('title', 'Annual Implementation Plan')
@section('section', 'Reports')
@section('crumb', 'AIP')
@section('content')
@php $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant'; @endphp
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Annual Implementation Plan</h1>
        <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">The school's yearly activities and financial targets per quarter and source of fund. An approved AIP creates the budget allotments. <a href="{{ route('allotment-registry') }}" class="font-semibold text-primary hover:underline">Allotment Registry →</a></p>
    </div>
    @if($canManage)
        <form method="POST" action="{{ route('aip.store') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <select name="school_id" class="rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none">@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select>
            <label class="flex items-center gap-2 text-xs font-semibold text-on-surface-variant">Fiscal Year<input type="number" name="fiscal_year" value="{{ now()->year + 1 }}" min="2000" max="2100" required class="w-24 rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-xs outline-none focus:border-primary" title="Type the year this plan is for; AIPs are usually crafted a year ahead."></label>
            <button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">+ New AIP</button>
        </form>
    @endif
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">School</th><th class="{{ $th }}">Fiscal Year</th><th class="{{ $th }} text-right">Activities</th><th class="{{ $th }} text-right">Total Financial Target</th><th class="{{ $th }}">Status</th><th class="{{ $th }}"></th></tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($aips as $aip)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $aip->school?->name }}</td>
                        <td class="px-4 py-3">FY {{ $aip->fiscal_year }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $aip->activities->count() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $peso($aip->activities->sum(fn ($a) => $a->total)) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $aip->status === 'approved' ? 'bg-secondary/10 text-secondary' : 'bg-amber-100 text-amber-800' }}">{{ \Illuminate\Support\Str::headline($aip->status) }}</span></td>
                        <td class="px-4 py-3 text-right text-xs font-semibold"><a href="{{ route('aip.show', $aip) }}" class="text-primary hover:underline">Open</a> · <a href="{{ route('aip.print', $aip) }}" target="_blank" rel="noopener" class="text-primary hover:underline">Print</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">No AIP yet. @if($canManage)Pick a school and year, then click New AIP.@endif</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
