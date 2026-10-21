{{-- A school on the trial sees how long it has and what it cannot do yet (the trial cannot print). Never shown to the master, a sub-master, Free or Annual. Drawn by a script because this partial sits in the page head. --}}
@auth
    @php
        $trialUser = auth()->user();
        $trialSubscription = $trialUser->seesAllSchools() ? null : $trialUser->activeSubscription();
        $trialDays = $trialSubscription?->isTrial() ? max(0, (int) now()->startOfDay()->diffInDays($trialSubscription->subscription_end?->copy()->startOfDay() ?? now()->startOfDay(), false)) : null;
    @endphp
    @if($trialDays !== null)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const bar = document.createElement('div');
                bar.setAttribute('data-trial-banner', '');
                bar.className = 'no-print';
                bar.style.cssText = 'position:fixed;left:50%;bottom:.75rem;transform:translateX(-50%);z-index:90;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem .9rem;max-width:calc(100vw - 1.5rem);padding:.55rem 1rem;border-radius:2rem;background:rgba(16,57,103,.96);color:#fff;font:600 12px/1.3 Inter,system-ui,sans-serif;box-shadow:0 12px 30px -10px rgba(16,57,103,.55)';
                const part = (text, style) => { const span = document.createElement('span'); span.textContent = text; if (style) span.style.cssText = style; return span; };
                const link = document.createElement('a');
                link.href = @json(url('/subscription')); link.textContent = 'Subscribe'; link.style.cssText = 'color:#8fd5bb;text-decoration:underline';
                bar.append(part(@json('Trial · '.($trialDays === 0 ? 'ends today' : $trialDays.' '.\Illuminate\Support\Str::plural('day', $trialDays).' left'))), part(@json('Printing, payments and Drive uploads need the paid subscription (₱'.number_format(\App\Services\SubscriptionPricing::LIST_PRICE, 2).' a year)'), 'opacity:.8;font-weight:500'), link);
                document.body.append(bar);
            });
            window.addEventListener('beforeprint', () => document.querySelector('[data-trial-banner]')?.remove());
        </script>
    @endif
@endauth
