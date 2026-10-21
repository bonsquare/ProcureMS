@php $keptParams = $entry->params ?: array_filter(['school_id' => $entry->school_id, 'from' => $entry->period_from->toDateString(), 'to' => $entry->period_to->toDateString(), 'source_of_fund' => $entry->source_of_fund, 'report_no' => $entry->report_no]); @endphp
<li data-history-item class="rounded-lg border border-outline-variant/50 bg-white p-3 text-xs shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0"><p class="font-semibold text-primary">{{ $entry->period_text }}</p><p class="text-on-surface-variant">{{ $entry->source_of_fund ?: 'All funds' }}@if($entry->report_no) · No. {{ $entry->report_no }}@endif</p></div>
        <p class="shrink-0 text-right font-semibold tabular-nums">{{ number_format((float) $entry->total_payments, 2) }}</p>
    </div>
    <p class="mt-1 text-[11px] text-on-surface-variant">{{ $entry->created_at->format('M d, Y g:i A') }}@if($entry->generator) · {{ $entry->generator->name }}@endif</p>
    <div class="mt-2 flex items-center gap-1.5">
        <a href="{{ route('cash.report.history', $entry->id) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 px-3 py-1.5 font-semibold text-primary hover:bg-primary hover:text-white">Open</a>
        <button type="button" data-history-edit data-kind="{{ $entry->report }}" data-url="{{ route('cash.report.history.update', $entry->id) }}" data-name="{{ $entry->label() }} · {{ $entry->period_text }}" data-params='@json($keptParams)' title="Edit" aria-label="Edit {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></button>
        <form method="POST" action="{{ route('cash.report.history.destroy', $entry->id) }}" data-guard="Delete {{ $entry->label() }} · {{ $entry->period_text }} from the history">@csrf @method('DELETE')<button title="Delete from the history" aria-label="Delete {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-error/50 text-error hover:bg-error hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button></form>
    </div>
</li>
