<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield("title", "Budget Allocation") · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb','surface-low':'#eef3f7','surface-container':'#e4ebf1','surface-high':'#d8e2ea',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#536273',secondary:'#369878','outline-variant':'#cbd7e1',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>html{font-size:14px}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isMasterUser = auth()->user()?->seesAllSchools();
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']];
    if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); }
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; if (auth()->user()->hasAccess('subscriptions')) $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; if (auth()->user()?->hasAccess('schools')) $navigation[]=['icon'=>'domain','label'=>'School Management','route'=>'school-management']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    // The page you are on is the highlighted item (never a fixed one): the key is the route name of its sidebar link.
    $routeName = request()->route()?->getName() ?? '';
    $activeNav = match (true) {
        $routeName === 'planning', str_starts_with($routeName, 'planning.'), $routeName === 'aip', str_starts_with($routeName, 'aip.'), str_starts_with($routeName, 'transactions.') => 'planning',
        str_starts_with($routeName, 'budget') => 'budget',
        $routeName === 'allotment-registry' => 'allotment-registry',
        str_starts_with($routeName, 'chart-of-accounts') => 'chart-of-accounts',
        default => null,
    };
    $financeLinks = [['budget', 'account_balance', 'Budget'], ['accounting', 'request_quote', 'Accounting'], ['chart-of-accounts', 'list_alt', 'Chart of Accounts'], ['allotment-registry', 'menu_book', 'Allotment Registry'], ['cash', 'payments', 'Cash']];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#103967] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)
    @php $isActive = $activeNav === $item['route']; @endphp
    <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ $isActive ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}" @if($isActive) aria-current="page" @endif><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
    @if($item['label'] === 'Dashboard')
        <details class="group" @if(in_array($activeNav, array_column($financeLinks, 0), true)) open @endif><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1">
            @foreach($financeLinks as [$financeRoute, $financeIcon, $financeLabel])
                @php $financeActive = $activeNav === $financeRoute; @endphp
                <a href="{{ route($financeRoute) }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium {{ $financeActive ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/75 hover:bg-white/10 hover:text-white' }}" @if($financeActive) aria-current="page" @endif><span class="material-symbols-outlined mr-2 text-[17px]">{{ $financeIcon }}</span>{{ $financeLabel }}</a>
            @endforeach
        </div></details>
    @endif
@endforeach
</nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

<div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex min-w-0 items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">@yield('section', 'Finance')</p><p class="truncate text-sm font-semibold text-on-surface">@yield('crumb', 'Budget Allocation')</p></div></div><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
@yield('content')
</div></main></div>
@stack('scripts')
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
