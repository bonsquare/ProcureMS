@php
    $filter = 'rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-xs outline-none';
    $detail = function (?array $metadata): string {
        return collect($metadata ?? [])->map(fn ($value, $key) => str_replace('_', ' ', (string) $key).': '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value)))->implode(' · ');
    };
@endphp
<a href="{{ route('user-management') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>All users</a>
<div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Audit log</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Who did what, newest first.</p></div>
    <form method="GET" action="{{ route('audit-logs.index') }}" class="flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" type="search" placeholder="Search an action..." class="{{ $filter }} sm:w-56">
        <select name="school_id" class="{{ $filter }} max-w-[14rem]"><option value="">All Schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</select>
        <button class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-container">Filter</button>
        @if(request()->hasAny(['q', 'school_id']))<a href="{{ route('audit-logs.index') }}" class="rounded border border-outline-variant/50 px-3 py-2 text-xs font-semibold text-on-surface-variant hover:bg-surface-low">Clear</a>@endif
    </form></div>
<style>tbody[data-collapsed] tr:not([data-head]){display:none}tbody:not([data-collapsed]) .sum-chevron{transform:rotate(180deg)}</style>
<div class="mb-3 flex justify-end gap-2"><button type="button" data-log-all="1" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Expand all</button><button type="button" data-log-all="0" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Collapse all</button></div>
<section class="overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="overflow-x-auto"><table class="w-full min-w-[800px] border-collapse text-left"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr class="border-b border-outline-variant/30"><th class="px-5 py-3 font-semibold">When</th><th class="px-5 py-3 font-semibold">Action</th><th class="px-5 py-3 font-semibold">By</th><th class="px-5 py-3 font-semibold">School</th><th class="px-5 py-3 font-semibold">Details</th></tr></thead>
    @forelse($logs->groupBy(fn ($log) => $log->created_at?->format('Y-m-d') ?? 'unknown') as $day => $dayLogs)
    <tbody class="divide-y divide-outline-variant/20 text-[13px]" data-log-day @unless($loop->first) data-collapsed @endunless>
    <tr data-head class="cursor-pointer bg-surface-low/70 hover:bg-surface-high" tabindex="0" role="button"><td colspan="5" class="px-5 py-2 text-xs font-bold uppercase tracking-wide text-primary"><span class="flex items-center justify-between gap-3"><span>{{ $day === 'unknown' ? 'No date' : \Illuminate\Support\Carbon::parse($day)->format('F j, Y') }}</span><span class="flex items-center gap-2 font-semibold normal-case tracking-normal text-on-surface-variant">{{ $dayLogs->count() }} event(s)<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span></span></td></tr>
    @foreach($dayLogs as $log)<tr><td class="whitespace-nowrap px-5 py-3 text-xs text-on-surface-variant">{{ $log->created_at?->format('M d, Y H:i') }}</td><td class="px-5 py-3 font-semibold">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td><td class="px-5 py-3">{{ $log->user?->name ?? 'System' }}</td><td class="px-5 py-3 text-on-surface-variant">{{ $log->school?->name ?? '—' }}</td><td class="px-5 py-3 text-xs text-on-surface-variant">{{ $detail($log->metadata) ?: '—' }}</td></tr>@endforeach
    </tbody>
    @empty<tbody><tr><td colspan="5" class="px-5 py-10 text-center text-sm text-on-surface-variant">Nothing recorded.</td></tr></tbody>@endforelse</table></div>
    <div class="flex flex-col justify-between gap-3 border-t border-outline-variant/20 bg-surface-low px-5 py-3 text-xs text-on-surface-variant sm:flex-row sm:items-center"><span>Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} events</span>@if($logs->hasPages())<div class="flex gap-2">@if($logs->onFirstPage())<span class="rounded bg-surface-container px-3 py-1 opacity-50">Previous</span>@else<a href="{{ $logs->previousPageUrl() }}" class="rounded bg-surface-container px-3 py-1 font-semibold hover:bg-surface-high">Previous</a>@endif<span class="px-2 py-1">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>@if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}" class="rounded bg-surface-container px-3 py-1 font-semibold hover:bg-surface-high">Next</a>@else<span class="rounded bg-surface-container px-3 py-1 opacity-50">Next</span>@endif</div>@endif</div></section>
<script>
    (() => {
        const days = Array.from(document.querySelectorAll('[data-log-day]'));
        days.forEach((day) => {
            const head = day.querySelector('[data-head]');
            const toggle = () => day.toggleAttribute('data-collapsed');
            head.addEventListener('click', toggle);
            head.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(); } });
        });
        document.querySelectorAll('[data-log-all]').forEach((button) => button.addEventListener('click', () => days.forEach((day) => day.toggleAttribute('data-collapsed', button.dataset.logAll !== '1'))));
    })();
</script>
