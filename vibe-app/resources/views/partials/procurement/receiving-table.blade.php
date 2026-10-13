@php $receivingGroups = $receivingRequests->groupBy(fn ($request) => $request->receiving['label']); @endphp
<style>tbody[data-collapsed] tr:not([data-head]){display:none}tbody:not([data-collapsed]) .sum-chevron,details[open] > summary .sum-chevron{transform:rotate(180deg)}</style>
<div class="flex justify-end gap-2 border-b border-outline-variant/50 px-4 py-3"><button type="button" data-recv-all="1" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Expand all</button><button type="button" data-recv-all="0" class="rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Collapse all</button></div>
<div class="civic-desktop-table overflow-x-auto">
    <table class="w-full min-w-[1080px] text-left text-xs">
        <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="p-4">Request</th><th class="p-4">Supplier / latest document</th><th class="p-4 text-right">Ordered</th><th class="p-4 text-right">Received</th><th class="p-4 text-right">Balance</th><th class="p-4">Status</th><th class="p-4 text-right">Action</th></tr></thead>
        @foreach($receivingGroups as $groupLabel => $groupRequests)
        <tbody data-recv-group @if($groupRequests->first()->receiving['status'] === 'complete') data-collapsed @endif>
        <tr data-head class="cursor-pointer border-b border-outline-variant/50 bg-surface-low/70 hover:bg-surface-high" tabindex="0" role="button"><td colspan="7" class="p-3 text-xs font-bold uppercase tracking-wide text-primary"><span class="flex items-center justify-between gap-3"><span>{{ $groupLabel }}</span><span class="flex items-center gap-2 font-semibold normal-case tracking-normal text-on-surface-variant">{{ $groupRequests->count() }} request(s)<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span></span></td></tr>
        @foreach($groupRequests as $request)
            @php($receiving = $request->receiving)
            <tr class="border-b border-outline-variant/50 align-top last:border-0 {{ $receiving['status'] === 'partial' ? 'bg-attention/5' : '' }}">
                <td class="p-4"><a class="font-bold text-primary hover:underline" href="{{ route('procurement.show', $request) }}">{{ $request->request_number }}</a><p class="mt-1 text-on-surface-variant">{{ $request->title }}</p><p class="mt-1 text-on-surface-variant">{{ $request->school?->name }}</p></td>
                <td class="p-4"><strong>{{ $receiving['supplier'] ?: 'Supplier not recorded' }}</strong><p class="mt-1 text-on-surface-variant">{{ $receiving['latest_receiving_document']?->document_number ?: 'No receiving document' }}</p></td>
                <td class="p-4 text-right font-semibold">{{ number_format($receiving['ordered_quantity'], 2) }}</td>
                <td class="p-4 text-right font-semibold">{{ number_format($receiving['received_quantity'], 2) }}</td>
                <td class="p-4 text-right font-bold {{ $receiving['balance_quantity'] > 0 ? 'text-error' : 'text-secondary' }}">{{ number_format($receiving['balance_quantity'], 2) }}</td>
                <td class="p-4"><x-procurement.status-badge :label="$receiving['label']" :tone="$receiving['tone']" /></td>
                <td class="p-4 text-right"><div class="flex justify-end gap-2"><a href="{{ route('procurement.documents', [$request, 'stage' => 'receiving']) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-3 font-bold text-white">{{ in_array($receiving['status'], ['partial', 'missing_iar', 'missing_po', 'missing_both']) ? 'Complete workflow' : 'Inspect documents' }}</a>@if($receiving['purchase_order'] && $receiving['latest_receiving_document'])<a href="{{ route('procurement.delivery-reconciliation', $request) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-outline-variant px-3 font-bold text-primary">Reconcile</a>@endif</div></td>
            </tr>
        @endforeach
        </tbody>
        @endforeach
    </table>
</div>
<div class="civic-mobile-cards gap-3 p-3">
    @foreach($receivingGroups as $groupLabel => $groupRequests)
    <details data-recv-card-group @if($groupRequests->first()->receiving['status'] !== 'complete') open @endif class="group">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg bg-surface-low px-3 py-2.5 text-xs font-bold uppercase tracking-wide text-primary [&::-webkit-details-marker]:hidden"><span>{{ $groupLabel }}</span><span class="flex items-center gap-2 font-semibold normal-case tracking-normal text-on-surface-variant">{{ $groupRequests->count() }} request(s)<span class="material-symbols-outlined sum-chevron shrink-0 text-[20px] transition-transform" aria-hidden="true">expand_more</span></span></summary>
    <div class="mt-3 grid gap-3">
    @foreach($groupRequests as $request)
        @php($receiving = $request->receiving)
        <article class="rounded-lg border border-outline-variant/60 bg-white p-4 {{ $receiving['status'] === 'partial' ? 'bg-attention/5' : '' }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><a class="font-bold text-primary hover:underline" href="{{ route('procurement.show', $request) }}">{{ $request->request_number }}</a><p class="mt-1 text-xs text-on-surface-variant">{{ $request->school?->name }}</p></div>
                <x-procurement.status-badge :label="$receiving['label']" :tone="$receiving['tone']" />
            </div>
            <p class="mt-3 text-sm font-semibold">{{ $request->title }}</p>
            <p class="mt-1 text-xs text-on-surface-variant">{{ $receiving['supplier'] ?: 'Supplier not recorded' }} · {{ $receiving['latest_receiving_document']?->document_number ?: 'No receiving document' }}</p>
            <dl class="mt-4 grid grid-cols-3 gap-2 rounded-lg bg-surface-low p-3 text-center text-xs">
                <div><dt class="text-on-surface-variant">Ordered</dt><dd class="mt-1 font-bold">{{ number_format($receiving['ordered_quantity'], 2) }}</dd></div>
                <div><dt class="text-on-surface-variant">Received</dt><dd class="mt-1 font-bold">{{ number_format($receiving['received_quantity'], 2) }}</dd></div>
                <div><dt class="text-on-surface-variant">Balance</dt><dd class="mt-1 font-bold {{ $receiving['balance_quantity'] > 0 ? 'text-error' : 'text-secondary' }}">{{ number_format($receiving['balance_quantity'], 2) }}</dd></div>
            </dl>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <a href="{{ route('procurement.documents', [$request, 'stage' => 'receiving']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-3 text-xs font-bold text-white">{{ in_array($receiving['status'], ['partial', 'missing_iar', 'missing_po', 'missing_both']) ? 'Complete workflow' : 'Inspect documents' }}</a>
                @if($receiving['purchase_order'] && $receiving['latest_receiving_document'])
                    <a href="{{ route('procurement.delivery-reconciliation', $request) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-outline-variant px-3 text-xs font-bold text-primary">Reconcile</a>
                @endif
            </div>
        </article>
    @endforeach
    </div>
    </details>
    @endforeach
</div>
<script>
    (() => {
        const groups = Array.from(document.querySelectorAll('[data-recv-group]'));
        const cards = Array.from(document.querySelectorAll('[data-recv-card-group]'));
        const setGroup = (group, open) => { group.toggleAttribute('data-collapsed', !open); };
        groups.forEach((group) => {
            const head = group.querySelector('[data-head]');
            const toggle = () => setGroup(group, group.hasAttribute('data-collapsed'));
            head.addEventListener('click', toggle);
            head.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(); } });
        });
        document.querySelectorAll('[data-recv-all]').forEach((button) => button.addEventListener('click', () => {
            const open = button.dataset.recvAll === '1';
            groups.forEach((group) => setGroup(group, open));
            cards.forEach((card) => { card.open = open; });
        }));
    })();
</script>
