@extends('layouts.budget')
@section('title', $title)
@section('content')
@php
    $query = ['year' => $year, 'school_id' => $selectedSchoolId];
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $box = 'rounded border border-outline-variant/60 bg-white px-3 py-2 text-xs';
@endphp
<style>@media print { aside, header, .no-print { display: none !important; } .md\:pl-72 { padding-left: 0 !important; } main { padding: 0 !important; } }</style>
<div class="no-print mb-4 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('reports.generate', $type) }}" class="flex flex-wrap items-end gap-2">
        <label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">School<select name="school_id" class="{{ $box }} mt-1 block"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) $school->id === (string) $selectedSchoolId)>{{ $school->name }}</option>@endforeach</select></label>
        @if($showYear)<label class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Year<input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="{{ $box }} mt-1 block w-24"></label>@endif
        <button class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white">Generate</button>
    </form>
    <div class="flex gap-2">
        <button onclick="window.print()" class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Print / Save as PDF</button>
        <a href="{{ route('reports.generate', ['type' => $type] + $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
    </div>
</div>

<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/30 p-5">
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $title }}</h1>
        <p class="mt-1 text-xs text-on-surface-variant">@if($showYear)FY {{ $year }} · @endif{{ $schoolName ?? 'All schools' }} · Generated {{ now()->format('M d, Y') }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr>@foreach($columns as $i => $column)<th class="px-4 py-3 {{ in_array($i, $money) ? 'text-right' : '' }}">{{ $column }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-outline-variant/20">
                @forelse($lines as $line)
                    <tr class="{{ ($line[0] ?? '') === 'TOTAL' ? 'bg-surface-low/60 font-bold' : '' }}">
                        @foreach($line as $i => $cell)<td class="px-4 py-3 {{ in_array($i, $money) ? 'text-right tabular-nums' : '' }}">{{ in_array($i, $money) && $cell !== '' ? $peso($cell) : $cell }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-on-surface-variant">No data for this report.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
