@php
    $isMasterUser = auth()->user()?->role === 'master_user';
    $navigation = [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'route' => 'home'],
        ['icon' => 'shopping_cart', 'label' => 'Procurement', 'route' => 'procurement'],
        ['icon' => 'receipt_long', 'label' => 'Liquidation', 'route' => 'liquidation'],
        ['icon' => 'folder', 'label' => 'Google Drive', 'route' => 'google-drive'],
        ['icon' => 'bar_chart', 'label' => 'Reports', 'route' => 'reports'],
    ];
    if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) {
        array_splice($navigation, 3, 0, [['icon' => 'account_tree', 'label' => 'Planning', 'route' => 'planning']]);
    }
    if ($isMasterUser) {
        $navigation[] = ['icon' => 'group', 'label' => 'User Management', 'route' => 'user-management'];
        $navigation[] = ['icon' => 'card_membership', 'label' => 'Subscriptions', 'route' => 'subscriptions'];
    }
    if ($isMasterUser) {
        $navigation[] = ['icon' => 'domain', 'label' => 'School Management', 'route' => 'school-management'];
    }
    $navigation[] = ['icon' => 'settings', 'label' => 'School Settings', 'route' => 'school-settings'];
@endphp
<aside id="civic-navigation" class="civic-nav" aria-label="Main navigation">
    <div class="mb-8 flex items-center gap-3 px-2">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary text-white"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">account_balance</span></div>
        <div><p class="text-lg font-bold tracking-tight">ProcureMS</p><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-white/55">Civic Operations</p></div>
    </div>
    <nav class="flex-1 space-y-1">
        @foreach($navigation as $item)
            @php $active = ($item['route'] === ($activeNavRoute ?? 'procurement')); @endphp
            <a href="{{ route($item['route']) }}" @if($active) aria-current="page" @endif class="civic-nav-link {{ $active ? 'civic-nav-link--active' : '' }}">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span>
            </a>
            @if($item['route'] === 'home')
                @include('partials.finance-nav')
            @endif
        @endforeach
    </nav>
    <div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55">Multi-School Procurement System<br>Secure operational workspace</div>
</aside>
