@php
    $h = $server['hosting']; $d = $server['domain'];
    $days = fn ($date) => $date ? (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false) : null;
    $dayTone = fn (?int $n) => $n === null ? 'text-on-surface-variant' : ($n <= 14 ? 'text-error' : ($n <= 45 ? 'text-[#b46f1f]' : 'text-secondary'));
    $sslDays = $days($d['ssl_expires']); $domainDays = $days($d['expires_at']);
    $statusOk = in_array($h['last_status'] ?? '', ['SUCCESS', 'DEPLOYING', 'BUILDING', 'INITIALIZING'], true);
    $cards = [
        ['Site status', $d['up'] ? 'Online' : 'Down', $d['response_ms'] !== null ? $d['response_ms'].' ms response' : 'No response', 'public', $d['up'] ? 'text-secondary' : 'text-error'],
        ['SSL certificate', $sslDays === null ? '—' : $sslDays.' days', $d['ssl_expires'] ? 'Expires '.$d['ssl_expires']->format('M d, Y') : 'Could not read the certificate', 'lock', $dayTone($sslDays)],
        ['Domain renewal', $domainDays === null ? '—' : $domainDays.' days', $d['expires_at'] ? 'Expires '.$d['expires_at']->format('M d, Y').($d['renewal_price'] ? ' · renews at '.$d['renewal_price'] : '') : 'Set DOMAIN_EXPIRES_AT to track this', 'event', $dayTone($domainDays)],
        ['Estimated hosting bill', $h['connected'] ? '$'.number_format($h['estimated_cost'], 2) : '—', $h['connected'] ? 'This billing period, from usage so far' : 'Railway not connected', 'payments', 'text-primary'],
        ['Last deploy', $h['connected'] ? $h['last_status'] : '—', $h['connected'] && $h['last_deploy'] ? $h['last_deploy']->timezone('Asia/Manila')->format('M d, g:i A') : 'Railway not connected', 'rocket_launch', $h['connected'] ? ($statusOk ? 'text-secondary' : 'text-error') : 'text-on-surface-variant'],
        ['Services', $h['connected'] ? $h['services'] : '—', $h['connected'] ? implode(', ', $h['environments']) : 'Railway not connected', 'dns', 'text-primary'],
    ];
    $usageRows = [['CPU_USAGE', 'CPU', 'vCPU-min', 0], ['MEMORY_USAGE_GB', 'Memory', 'GB-min', 0], ['NETWORK_TX_GB', 'Network out', 'GB', 2], ['DISK_USAGE_GB', 'Disk', 'GB-min', 0]];
@endphp
<section aria-label="Server dashboard" class="mb-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-variant/50 border-l-4 border-l-primary bg-white px-5 py-3.5">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-action">Server Dashboard</p>
            <h2 class="mt-1 truncate text-xl font-bold text-primary">{{ $d['name'] }}</h2>
            <p class="mt-0.5 text-sm text-on-surface-variant">Hosting, domain and billing at a glance. Checked {{ $server['checked_at']->timezone('Asia/Manila')->format('M d, g:i A') }}; refreshes every 5 minutes.</p>
        </div>
        <a href="{{ route('home', ['view' => 'server']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-primary/30 px-4 text-xs font-bold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">refresh</span>Reload</a>
    </div>

    @unless($h['connected'])
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-[#f2d9b0] bg-[#fff6e8] px-4 py-3 text-sm text-[#8a5413]" role="status"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">info</span><span>{{ $h['note'] }}</span></div>
    @endunless

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($cards as [$label, $value, $note, $icon, $tone])
            <div class="rounded-xl border border-outline-variant/50 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $label }}</p>
                        <p class="mt-1.5 text-2xl font-bold leading-none {{ $tone }}">{{ $value }}</p>
                    </div>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-surface-low text-primary"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">{{ $icon }}</span></span>
                </div>
                <p class="mt-2 text-xs text-on-surface-variant">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    @if($h['connected'])
    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
        <div class="rounded-xl border border-outline-variant/50 bg-white p-4">
            <h3 class="text-sm font-bold text-primary">Usage this period</h3>
            <table class="mt-2 w-full text-sm"><tbody>
                @foreach($usageRows as [$key, $name, $unit, $dec])
                    <tr class="border-t border-outline-variant/40 first:border-0"><td class="py-2 text-on-surface-variant">{{ $name }}</td><td class="py-2 text-right font-semibold">{{ number_format($h['usage'][$key] ?? 0, $dec) }} <span class="text-xs font-normal text-on-surface-variant">{{ $unit }}</span></td></tr>
                @endforeach
            </tbody></table>
            <p class="mt-2 text-xs text-on-surface-variant">The bill is an estimate from Railway's published rates, not an invoice.</p>
        </div>
        <div class="rounded-xl border border-outline-variant/50 bg-white p-4">
            <h3 class="text-sm font-bold text-primary">Recent deploys</h3>
            <ul class="mt-2 divide-y divide-outline-variant/40 text-sm">
                @forelse($h['deployments'] as $deploy)
                    <li class="flex items-center justify-between py-2"><span class="font-semibold {{ $deploy['status'] === 'SUCCESS' ? 'text-secondary' : (in_array($deploy['status'], ['FAILED', 'CRASHED'], true) ? 'text-error' : 'text-on-surface-variant') }}">{{ $deploy['status'] }}</span><span class="text-xs text-on-surface-variant">{{ $deploy['at']->timezone('Asia/Manila')->format('M d, Y g:i A') }}</span></li>
                @empty
                    <li class="py-2 text-on-surface-variant">No deploys yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @endif
</section>
