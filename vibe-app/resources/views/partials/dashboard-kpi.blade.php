@php
    $money = fn (float $value) => $value >= 1000000 ? '₱'.rtrim(rtrim(number_format($value / 1000000, 2), '0'), '.').'M' : ($value >= 1000 ? '₱'.rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'K' : '₱'.number_format($value, 0));
    $p = $kpi['procurement']; $b = $kpi['budget']; $l = $kpi['liquidation']; $s = $kpi['suppliers'];
    $tone = fn (string $name) => match ($name) {
        'blue' => ['#dcf0fb', '#1b7fc0', '#b6dcf1', '#eaf6fd'], 'green' => ['#d5f3ea', '#0e8a6e', '#a9e2d0', '#e7f8f2'], 'amber' => ['#fff1cc', '#c98a0a', '#f6dc95', '#fff8e3'], 'red' => ['#ffdde0', '#d63a49', '#f9b8be', '#fff0f1'], default => ['#e7eef4', '#14818f', '#cbd7e1', '#eef3f8'],
    };
    $utilTone = $b['utilization'] >= 100 ? 'red' : ($b['utilization'] >= 80 ? 'amber' : 'green');
    $cards = [
        ['label' => 'Budget utilization', 'value' => $b['lines'] ? $b['utilization'].'%' : '—', 'note' => $b['lines'] ? $money($b['obligated']).' of '.$money($b['allocated']).' obligated' : 'No budget lines yet', 'icon' => 'account_balance_wallet', 'tone' => $b['lines'] ? $utilTone : 'blue', 'bar' => $b['lines'] ? min(100, $b['utilization']) : null, 'href' => route('budget')],
        ['label' => 'Budget balance', 'value' => $b['lines'] ? $money($b['balance']) : '—', 'note' => $b['near_limit'] ? $b['near_limit'].' line(s) near or at limit' : 'All lines within limit', 'icon' => 'savings', 'tone' => $b['near_limit'] ? 'amber' : 'green', 'href' => route('budget')],
        ['label' => 'Purchase requests', 'value' => $p['total'], 'note' => $money($p['value']).' total value', 'icon' => 'description', 'tone' => 'blue', 'spark' => collect($p['monthly'])->pluck('count')->all(), 'href' => route('procurement.requests')],
        ['label' => 'Pending approval', 'value' => $p['pending'], 'note' => $p['pending'] ? 'Needs a decision' : 'Nothing waiting', 'icon' => 'pending_actions', 'tone' => $p['pending'] ? 'amber' : 'green', 'href' => route('procurement.requests', ['status' => 'pending_approval'])],
        ['label' => 'In progress', 'value' => $p['in_progress'], 'note' => 'Approved or in canvass', 'icon' => 'sync', 'tone' => 'blue', 'href' => route('procurement.requests', ['status' => 'for_canvass'])],
        ['label' => 'Completion rate', 'value' => $p['total'] ? $p['completion_rate'].'%' : '—', 'note' => $p['completed'].' of '.$p['total'].' completed', 'icon' => 'task_alt', 'tone' => 'green', 'bar' => $p['total'] ? $p['completion_rate'] : null, 'href' => route('procurement.requests', ['status' => 'completed'])],
        ['label' => 'Liquidation to review', 'value' => $l['pending'], 'note' => $l['pending'] ? $money($l['pending_amount']).' awaiting review' : $l['approved'].' approved', 'icon' => 'receipt_long', 'tone' => $l['pending'] ? 'amber' : 'green', 'href' => route('liquidation')],
        ['label' => 'Active suppliers', 'value' => $s['active'], 'note' => $s['expiring'] ? $s['expiring'].' with expiring permits' : 'Permits up to date', 'icon' => 'storefront', 'tone' => $s['expiring'] ? 'red' : 'blue', 'href' => route('suppliers')],
    ];
    // A school sees what it acts on: the completion rate and the supplier count go, and the cash (balance, DVs waiting for payment) is added.
    if (! $isMasterUser) {
        $c = $kpi['cash'];
        $cards = array_values(array_filter($cards, fn (array $card) => ! in_array($card['label'], ['Completion rate', 'Active suppliers'], true)));
        array_splice($cards, 2, 0, [
            ['label' => 'Cash balance', 'value' => $c['balance'] === null ? '—' : $money($c['balance']), 'note' => 'Cash received less paid, FY '.$kpi['year'], 'icon' => 'account_balance', 'tone' => ($c['balance'] ?? 0) < 0 ? 'red' : 'green', 'href' => route('cash')],
            ['label' => 'DVs for payment', 'value' => $c['awaiting'], 'note' => $c['awaiting'] ? $money($c['awaiting_amount']).' to pay' : 'Nothing waiting', 'icon' => 'payments', 'tone' => $c['awaiting'] ? 'amber' : 'green', 'href' => route('cash')],
        ]);
    }
    // Finance sits at the top of the page; the request tiles come after the planning and budget cards.
    $financeLabels = ['Budget utilization', 'Budget balance', 'Cash balance', 'DVs for payment'];
    $financeCards = array_values(array_filter($cards, fn (array $card) => in_array($card['label'], $financeLabels, true)));
    $requestCards = array_values(array_filter($cards, fn (array $card) => ! in_array($card['label'], $financeLabels, true)));
    $today = now('Asia/Manila');
    $daysInYear = $today->isLeapYear() ? 366 : 365;
    $yearProgress = (int) round($today->dayOfYear / $daysInYear * 100);
