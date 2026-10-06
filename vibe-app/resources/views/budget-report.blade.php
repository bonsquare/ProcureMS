@extends('layouts.budget')
@section('title', $title)
@section('content')
@php $query = ['year' => $year, 'school_id' => $selectedSchoolId]; @endphp
<style>@media print { aside, header, .no-print { display: none !important; } .md\:pl-72 { padding-left: 0 !important; } main { padding: 0 !important; } }</style>
<div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('budget.allocation', $query) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">← Budget Allocation</a>
    <div class="flex gap-2">
        <button onclick="window.print()" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Print / Save as PDF</button>
        <a href="{{ route('budget.allocation.report', ['type' => $type] + $query + ['format' => 'csv']) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">Export to Excel</a>
    </div>
</div>
<div class="no-print mb-4 flex flex-wrap gap-2">
    @foreach($reports as $key => $label)<a href="{{ route('budget.allocation.report', ['type' => $key] + $query) }}" class="rounded px-3 py-1.5 text-xs font-semibold {{ $key === $type ? 'bg-primary text-white' : 'border border-outline-variant/60 bg-white text-primary hover:bg-surface-low' }}">{{ $label }}</a>@endforeach
</div>

<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="border-b border-outline-variant/30 p-5">
        <h1 class="text-xl font-semibold">{{ $title }}</h1>
        <p class="mt-1 text-xs text-on-surface-variant">FY {{ $year }} · {{ $schoolName ?? 'All schools' }} · Generated {{ now()->format('M d, Y') }}</p>
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
@if(request('print'))@push('scripts')<script>window.addEventListener('load', () => window.print());</script>@endpush @endif
@endsection
