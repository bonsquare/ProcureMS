@php
    $money = fn (float $value) => $value >= 1000000 ? '₱'.rtrim(rtrim(number_format($value / 1000000, 2), '0'), '.').'M' : ($value >= 1000 ? '₱'.rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'K' : '₱'.number_format($value, 0));
    $p = $kpi['procurement']; $b = $kpi['budget']; $l = $kpi['liquidation']; $s = $kpi['suppliers'];
    $tone = fn (string $name) => match ($name) {
        'blue' => ['#e2edf7', '#286da8', '#c5dbef', '#eaf3fb'], 'green' => ['#ddf0e8', '#2a7f64', '#bfe3d3', '#e8f6f0'], 'amber' => ['#fff0dc', '#b46f1f', '#f2d9b0', '#fff6e8'], 'red' => ['#f8e2e4', '#b64a50', '#efc3c7', '#fdeeef'], default => ['#e7eef4', '#103967', '#cbd7e1', '#eef3f8'],
    };
    $utilTone = $b['utilization'] >= 100 ? 'red' : ($b['utilization'] >= 80 ? 'amber' : 'green');
    $cards = [
        ['label' => 'Budget utilization', 'value' => $b['lines'] ? $b['utilization'].'%' : '—', 'note' => $b['lines'] ? $money($b['obligated']).' of '.$money($b['allocated']).' obligated' : 'No budget lines yet', 'icon' => 'account_balance_wallet', 'tone' => $b['lines'] ? $utilTone : 'blue', 'bar' => $b['lines'] ? min(100, $b['utilization']) : null, 'href' => route('budget')],
        ['label' => 'Budget balance', 'value' => $b['lines'] ? $money($b['balance']) : '—', 'note' => $b['near_limit'] ? $b['near_limit'].' line(s) near or at limit' : 'All lines within limit', 'icon' => 'savings', 'tone' => $b['near_limit'] ? 'amber' : 'green', 'href' => route('budget')],
        ['label' => 'Purchase requests', 'value' => $p['total'], 'note' => $money($p['value']).' total value', 'icon' => 'description', 'tone' => 'blue', 'href' => route('procurement.requests')],
        ['label' => 'Pending approval', 'value' => $p['pending'], 'note' => $p['pending'] ? 'Needs a decision' : 'Nothing waiting', 'icon' => 'pending_actions', 'tone' => $p['pending'] ? 'amber' : 'green', 'href' => route('procurement.requests', ['status' => 'pending_approval'])],
        ['label' => 'In progress', 'value' => $p['in_progress'], 'note' => 'Approved or in canvass', 'icon' => 'sync', 'tone' => 'blue', 'href' => route('procurement.requests', ['status' => 'for_canvass'])],
        ['label' => 'Completion rate', 'value' => $p['total'] ? $p['completion_rate'].'%' : '—', 'note' => $p['completed'].' of '.$p['total'].' completed', 'icon' => 'task_alt', 'tone' => 'green', 'bar' => $p['total'] ? $p['completion_rate'] : null, 'href' => route('procurement.requests', ['status' => 'completed'])],
        ['label' => 'Liquidation to review', 'value' => $l['pending'], 'note' => $l['pending'] ? $money($l['pending_amount']).' awaiting review' : $l['approved'].' approved', 'icon' => 'receipt_long', 'tone' => $l['pending'] ? 'amber' : 'green', 'href' => route('liquidation')],
        ['label' => 'Active suppliers', 'value' => $s['active'], 'note' => $s['expiring'] ? $s['expiring'].' with expiring permits' : 'Permits up to date', 'icon' => 'storefront', 'tone' => $s['expiring'] ? 'red' : 'blue', 'href' => route('suppliers')],
    ];
    $statusMeta = [
        'draft' => ['Draft', '#9aa8b6'], 'submitted' => ['Submitted', '#cc8535'], 'pending_approval' => ['Pending approval', '#e0a04a'], 'approved' => ['Approved', '#286da8'],
        'for_canvass' => ['For canvass', '#5b9bd5'], 'completed' => ['Completed', '#369878'], 'returned' => ['Returned', '#b8793f'], 'rejected' => ['Rejected', '#b64a50'],
    ];
    $statusTotal = max(1, array_sum($p['by_status']));
    $maxMonth = max(1, collect($p['monthly'])->max('count'));
    $flow = [['Allocated', $b['allocated'], '#103967'], ['Obligated', $b['obligated'], '#286da8'], ['Liquidated', $b['liquidated'], '#369878']];
    $flowMax = max(1, $b['allocated'], $b['obligated']);
