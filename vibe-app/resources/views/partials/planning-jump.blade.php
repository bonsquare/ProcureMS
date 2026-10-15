{{-- "Back to Planning" and the jump buttons (SIP, AIP, SOB, APP) shown at the top of every planning page. $schoolId, $year (optional), $backTo (the tab the page belongs to). --}}
@php $jumpQuery = array_filter(['school_id' => $schoolId, 'year' => $year ?? null]); @endphp
<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('planning', $jumpQuery) }}#{{ $backTo }}" class="mr-2 text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    @foreach(['sip' => ['SIP', 'flag'], 'aip' => ['AIP', 'event_note'], 'sob' => ['SOB', 'account_balance_wallet'], 'app' => ['APP', 'inventory_2']] as $jumpTab => [$jumpLabel, $jumpIcon])
        <a href="{{ route('planning', $jumpQuery) }}#{{ $jumpTab }}" title="Go to the {{ $jumpLabel }}" class="inline-flex items-center gap-1 rounded-lg border border-outline-variant/60 bg-white px-2.5 py-1 text-xs font-bold text-primary shadow-sm hover:border-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $jumpIcon }}</span>{{ $jumpLabel }}</a>
    @endforeach
</div>
