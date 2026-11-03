<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#eef7f8','surface-low':'#e0eff1','surface-container':'#d1e7ea','surface-high':'#c1dde1',primary:'#14818f','primary-container':'#1a98a8','on-surface':'#12303a','on-surface-variant':'#3d5560',secondary:'#2aa86b','outline-variant':'#86adb4',error:'#e0414f'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><style>html{font-size:14px}@media screen{html{font-size:max(12.25px,min(14px,1.4vh,.93vw))}}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    <aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#14818f] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
        @php $navigation=[['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive'],['icon'=>'bar_chart','label'=>'Reports','route'=>'reports','active'=>true],['icon'=>'group','label'=>'User Management'],['icon'=>'card_membership','label'=>'Subscriptions'],['icon'=>'settings','label'=>'School Settings']]; $navigation = array_values(array_filter($navigation, fn ($item) => match ($item['label'] ?? '') { 'User Management' => (bool) auth()->user()?->isAnyMaster(), 'Subscriptions' => (bool) auth()->user()?->hasAccess('subscriptions'), default => true })); if (auth()->user()?->hasAccess('schools')) { array_splice($navigation, count($navigation) - 1, 0, [['icon'=>'domain','label'=>'School Management','route'=>'school-management']]); } if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); } @endphp
        @foreach($navigation as $item)<a href="{{ isset($item['route']) ? route($item['route']) : ($item['label'] === 'User Management' ? route('user-management') : ($item['label'] === 'Subscriptions' ? route('subscriptions') : ($item['label'] === 'School Settings' ? route('school-settings') : '#'))) }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if(($item['label'] ?? '') === 'Dashboard')<details class="group"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('financial-reports') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">assessment</span>Financial Reports</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
    </nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

    <div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Reports &amp; Analytics</p><p class="truncate text-sm font-semibold text-on-surface">Reports</p></div></div><div class="flex items-center gap-4"><button class="relative rounded p-1 text-on-surface-variant" aria-label="Notifications"><span class="material-symbols-outlined text-[21px]">notifications</span><span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-error"></span></button><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></div></header>

    <main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">bar_chart</span><span class="font-semibold text-primary">Management Intelligence</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Reports &amp; Analytics</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Monitor procurement activity, accounting workflow, budget/cash status, and organization-wide spending.</p></div></div>

        @php
            $user = auth()->user();
            $reports = [];
            if ($user->hasPermission('planning.view')) {
                $reports[] = ['icon' => 'event_note', 'title' => 'Annual Implementation Plan (AIP)', 'description' => 'Yearly activities, quarterly financial targets and source of fund per school. An approved AIP creates the budget allotments.', 'category' => 'Planning', 'url' => route('planning').'#aip'];
            }
            if ($user->hasPermission('budget.view')) {
                foreach (\App\Http\Controllers\BudgetAllocationController::REPORTS as $type => $title) {
                    $reports[] = ['icon' => 'bar_chart', 'title' => $title, 'description' => 'Read from the Budget Allocation records; on screen or as a CSV file.', 'category' => 'Budget', 'url' => route('budget.allocation.report', $type)];
                }
                $reports[] = ['icon' => 'account_balance_wallet', 'title' => 'Allotment Registry', 'description' => 'The allotments of the school, their obligations and their balance.', 'category' => 'Budget', 'url' => route('allotment-registry')];
            }
            if ($user->hasPermission('accounting.view') || $user->hasPermission('accounting.manage')) {
                $reports[] = ['icon' => 'menu_book', 'title' => 'Chart of Accounts', 'description' => 'The accounts of the organization and where they are used.', 'category' => 'Accounting', 'url' => route('chart-of-accounts')];
            }
            if ($user->hasPermission('accounting.view')) {
                $reports[] = ['icon' => 'fact_check', 'title' => 'Accounting: ORS and DV', 'description' => 'Obligation Requests and Disbursement Vouchers with their review status.', 'category' => 'Accounting', 'url' => route('accounting')];
            }
            if ($user->hasPermission('cash.pay')) {
                $reports[] = ['icon' => 'payments', 'title' => 'Cash reports', 'description' => 'Report of Checks Issued, Checks and Advices, Cash Disbursement Register and Bank Reconciliation.', 'category' => 'Cash', 'url' => route('cash', ['tab' => 'reports'])];
            }
        @endphp
        <section class="overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="border-b border-outline-variant/30 p-5"><h2 class="text-lg font-semibold">Reports</h2><p class="mt-1 text-xs text-on-surface-variant">The reports of the system, from the school's own records. Choose Generate to show a report below.</p></div>@if($reports === [])<p class="p-5 text-sm text-on-surface-variant">No reports are available for your role.</p>@else<div class="grid grid-cols-1 divide-y divide-outline-variant/20 md:grid-cols-2 md:divide-x md:divide-y-0">@foreach($reports as $report)<div class="flex items-center justify-between gap-4 p-5"><div class="flex min-w-0 items-start gap-3"><span class="material-symbols-outlined text-2xl text-primary">{{ $report['icon'] }}</span><div class="min-w-0"><h3 class="text-sm font-semibold">{{ $report['title'] }}</h3><p class="mt-1 text-xs leading-5 text-on-surface-variant">{{ $report['description'] }}</p><span class="mt-2 inline-flex rounded bg-surface-low px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ $report['category'] }}</span></div></div><button type="button" data-report-url="{{ $report['url'] }}" data-report-title="{{ $report['title'] }}" class="report-generate shrink-0 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-container">Generate</button></div>@endforeach</div>@endif</section>
        <section id="report-output" class="mt-6 hidden overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="flex items-center justify-between gap-4 border-b border-outline-variant/30 p-4"><h2 id="report-output-title" class="text-lg font-semibold"></h2><button type="button" id="report-output-close" class="rounded-lg border border-outline-variant/60 bg-white px-3 py-2 text-xs font-semibold hover:bg-surface-low">Close</button></div><iframe id="report-output-frame" title="Generated report" class="h-[80vh] w-full bg-white"></iframe></section>
    </div></main></div>
<script>
(() => {
    const panel = document.getElementById('report-output');
    const frame = document.getElementById('report-output-frame');
    const title = document.getElementById('report-output-title');
    document.querySelectorAll('.report-generate').forEach((button) => button.addEventListener('click', () => {
        title.textContent = button.dataset.reportTitle;
        frame.src = button.dataset.reportUrl;
        panel.classList.remove('hidden');
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    // The report page is shown without its own menu and top bar, so only the report appears below the list.
    frame.addEventListener('load', () => {
        try {
            const style = frame.contentDocument.createElement('style');
            style.textContent = 'aside, header, nav[aria-label], body > nav { display: none !important; } body > div { padding-left: 0 !important; } main { padding-top: 1rem !important; }';
            frame.contentDocument.head.appendChild(style);
        } catch (error) {}
    });
    document.getElementById('report-output-close').addEventListener('click', () => {
        panel.classList.add('hidden');
        frame.removeAttribute('src');
    });
})();
</script>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
