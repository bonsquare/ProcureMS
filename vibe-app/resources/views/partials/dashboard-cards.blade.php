@php
    // Pastel colours, one per tile: [background, border, accent, icon background]. A tile keeps its colour wherever it is shown.
    $pastels = [
        'sky' => ['#e6f3fc', '#c3e1f5', '#1b6fa8', '#cde6f7'], 'mint' => ['#e4f6ee', '#bfe8d6', '#156a52', '#c9ecdd'], 'lavender' => ['#eeeafb', '#d6cef3', '#6a55b8', '#e0daf6'],
        'peach' => ['#fdeee3', '#f8d5bc', '#9c4a18', '#fadfca'], 'butter' => ['#fdf5d9', '#f4e4a0', '#7d5a08', '#f9eebb'], 'rose' => ['#fdebee', '#f6c8d0', '#b8455a', '#f9d6dc'], 'teal' => ['#e1f4f5', '#b7e0e4', '#0f7482', '#c6e9ec'],
    ];
    $pastelFor = ['Budget utilization' => 'teal', 'Budget balance' => 'mint', 'Cash balance' => 'sky', 'DVs for payment' => 'butter', 'Purchase requests' => 'lavender', 'Pending approval' => 'peach', 'In progress' => 'sky', 'Liquidation to review' => 'rose', 'Completion rate' => 'mint', 'Active suppliers' => 'butter'];
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 {{ count($cards) === 2 ? '' : (count($cards) === 6 ? 'xl:grid-cols-3' : 'xl:grid-cols-4') }}">
        @foreach($cards as $card)
            @php [$cardBg, $cardEdge, $strong, $soft] = $pastels[$pastelFor[$card['label']] ?? 'teal']; $value = $card['tone'] === 'red' ? '#c2303f' : $strong; @endphp
            <a href="{{ $card['href'] }}" class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border shadow-sm p-4 pl-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c]" style="background:linear-gradient(135deg,{{ $cardBg }},#fff 135%);border-color:{{ $cardEdge }}">
                <span class="absolute inset-y-0 left-0 w-1.5" style="background:{{ $strong }}" aria-hidden="true"></span>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide" style="color:{{ $strong }}">{{ $card['label'] }}</p>
                        <p data-countup class="mt-1.5 text-3xl font-extrabold leading-none tracking-tight" style="color:{{ $value }}">{{ $card['value'] }}</p>
                        @if(! empty($card['spark']) && array_sum($card['spark']) > 0)@php $sparkMax = max(1, max($card['spark'])); @endphp<div class="mt-2 flex h-5 items-end gap-0.5" role="img" aria-label="Monthly requests, last six months: {{ implode(', ', $card['spark']) }}">@foreach($card['spark'] as $sparkCount)<i class="dash-spark block w-2 rounded-sm" style="height:{{ max($sparkCount / $sparkMax * 100, 8) }}%;background:{{ $strong }};opacity:{{ $loop->last ? 1 : .45 }};animation-delay:{{ $loop->index * 60 }}ms"></i>@endforeach</div>@endif
                    </div>
                    @if(isset($card['bar']) && $card['bar'] !== null)
                        <span class="relative grid h-12 w-12 shrink-0 place-items-center" style="color:{{ $strong }}"><svg viewBox="0 0 44 44" class="absolute inset-0 h-full w-full -rotate-90" aria-hidden="true"><circle cx="22" cy="22" r="18" fill="none" stroke="{{ $soft }}" stroke-width="5"/><circle class="dash-ring" cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="{{ round(113.1 * min(100, $card['bar']) / 100, 1) }} 113.1"/></svg><span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $card['icon'] }}</span></span>
                    @else
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:{{ $soft }};color:{{ $strong }}"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">{{ $card['icon'] }}</span></span>
                    @endif
                </div>
                @if(isset($card['bar']) && $card['bar'] !== null)
                    <div class="dash-bar mt-3 h-1.5 overflow-hidden rounded-full" style="background:{{ $soft }}" role="progressbar" aria-label="{{ $card['label'] }}" aria-valuenow="{{ $card['bar'] }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full" style="width:{{ $card['bar'] }}%;background:{{ $strong }}"></div></div>
                @endif
                <p class="mt-2 truncate text-xs text-on-surface-variant">{{ $card['note'] }}</p>
            </a>
        @endforeach
    </div>
