<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield("title", "Budget Allocation") · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4','surface-high':'#e9e7ef',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
@php
    $isMasterUser = auth()->user()?->role === 'master_user';
    $navigation = [['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports']];
    if ($isMasterUser) { $navigation[]=['icon'=>'group','label'=>'User Management','route'=>'user-management']; $navigation[]=['icon'=>'card_membership','label'=>'Subscriptions','route'=>'subscriptions']; }
    $navigation[]=['icon'=>'settings','label'=>'School Settings','route'=>'school-settings'];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $inputClass = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary';
@endphp
<aside class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col bg-primary px-4 py-6 text-white md:flex"><div class="mb-8 flex items-center gap-3 px-2"><div class="flex h-8 w-8 items-center justify-center rounded bg-secondary"><span class="material-symbols-outlined text-[20px]">school</span></div><span class="text-xl font-semibold">ProcureMS</span></div><nav class="flex-1 space-y-1" aria-label="Main navigation">
    @foreach($navigation as $item)<a href="{{ route($item['route']) }}" class="flex items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>@if($item['label']==='Dashboard')<details class="group" open><summary class="flex cursor-pointer list-none items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded bg-primary-container px-3 py-2 text-sm font-semibold text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
</nav><div class="border-t border-white/15 pt-4 text-xs text-white/60"><p>Multi-School Procurement System</p></div></aside>

<div class="md:pl-72"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-72 md:px-6"><span class="text-sm font-semibold text-on-surface-variant">Finance · @yield('crumb', 'Budget Allocation')</span><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></header>

<main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
@yield('content')
</div></main></div>
@stack('scripts')
@include('partials.profile-menu')
</body>
</html>