@endphp
<section aria-label="KPI dashboard" class="mb-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-variant/50 border-l-4 border-l-primary bg-white px-5 py-3.5">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-action">{{ $isMasterUser ? 'KPI Dashboard' : 'School Dashboard' }} <span class="font-semibold text-on-surface-variant">· FY {{ $kpi['year'] }}</span></p>
            <h2 class="mt-1 truncate text-xl font-bold text-primary">{{ $isMasterUser ? 'All schools' : ($currentSchool?->name ?? 'Your school') }}</h2>
            @unless($isMasterUser)<p class="mt-0.5 text-sm text-on-surface-variant">Welcome, {{ auth()->user()->name }}. Your procurement, budget, and liquidation at a glance.</p>@endunless
        </div>
        @if($isMasterUser)
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach([['Schools', $totalSchools], ['Active', $activeSchools], ['Users', $totalUsers], ['Subscriptions', $activeSubscriptions]] as [$chipLabel, $chipValue])
                    <span class="rounded-full border border-outline-variant/60 bg-surface-low px-3 py-1 font-semibold">{{ $chipLabel }} <strong class="ml-1 text-primary">{{ $chipValue }}</strong></span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
            @php [$soft, $strong, $edge, $tint] = $tone($card['tone']); $value = in_array($card['tone'], ['amber', 'red'], true) ? $strong : '#103967'; @endphp
            <a href="{{ $card['href'] }}" class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-outline-variant/50 bg-white p-4 pl-5 transition hover:border-action/50 hover:shadow-md">
                <span class="absolute inset-y-0 left-0 w-1" style="background:{{ $strong }}" aria-hidden="true"></span>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $card['label'] }}</p>
                        <p class="mt-1.5 text-2xl font-bold leading-none" style="color:{{ $value }}">{{ $card['value'] }}</p>
                    </div>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" style="background:{{ $soft }};color:{{ $strong }}"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">{{ $card['icon'] }}</span></span>
                </div>
                @if(isset($card['bar']) && $card['bar'] !== null)
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-container" role="progressbar" aria-valuenow="{{ $card['bar'] }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full" style="width:{{ $card['bar'] }}%;background:{{ $strong }}"></div></div>
                @endif
                <p class="mt-2 truncate text-xs text-on-surface-variant">{{ $card['note'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
        <article class="rounded-xl border border-outline-variant/50 bg-white p-4">
            <h3 class="text-sm font-bold">Request pipeline</h3>
            <p class="text-xs text-on-surface-variant">Purchase requests by status</p>
            @if(array_sum($p['by_status']))
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-surface-container">
                    @foreach($statusMeta as $key => [$statusLabel, $statusColor])
                        @if(($p['by_status'][$key] ?? 0) > 0)<div title="{{ $statusLabel }}: {{ $p['by_status'][$key] }}" style="width:{{ $p['by_status'][$key] / $statusTotal * 100 }}%;background:{{ $statusColor }}"></div>@endif
                    @endforeach
                </div>
                <ul class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                    @foreach($statusMeta as $key => [$statusLabel, $statusColor])
                        @if(($p['by_status'][$key] ?? 0) > 0)<li class="flex items-center justify-between gap-2"><span class="flex items-center gap-1.5"><i class="inline-block h-2 w-2 rounded-full" style="background:{{ $statusColor }}"></i>{{ $statusLabel }}</span><strong>{{ $p['by_status'][$key] }}</strong></li>@endif
                    @endforeach
                </ul>
            @else
                <p class="mt-6 text-center text-xs text-on-surface-variant">No purchase requests this fiscal year.</p>
            @endif
        </article>

        <article class="rounded-xl border border-outline-variant/50 bg-white p-4">
            <h3 class="text-sm font-bold">Requests per month</h3>
            <p class="text-xs text-on-surface-variant">Last six months</p>
            <div class="mt-4 flex h-28 items-end justify-between gap-2" role="img" aria-label="Purchase requests per month">
                @foreach($p['monthly'] as $month)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $month['label'] }}: {{ $month['count'] }} request(s), {{ $money($month['value']) }}">
                        <span class="text-[10px] font-bold text-on-surface-variant">{{ $month['count'] ?: '' }}</span>
                        <div class="w-full max-w-9 rounded-t-md" style="height:{{ max($month['count'] / $maxMonth * 100, $month['count'] ? 6 : 2) }}%;background:{{ $month['count'] ? '#286da8' : '#dbe4ec' }}"></div>
                        <span class="text-[10px] text-on-surface-variant">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-xl border border-outline-variant/50 bg-white p-4">
            <h3 class="text-sm font-bold">Budget flow</h3>
            <p class="text-xs text-on-surface-variant">Allocated, obligated and liquidated</p>
            @if($b['lines'])
                <div class="mt-4 space-y-3">
                    @foreach($flow as [$flowLabel, $flowValue, $flowColor])
                        <div>
                            <div class="mb-1 flex justify-between text-xs"><span class="font-semibold">{{ $flowLabel }}</span><span>{{ $money($flowValue) }}</span></div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-surface-container"><div class="h-full rounded-full" style="width:{{ min(100, $flowValue / $flowMax * 100) }}%;background:{{ $flowColor }}"></div></div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-on-surface-variant">{{ $b['liquidation_rate'] }}% of obligations liquidated</p>
            @else
                <p class="mt-6 text-center text-xs text-on-surface-variant">No budget allocations for FY {{ $kpi['year'] }}.</p>
            @endif
        </article>
    </div>


    @if(! $isMasterUser)
        @php
            $plural = fn (int $count, string $word) => $count.' '.\Illuminate\Support\Str::plural($word, $count);
            $attention = array_values(array_filter([
                $p['pending'] ? ['icon' => 'pending_actions', 'tone' => 'amber', 'text' => $plural($p['pending'], 'purchase request').' waiting for approval', 'href' => route('procurement.requests', ['status' => 'pending_approval']), 'cta' => 'Review'] : null,
                $p['attention'] ? ['icon' => 'undo', 'tone' => 'red', 'text' => $plural($p['attention'], 'returned or rejected request').' to revise', 'href' => route('procurement.requests', ['status' => 'returned']), 'cta' => 'Open'] : null,
                $l['pending'] ? ['icon' => 'receipt_long', 'tone' => 'amber', 'text' => $plural($l['pending'], 'liquidation report').' to review ('.$money($l['pending_amount']).')', 'href' => route('liquidation'), 'cta' => 'Review'] : null,
                $b['near_limit'] ? ['icon' => 'warning', 'tone' => 'amber', 'text' => $plural($b['near_limit'], 'budget line').' near or at the limit', 'href' => route('budget'), 'cta' => 'View budget'] : null,
                $s['expiring'] ? ['icon' => 'event_busy', 'tone' => 'red', 'text' => $plural($s['expiring'], 'supplier').' with an expired or expiring permit', 'href' => route('suppliers'), 'cta' => 'Check'] : null,
            ]));
            $quarterMax = max(1, collect($b['quarters'])->max('allocated'), collect($b['quarters'])->max('obligated'));
        @endphp
        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
            <article class="rounded-xl border border-outline-variant/50 bg-white p-4">
                <div class="flex items-center justify-between"><h3 class="text-sm font-bold">Needs your attention</h3><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ count($attention) ? 'bg-attention/15 text-attention' : 'bg-secondary/10 text-secondary' }}">{{ count($attention) ?: 'All clear' }}</span></div>
                @if(count($attention))
                    <ul class="mt-3 space-y-2">
                        @foreach($attention as $item)
                            @php [$soft, $strong] = $tone($item['tone']); @endphp
                            <li><a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-lg border border-outline-variant/40 p-2.5 text-xs transition hover:border-action/60 hover:bg-surface-low/60"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" style="background:{{ $soft }};color:{{ $strong }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">{{ $item['icon'] }}</span></span><span class="min-w-0 flex-1 font-semibold">{{ $item['text'] }}</span><span class="shrink-0 font-bold text-action">{{ $item['cta'] }}</span></a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-6 text-center text-xs text-on-surface-variant">Nothing needs your attention right now.</p>
                @endif
            </article>

            <article class="rounded-xl border border-outline-variant/50 bg-white p-4">
                <h3 class="text-sm font-bold">Budget by quarter</h3>
                <p class="text-xs text-on-surface-variant">Allocated vs obligated</p>
                @if($b['lines'])
                    <div class="mt-4 flex h-28 items-end justify-between gap-3">
                        @foreach($b['quarters'] as $quarter => $row)
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="Q{{ $quarter }}: {{ $money($row['obligated']) }} of {{ $money($row['allocated']) }}">
                                <div class="flex h-full w-full items-end justify-center gap-1">
                                    <div class="w-3 rounded-t" style="height:{{ max($row['allocated'] / $quarterMax * 100, 2) }}%;background:#cbd7e1"></div>
                                    <div class="w-3 rounded-t" style="height:{{ max($row['obligated'] / $quarterMax * 100, 2) }}%;background:#286da8"></div>
                                </div>
                                <span class="text-[10px] font-semibold text-on-surface-variant">Q{{ $quarter }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 flex items-center gap-3 text-[10px] text-on-surface-variant"><span class="flex items-center gap-1"><i class="inline-block h-2 w-2 rounded-sm" style="background:#cbd7e1"></i>Allocated</span><span class="flex items-center gap-1"><i class="inline-block h-2 w-2 rounded-sm" style="background:#286da8"></i>Obligated</span></p>
                @else
                    <p class="mt-6 text-center text-xs text-on-surface-variant">No budget allocations for FY {{ $kpi['year'] }}.</p>
                @endif
            </article>

            <article class="rounded-xl border border-outline-variant/50 bg-white">
                <div class="flex items-center justify-between px-4 pt-4"><h3 class="text-sm font-bold">Recent requests</h3><a href="{{ route('procurement.requests') }}" class="text-xs font-bold text-action">View all</a></div>
                @if(count($kpi['recent']))
                    <ul class="mt-2 divide-y divide-outline-variant/40">
                        @foreach($kpi['recent'] as $recent)
                            <li><a href="{{ route('procurement.show', $recent['id']) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs hover:bg-surface-low/60"><span class="min-w-0"><strong class="block truncate">{{ $recent['number'] }}</strong><span class="block truncate text-on-surface-variant">{{ $recent['title'] }}</span></span><span class="shrink-0 text-right"><span class="block font-semibold">{{ $money($recent['amount']) }}</span><span class="rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:{{ $statusMeta[$recent['status']][1] ?? '#9aa8b6' }}22;color:{{ $statusMeta[$recent['status']][1] ?? '#536273' }}">{{ $statusMeta[$recent['status']][0] ?? str($recent['status'])->headline() }}</span></span></a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="px-4 py-8 text-center text-xs text-on-surface-variant">No purchase requests yet.</p>
                @endif
            </article>
        </div>
    @endif

    @if($isMasterUser && count($kpi['schools']) > 0)
        <article class="mt-3 overflow-hidden rounded-xl border border-outline-variant/50 bg-white">
            <div class="border-b border-outline-variant/40 px-4 py-3"><h3 class="text-sm font-bold">School performance</h3><p class="text-xs text-on-surface-variant">Requests and budget use per school</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-xs">
                    <thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-4 py-2">School</th><th class="px-4 py-2 text-right">Requests</th><th class="px-4 py-2 text-right">Pending</th><th class="px-4 py-2 text-right">Completed</th><th class="px-4 py-2">Budget used</th></tr></thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @foreach($kpi['schools'] as $row)
                            <tr class="hover:bg-surface-low/50">
                                <td class="px-4 py-2 font-semibold">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['requests'] }}</td>
                                <td class="px-4 py-2 text-right">@if($row['pending'])<span class="rounded-full bg-attention/15 px-2 py-0.5 font-bold text-attention">{{ $row['pending'] }}</span>@else<span class="text-on-surface-variant">0</span>@endif</td>
                                <td class="px-4 py-2 text-right">{{ $row['completed'] }}</td>
                                <td class="px-4 py-2">
                                    @if($row['utilization'] === null)<span class="text-on-surface-variant">No allocation</span>
                                    @else<div class="flex items-center gap-2"><div class="h-1.5 w-28 overflow-hidden rounded-full bg-surface-container"><div class="h-full rounded-full" style="width:{{ min(100, $row['utilization']) }}%;background:{{ $row['utilization'] >= 100 ? '#b64a50' : ($row['utilization'] >= 80 ? '#cc8535' : '#369878') }}"></div></div><span class="font-semibold">{{ $row['utilization'] }}%</span><span class="text-on-surface-variant">{{ $money($row['obligated']) }} / {{ $money($row['allocated']) }}</span></div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endif
</section>
