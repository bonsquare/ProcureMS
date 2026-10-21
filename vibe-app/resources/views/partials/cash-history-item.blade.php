@php $keptParams = $entry->params ?: array_filter(['school_id' => $entry->school_id, 'from' => $entry->period_from->toDateString(), 'to' => $entry->period_to->toDateString(), 'source_of_fund' => $entry->source_of_fund, 'report_no' => $entry->report_no]); @endphp
<tr data-history-item>
    <td class="px-4 py-2.5">{{ $entry->created_at->format('M d, Y g:i A') }}</td>
    <td class="px-3 py-2.5 font-semibold">{{ $entry->period_text }}</td>
    <td class="px-3 py-2.5">{{ $entry->source_of_fund ?: 'All funds' }}</td>
    <td class="px-3 py-2.5">{{ $entry->report_no ?: '—' }}</td>
    <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $entry->total_payments, 2) }}</td>
    <td class="px-3 py-2.5">{{ $entry->generator?->name }}</td>
    <td class="px-4 py-2.5 text-right"><div class="flex items-center justify-end gap-1.5">
        <a href="{{ route('cash.report.history', $entry->id) }}" target="_blank" rel="noopener" class="rounded border border-outline-variant/60 px-3 py-1.5 font-semibold text-primary hover:bg-primary hover:text-white">Open</a>
        <button type="button" data-history-edit data-kind="{{ $entry->report }}" data-url="{{ route('cash.report.history.update', $entry->id) }}" data-name="{{ $entry->label() }} · {{ $entry->period_text }}" data-params='@json($keptParams)' title="Edit" aria-label="Edit {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span></button>
        <form method="POST" action="{{ route('cash.report.history.destroy', $entry->id) }}" data-guard="Delete {{ $entry->label() }} · {{ $entry->period_text }} from the history">@csrf @method('DELETE')<button title="Delete from the history" aria-label="Delete {{ $entry->label() }}" class="inline-flex h-8 w-8 items-center justify-center rounded border border-error/50 text-error hover:bg-error hover:text-white"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button></form>
    </div></td>
</tr>
