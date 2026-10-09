@php
    $financeLinks = [
        ['budget', 'Budget', 'account_balance', ['budget', 'budget.*']],
        ['accounting', 'Accounting', 'request_quote', ['accounting', 'accounting.*']],
        ['chart-of-accounts', 'Chart of Accounts', 'list_alt', ['chart-of-accounts*']],
        ['allotment-registry', 'Allotment Registry', 'menu_book', ['allotment-registry*']],
        ['cash', 'Cash', 'payments', ['cash', 'cash.*']],
    ];
    $financeOpen = collect($financeLinks)->contains(fn ($link) => request()->routeIs(...$link[3]));
@endphp
<details class="group" @if($financeOpen) open @endif>
    <summary class="civic-nav-link cursor-pointer list-none {{ $financeOpen ? 'civic-nav-link--active' : '' }}"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span></summary>
    <div class="mt-1 space-y-0.5">
        @foreach($financeLinks as [$route, $label, $icon, $patterns])
            <a href="{{ route($route) }}" @if(request()->routeIs(...$patterns)) aria-current="page" @endif class="ml-7 flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] {{ request()->routeIs(...$patterns) ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">{{ $icon }}</span>{{ $label }}</a>
        @endforeach
    </div>
</details>