@endphp
<section aria-label="KPI dashboard" class="mb-6">
    <div class="dash-hero mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-r from-[#0f6f7e] via-[#14818f] to-[#f2b134] px-6 py-5 text-white shadow-md">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-white">{{ $isMasterUser ? 'KPI Dashboard' : 'School Dashboard' }} <span class="font-semibold text-white">· FY {{ $kpi['year'] }}</span></p>
            <h2 class="mt-1 truncate text-2xl font-extrabold tracking-tight text-white">{{ $isMasterUser ? 'All schools' : ($currentSchool?->name ?? 'Your school') }}</h2>
            @unless($isMasterUser)<p class="mt-1 text-sm text-white">Welcome, <strong class="font-extrabold text-white">{{ auth()->user()->name }}</strong>. Your procurement, budget, and liquidation at a glance.</p>
            @php $mySubscription = auth()->user()->activeSubscription(); @endphp
            @if($mySubscription?->isTrial() && $mySubscription->subscription_end)<a href="{{ route('subscription') }}" class="mt-2 inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/15 px-3 py-1 text-xs font-semibold text-white">Trial · {{ max(0, (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($mySubscription->subscription_end)->startOfDay(), false)) }} day(s) left</a>@endif
            @endunless
        </div>
        <div class="flex min-w-[15rem] flex-col gap-2 rounded-xl bg-[#0a3a47]/65 p-3 text-xs">
            <p class="flex w-fit items-center gap-1.5 self-end rounded-full border border-white/30 bg-white/15 px-3 py-1 font-bold text-white" aria-live="polite"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping motion-reduce:animate-none rounded-full bg-emerald-300 opacity-70"></span><span class="relative inline-block h-2 w-2 rounded-full bg-emerald-300" data-live-dot></span></span><span data-live-status>Live</span></p>
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">calendar_today</span>{{ $today->format('l, F j, Y') }}<span class="text-white/50" aria-hidden="true">·</span><span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span><strong class="tabular-nums font-extrabold text-white" data-live-clock>{{ $today->format('g:i:s A') }}</strong></p>
            <div><div class="mb-1 flex justify-between font-semibold text-white"><span>Fiscal year {{ $kpi['year'] }}</span><span>Day {{ $today->dayOfYear }} of {{ $daysInYear }} · {{ $yearProgress }}%</span></div><div class="dash-bar h-2 overflow-hidden rounded-full bg-white/30" role="progressbar" aria-label="Fiscal year {{ $kpi['year'] }} progress" aria-valuenow="{{ $yearProgress }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-white" style="width:{{ $yearProgress }}%"></div></div></div>
        </div>
        @if($isMasterUser)
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach([['Schools', $totalSchools], ['Active', $activeSchools], ['Users', $totalUsers], ['Subscriptions', $activeSubscriptions]] as [$chipLabel, $chipValue])
                    <span class="rounded-full border border-white/30 bg-white/15 px-3 py-1 font-semibold text-white">{{ $chipLabel }} <strong class="ml-1 text-white">{{ $chipValue }}</strong></span>
                @endforeach
            </div>
        @endif
    </div>

    @include('partials.dashboard-cards', ['cards' => $financeCards, 'tone' => $tone])

    @php
        $c = $kpi['cash'];
        $stages = [
            ['Obligation', 'assignment_turned_in', '#1b7fc0', route('budget'), $money($b['obligated']), $l['total'].' ORS · '.($b['lines'] ? $b['utilization'].'% of the budget' : 'no budget yet'), $b['lines'] ? min(100, $b['utilization']) : null, 'Funds committed by ORS'],
            ['Liquidation', 'receipt_long', '#0e8a6e', route('liquidation'), $money($b['liquidated']), $l['approved'].' approved · '.($l['pending'] ? $l['pending'].' to review ('.$money($l['pending_amount']).')' : 'none waiting'), $b['obligated'] > 0 ? min(100, $b['liquidation_rate']) : null, 'Obligations cleared'],
            ['Disbursement', 'payments', '#6a55b8', route('cash'), $money($c['paid_amount']), $c['paid'].' DV'.($c['paid'] === 1 ? '' : 's').' paid · '.($c['awaiting'] ? $c['awaiting'].' awaiting ('.$money($c['awaiting_amount']).')' : 'none awaiting'), ($c['paid'] + $c['awaiting']) > 0 ? (int) round($c['paid'] / ($c['paid'] + $c['awaiting']) * 100) : null, 'Paid out in FY '.$kpi['year']],
        ];
    @endphp
    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3" aria-label="Obligation, liquidation and disbursement">
        @foreach($stages as [$stageLabel, $stageIcon, $stageColor, $stageHref, $stageValue, $stageNote, $stageShare, $stageHint])
            <div class="relative">
                <a href="{{ $stageHref }}" class="group relative flex h-full flex-col justify-between overflow-hidden rounded-2xl border p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c]" style="background:linear-gradient(135deg,{{ ['#e6f3fc', '#e4f6ee', '#eeeafb'][$loop->index] }},#fff 135%);border-color:{{ ['#c3e1f5', '#bfe8d6', '#d6cef3'][$loop->index] }}">
                    <span class="absolute inset-x-0 top-0 h-1" style="background:{{ $stageColor }}" aria-hidden="true"></span>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ $loop->iteration }} · {{ $stageLabel }}</p><p data-countup class="mt-1.5 text-3xl font-extrabold leading-none tracking-tight" style="color:{{ $stageColor }}">{{ $stageValue }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $stageHint }}</p></div>
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:{{ $stageColor }}1f;color:{{ $stageColor }}"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $stageIcon }}</span></span>
                    </div>
                    @if($stageShare !== null)<div class="dash-bar mt-3 h-1.5 overflow-hidden rounded-full bg-surface-container" role="progressbar" aria-label="{{ $stageLabel }} share" aria-valuenow="{{ $stageShare }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full" style="width:{{ $stageShare }}%;background:{{ $stageColor }}"></div></div>@endif
                    <p class="mt-2 text-xs font-medium text-on-surface-variant">{{ $stageNote }}</p>
                </a>
                @unless($loop->last)<span class="absolute -right-3 top-1/2 z-10 hidden h-6 w-6 -translate-y-1/2 place-items-center rounded-full bg-primary text-white shadow md:grid" aria-hidden="true"><span class="material-symbols-outlined text-[16px]">chevron_right</span></span>@endunless
            </div>
        @endforeach
    </div>

    @php $pl = $kpi['planning']; @endphp
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-5">
        <article class="flex flex-col rounded-2xl border border-outline-variant/50 shadow-sm bg-white p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2"><div><h3 class="text-base font-bold text-primary">Planning flow</h3><p class="text-xs text-on-surface-variant">SIP, AIP, SOB, APP and APP-CSE · FY {{ $kpi['year'] }}</p></div><a href="{{ route('planning') }}" class="text-xs font-bold text-action hover:underline">Open Planning</a></div>
            @php $stepsDone = collect($pl['steps'])->filter(fn ($step) => $step['total'] > 0 && ($step['approved'] === null || $step['approved'] === $step['total']))->count(); @endphp
            <div class="mt-3 flex items-center gap-2"><div class="dash-bar flex flex-1 gap-1" role="img" aria-label="{{ $stepsDone }} of {{ count($pl['steps']) }} planning steps done">@foreach($pl['steps'] as $stepKey => $step)@php $stepDone = $step['total'] > 0 && ($step['approved'] === null || $step['approved'] === $step['total']); @endphp<i class="block h-2 flex-1 rounded-full" style="background:{{ $stepDone ? '#2aa86b' : ($step['total'] > 0 ? '#f2b134' : '#d8e3e6') }}"></i>@endforeach</div><span class="text-xs font-bold text-primary">{{ $stepsDone }}/{{ count($pl['steps']) }}</span></div>
            <ol class="relative mt-4 flex flex-1 flex-col justify-between gap-3 before:absolute before:bottom-6 before:left-[1.65rem] before:top-6 before:w-0.5 before:bg-outline-variant/40">
                @foreach($pl['steps'] as $stepKey => $step)
                    @php
                        $started = $step['total'] > 0;
                        $done = $started && ($step['approved'] === null || $step['approved'] === $step['total']);
                        $chip = $done ? ['bg-secondary/10 text-secondary', $step['approved'] === null ? 'Entered' : 'Approved'] : ($started ? ['bg-amber-100 text-amber-800', $step['approved'].' of '.$step['total'].' approved'] : ['bg-rose-100 text-rose-800', 'Not started']);
                    @endphp
                    <li class="relative"><a href="{{ route('planning') }}#{{ $stepKey }}" class="flex w-full items-center gap-3 rounded-xl border border-outline-variant/50 bg-white p-3 transition hover:border-primary hover:bg-surface-low/60 hover:shadow-sm">
                        <span class="relative z-10 grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold ring-4 ring-white {{ $done ? 'bg-secondary text-white' : ($started ? 'bg-amber-400 text-white' : 'bg-rose-200 text-rose-900') }}">@if($done)<span class="material-symbols-outlined text-[18px]" aria-hidden="true">check</span>@else{{ $loop->iteration }}@endif</span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-bold text-primary">{{ $step['label'] }}</span><span class="block text-xs text-on-surface-variant">{{ $step['total'] }} {{ $step['unit'] }}{{ $step['total'] === 1 ? '' : 's' }}</span></span>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $chip[0] }}">{{ $chip[1] }}</span>
                    </a></li>
                @endforeach
            </ol>
        </article>

        <article class="flex flex-col overflow-hidden rounded-2xl border border-outline-variant/50 shadow-sm bg-white lg:col-span-3">
            <div class="flex items-center justify-between gap-2 border-b border-outline-variant/40 px-4 py-3"><div><h3 class="text-base font-bold text-primary">Budget funded from the SOB</h3><p class="text-xs text-on-surface-variant">The approved SOB is the actual budget · by source of fund and quarter</p></div><a href="{{ route('allotment-registry') }}" class="text-xs font-bold text-action hover:underline">Allotment Registry</a></div>
            @if(count($pl['funds']))
                @php
                    $sobAllocated = (float) collect($pl['funds'])->sum('allocated');
                    $sobObligated = (float) collect($pl['funds'])->sum('obligated');
                    $sobBalance = $sobAllocated - $sobObligated;
                    $sobTiles = [
                        ['Budget (SOB)', $sobAllocated, 'Approved allocation', '#14818f', 'account_balance'],
                        ['Obligated', $sobObligated, $sobAllocated > 0 ? round($sobObligated / $sobAllocated * 100).'% of the budget' : 'No budget yet', '#1b7fc0', 'assignment_turned_in'],
                        ['Balance', $sobBalance, $sobAllocated > 0 ? round($sobBalance / $sobAllocated * 100).'% still available' : 'No budget yet', $sobBalance > 0 ? '#2aa86b' : '#c98a0a', 'savings'],
                    ];
                @endphp
                <div class="grid grid-cols-1 gap-3 px-4 py-4 sm:grid-cols-3">
                    @foreach($sobTiles as [$tileLabel, $tileValue, $tileNote, $tileColor, $tileIcon])
                        <div class="relative overflow-hidden rounded-xl border p-3" style="background:{{ ['#e1f4f5', '#e6f3fc', '#e4f6ee'][$loop->index] }};border-color:{{ ['#b7e0e4', '#c3e1f5', '#bfe8d6'][$loop->index] }}" title="{{ $tileLabel }}: {{ number_format($tileValue, 2) }}">
                            <span class="absolute inset-x-0 top-0 h-1" style="background:{{ $tileColor }}" aria-hidden="true"></span>
                            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-on-surface-variant"><span class="material-symbols-outlined text-[16px]" style="color:{{ $tileColor }}" aria-hidden="true">{{ $tileIcon }}</span>{{ $tileLabel }}</p>
                            <p data-countup class="mt-1.5 text-xl font-extrabold tracking-tight" style="color:{{ $tileColor }}">{{ $money($tileValue) }}</p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ $tileNote }}</p>
                            @if($sobAllocated > 0)<div class="dash-bar mt-2 h-1.5 overflow-hidden rounded-full bg-surface-container"><div class="h-full rounded-full" style="width:{{ min(100, round(($loop->index === 0 ? 100 : ($loop->index === 1 ? $sobObligated : max(0, $sobBalance)) / $sobAllocated * 100))) }}%;background:{{ $tileColor }}"></div></div>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            @if(count($pl['funds']))
                <div class="flex-1 overflow-x-auto border-t border-outline-variant/40"><table class="w-full min-w-[520px] text-left text-xs">
                    <thead class="bg-surface-low uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-2">Source of fund</th>@foreach([1, 2, 3, 4] as $q)<th class="px-2 py-2 text-center">Q{{ $q }}</th>@endforeach<th class="px-3 py-2 text-center">Total</th><th class="px-3 py-2 text-center">Obligated</th></tr></thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @foreach($pl['funds'] as $fundRow)
                            <tr class="hover:bg-surface-low/50"><td class="px-4 py-2.5 font-bold text-primary">{{ $fundRow['fund'] }}<span class="block font-normal text-on-surface-variant">{{ $fundRow['items'] }} item{{ $fundRow['items'] === 1 ? '' : 's' }}</span></td>@foreach($fundRow['quarters'] as $amount)<td class="px-2 py-2.5 text-center tabular-nums {{ $amount > 0 ? 'font-semibold' : 'text-on-surface-variant/40' }}">{{ $amount > 0 ? number_format($amount, 2) : '–' }}</td>@endforeach<td class="px-3 py-2.5 text-center font-bold tabular-nums">{{ number_format($fundRow['allocated'], 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format($fundRow['obligated'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-outline-variant/60 bg-surface-low font-bold"><tr><td class="px-4 py-2.5">TOTAL</td>@foreach([0, 1, 2, 3] as $quarterIndex)<td class="px-2 py-2.5 text-center tabular-nums">{{ number_format(collect($pl['funds'])->sum(fn ($fundRow) => $fundRow['quarters'][$quarterIndex]), 2) }}</td>@endforeach<td class="px-3 py-2.5 text-center tabular-nums">{{ number_format(collect($pl['funds'])->sum('allocated'), 2) }}</td><td class="px-3 py-2.5 text-center tabular-nums">{{ number_format(collect($pl['funds'])->sum('obligated'), 2) }}</td></tr></tfoot>
                </table></div>
            @else
                <p class="px-4 py-8 text-center text-xs text-on-surface-variant">No SOB has been approved for FY {{ $kpi['year'] }} yet. Approving a quarter of the SOB creates the budget.</p>
            @endif
        </article>
    </div>

    <div class="mb-3 mt-8 flex items-center gap-3" role="separator" aria-label="Procurement"><span class="material-symbols-outlined text-[20px] text-[#14818f]" aria-hidden="true">shopping_cart</span><h3 class="text-sm font-extrabold uppercase tracking-[.14em] text-[#0f4c5c]">Procurement</h3><span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#14818f] to-[#f2b134]/40" aria-hidden="true"></span></div>
    <div>@include('partials.dashboard-cards', ['cards' => $requestCards, 'tone' => $tone])</div>

    @if($isMasterUser && count($kpi['schools']) > 0)
        <article class="mt-3 overflow-hidden rounded-2xl border border-outline-variant/50 shadow-sm bg-white">
            <div class="border-b border-outline-variant/40 px-4 py-3"><h3 class="text-base font-bold text-primary">School performance</h3><p class="text-xs text-on-surface-variant">Requests and budget use per school</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-xs">
                    <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-2">School</th><th class="px-4 py-2 text-right">Requests</th><th class="px-4 py-2 text-right">Pending</th><th class="px-4 py-2 text-right">Completed</th><th class="px-4 py-2">Budget used</th></tr></thead>
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
    <details class="mt-3 rounded-2xl border border-outline-variant/50 bg-white p-4 shadow-sm">
        <summary class="flex cursor-pointer items-center gap-2 text-sm font-bold text-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">info</span>What these numbers mean</summary>
        <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-2 text-xs text-on-surface-variant md:grid-cols-2">
            <div><dt class="font-bold text-primary">Budget utilization</dt><dd>Share of the approved budget already obligated (obligated ÷ allocated). Turns amber at 80% and red at 100%.</dd></div>
            <div><dt class="font-bold text-primary">Budget balance</dt><dd>Budget still available to obligate (allocated less obligated). The note counts budget lines at or near their limit.</dd></div>
            <div><dt class="font-bold text-primary">Purchase requests</dt><dd>All purchase requests this fiscal year and their total value. The small bars show requests per month for the last six months.</dd></div>
            <div><dt class="font-bold text-primary">Pending approval</dt><dd>Requests submitted and waiting for an approver's decision.</dd></div>
            <div><dt class="font-bold text-primary">In progress</dt><dd>Requests already approved or in canvass, not yet completed.</dd></div>
            @if($isMasterUser)
                <div><dt class="font-bold text-primary">Completion rate</dt><dd>Completed requests as a share of all requests.</dd></div>
                <div><dt class="font-bold text-primary">Active suppliers</dt><dd>Suppliers marked active. Red means some have permits that are expiring or expired.</dd></div>
            @else
                <div><dt class="font-bold text-primary">Cash balance</dt><dd>Cash received less cash paid out this fiscal year.</dd></div>
                <div><dt class="font-bold text-primary">DVs for payment</dt><dd>Disbursement vouchers (DVs) waiting to be paid, with the amount to pay.</dd></div>
            @endif
            <div><dt class="font-bold text-primary">Liquidation to review</dt><dd>Liquidations submitted and waiting for review, with the amount involved.</dd></div>
            <div><dt class="font-bold text-primary">1 · Obligation</dt><dd>Funds committed through Obligation Requests (ORS). The bar is the share of the budget committed.</dd></div>
            <div><dt class="font-bold text-primary">2 · Liquidation</dt><dd>Obligations cleared with approved liquidation. The bar is the share of obligations liquidated.</dd></div>
            <div><dt class="font-bold text-primary">3 · Disbursement</dt><dd>Amount actually paid out through paid DVs this fiscal year. The bar is paid DVs out of paid plus awaiting.</dd></div>
            <div><dt class="font-bold text-primary">Planning flow</dt><dd>Progress of the yearly plans (SIP, AIP, SOB, APP, APP-CSE). Done means entered, or fully approved where approval applies.</dd></div>
            <div><dt class="font-bold text-primary">Budget funded from the SOB</dt><dd>The approved SOB is the actual budget, shown per source of fund and quarter, with how much is obligated.</dd></div>
            @if($isMasterUser)<div><dt class="font-bold text-primary">School performance</dt><dd>Requests, pending and completed counts and budget use for each school.</dd></div>@endif
        </dl>
        <p class="mt-3 text-xs text-on-surface-variant">Figures are for fiscal year {{ $kpi['year'] }}. Amounts show as ₱K (thousands) or ₱M (millions). The page refreshes by itself when the data changes.</p>
    </details>
</section>
