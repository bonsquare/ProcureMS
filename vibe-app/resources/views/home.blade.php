<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            colors: { surface: '#faf8ff', 'surface-low': '#f4f3fa', 'surface-container': '#eeedf4', 'surface-high': '#e9e7ef', 'surface-highest': '#e3e1e9', primary: '#00236f', 'primary-container': '#1e3a8a', 'on-surface': '#1a1b21', 'on-surface-variant': '#444651', secondary: '#006c4a', outline: '#757682', 'outline-variant': '#c5c5d3', error: '#ba1a1a' },
            fontFamily: { inter: ['Inter', 'sans-serif'] }
        } } };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.input-fixes')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    @php
        $isMasterUser = $isMasterUser ?? auth()->user()?->seesAllSchools();
        $currentSchool = $schools->first();
        $pendingPreRegistrations = $pendingPreRegistrations ?? collect();
    @endphp
    <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col bg-primary px-4 py-6 text-white md:flex">
        <div class="mb-8 flex items-center gap-3 px-2"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary text-white"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">account_balance</span></div><div><p class="text-lg font-bold tracking-tight">ProcureMS</p><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-white/55">Civic Operations</p></div></div>
        <nav class="flex-1 space-y-1" aria-label="Main navigation">
            @php
                $navigation = [
                    ['icon' => 'dashboard', 'label' => 'Dashboard', 'route' => 'home', 'active' => true],
                    ['icon' => 'shopping_cart', 'label' => 'Procurement', 'route' => 'procurement'],
                    ['icon' => 'receipt_long', 'label' => 'Liquidation', 'route' => 'liquidation'],
                    ['icon' => 'folder', 'label' => 'Google Drive', 'route' => 'google-drive'],
                    ['icon' => 'bar_chart', 'label' => 'Reports', 'route' => 'reports'],
                ];
                if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) {
                    array_splice($navigation, 1, 0, [['icon' => 'account_tree', 'label' => 'Planning / SIP', 'route' => 'planning']]);
                }
                if ($isMasterUser) {
                    $navigation[] = ['icon' => 'group', 'label' => 'User Management', 'route' => 'user-management'];
                    if (auth()->user()->hasAccess('subscriptions')) $navigation[] = ['icon' => 'card_membership', 'label' => 'Subscriptions', 'route' => 'subscriptions'];
                    if (auth()->user()->hasAccess('schools')) $navigation[] = ['icon' => 'domain', 'label' => 'School Management', 'route' => 'school-management'];
                }
                $navigation[] = ['icon' => 'settings', 'label' => 'School Settings', 'route' => 'school-settings'];
            @endphp
            @foreach ($navigation as $item)
                <a href="{{ route($item['route']) }}" class="flex items-center rounded px-3 py-2.5 text-sm {{ ($item['active'] ?? false) ? 'bg-primary-container font-semibold text-white' : 'text-white/80 hover:bg-primary-container hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
                 @if(($item['label'] ?? '') === 'Dashboard')
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center rounded px-3 py-2.5 text-sm text-white/80 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary>
                        <div class="mt-1 space-y-1">
                            <a href="{{ route('budget') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a>
                            <a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a>
                            <a href="{{ route('cash') }}" class="ml-8 flex items-center rounded px-3 py-2 text-sm text-white/75 hover:bg-primary-container hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a>
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
        <div class="border-t border-white/15 pt-4 text-xs text-white/60"><p>Multi-School Procurement System</p><p class="mt-1">Secure operational workspace</p></div>
    </aside>

    <div class="md:pl-72">
        @php $dashboardView = $isMasterUser && request('view') !== 'kpi' ? 'system' : 'kpi'; @endphp
        <header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-72 md:px-6">
            <div class="flex items-center gap-3"><button id="open-dashboard-menu" type="button" class="rounded p-2 text-on-surface-variant hover:bg-primary hover:text-white md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button>@if($isMasterUser)<nav class="hidden rounded-xl border border-outline-variant/60 bg-white p-1 text-xs font-bold sm:inline-flex" aria-label="Dashboard views">
                    <a href="{{ route('home') }}" @if($dashboardView === 'system') aria-current="page" @endif class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 {{ $dashboardView === 'system' ? 'bg-primary text-white' : 'text-primary hover:bg-surface-low' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">admin_panel_settings</span>System Master Dashboard</a>
                    <a href="{{ route('home', ['view' => 'kpi']) }}" @if($dashboardView === 'kpi') aria-current="page" @endif class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 {{ $dashboardView === 'kpi' ? 'bg-primary text-white' : 'text-primary hover:bg-surface-low' }}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">monitoring</span>KPI Dashboard</a>
                </nav>@else<p class="flex min-w-0 items-center gap-2 text-sm font-bold uppercase tracking-wide text-on-surface-variant md:text-base"><span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">domain</span><span class="hidden sm:inline">School Workspace</span><span class="hidden sm:inline">/</span><span class="truncate text-primary">{{ $currentSchool?->name ?? 'Assigned School' }}</span></p>@endif</div>
            <div class="relative flex items-center gap-4"><button id="open-notifications" type="button" class="relative rounded p-1 text-on-surface-variant hover:bg-primary hover:text-white" aria-label="Notifications"><span class="material-symbols-outlined text-[21px]">notifications</span>@if($pendingProcurements || $totalSchools > $activeSchools || $pendingPreRegistrations->count())<span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-error"></span>@endif</button><button id="open-user-menu" type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-container" aria-label="User menu"><span class="material-symbols-outlined text-[18px]">person</span></button><div id="user-menu" class="absolute right-0 top-11 hidden w-52 rounded border border-outline-variant/40 bg-white p-2 text-xs shadow-xl"><div class="border-b border-outline-variant/30 px-3 py-2"><p class="font-semibold">{{ auth()->user()->name }}</p><p class="truncate text-on-surface-variant">{{ auth()->user()->email }}</p></div>@if($isMasterUser)<a href="{{ route('user-management') }}" class="mt-1 flex items-center gap-2 rounded px-3 py-2 hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]">manage_accounts</span>User Management</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded px-3 py-2 text-left hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined text-[17px]">logout</span>Sign Out</button></form></div></div>
        </header>

        <main class="min-h-screen bg-surface px-4 pb-16 pt-[4.75rem] md:px-6 lg:px-8"><div class="mx-auto max-w-[1600px]">
            @if($isMasterUser && ($lastBackupFailure ?? null))
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-error/30 bg-error/5 px-4 py-3 text-sm text-error" role="alert"><span class="material-symbols-outlined" aria-hidden="true">warning</span><span class="flex-1">The last database backup failed on {{ $lastBackupFailure->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}.</span><a href="{{ route('backup.index') }}" class="rounded border border-error/40 px-3 py-1 text-xs font-bold hover:bg-error hover:text-white">Open Backup</a></div>
            @endif
            @if($isMasterUser)
            <div class="mb-4 flex flex-col justify-between gap-3 xl:flex-row xl:items-center"><div>@if($isMasterUser)<div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">{{ $isMasterUser ? 'admin_panel_settings' : 'domain' }}</span><span>{{ $isMasterUser ? 'Super Admin Control Center' : 'School Workspace' }}</span><span>/</span><span class="font-semibold text-primary">{{ $isMasterUser ? 'Global Overview' : ($currentSchool?->name ?? 'Assigned School') }}</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $isMasterUser ? 'System Master Dashboard' : 'My KPI Dashboard' }}</h1>@endif<p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $isMasterUser ? 'Real-time telemetry and cross-institution governance for '.$totalSchools.' connected '.Str::plural('campus', $totalSchools).'.' : 'Welcome, '.auth()->user()->name.'. Your school procurement, budget, and liquidation at a glance.' }}</p></div><div class="flex flex-wrap gap-2">@if($isMasterUser)<button type="button" id="open-add-school" class="flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">add_business</span>Add School</button>@endif @if($isMasterUser)<a href="{{ route('school-settings') }}" class="flex items-center gap-2 rounded bg-surface-high px-4 py-2.5 text-xs font-semibold text-on-surface hover:bg-surface-highest"><span class="material-symbols-outlined text-[18px]">domain</span>Manage Schools</a>@endif @if($isMasterUser)<a href="{{ route('subscriptions') }}" class="flex items-center gap-2 rounded bg-surface-high px-4 py-2.5 text-xs font-semibold text-on-surface hover:bg-surface-highest"><span class="material-symbols-outlined text-[18px]">card_membership</span>Subscriptions</a>@endif</div></div>
            @endif

            @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-medium text-secondary">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"><p class="font-semibold">Please correct the following:</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif


            @if($dashboardView === 'kpi')
            @include('partials.dashboard-kpi')
            @else
            @php
                $activeRate = $totalSchools ? round(($activeSchools / $totalSchools) * 100, 1) : 0;
                $metrics = $isMasterUser
                    ? [
                        ['label'=>'Total Schools','value'=>$totalSchools,'change'=>'Live database','footer'=>'Registered Campuses','icon'=>'school','tone'=>'primary','target'=>'schools'],
                        ['label'=>'Active Schools','value'=>$activeSchools,'change'=>$activeRate.'% operational','footer'=>($totalSchools - $activeSchools).' Inactive','icon'=>'verified','tone'=>'secondary','target'=>'schools','bar'=>$activeRate],
                        ['label'=>'Total Users','value'=>$totalUsers,'change'=>'Registered accounts','footer'=>'Admins & Staff','icon'=>'group','tone'=>'primary','target'=>'user-management'],
                        ['label'=>'Active Subscriptions','value'=>$activeSubscriptions,'change'=>'Current plans','footer'=>'All subscription tiers','icon'=>'card_membership','tone'=>'secondary','target'=>'subscriptions'],
                        ['label'=>'Pending PRs','value'=>$pendingProcurements,'change'=>'Needs review','footer'=>'Procurement Queue','icon'=>'pending_actions','tone'=>'error','target'=>'tasks'],
                    ]
                    : [
                        ['label'=>'My School','value'=>$totalSchools,'change'=>$currentSchool?->status === 'active' ? 'Active' : 'Assigned','footer'=>$currentSchool?->name ?? 'No school assigned','icon'=>'school','tone'=>'primary','target'=>'schools'],
                        ['label'=>'School Users','value'=>$totalUsers,'change'=>'Scoped accounts','footer'=>'Only your school','icon'=>'group','tone'=>'primary','target'=>'schools'],
                        ['label'=>'Pending PRs','value'=>$pendingProcurements,'change'=>'Needs action','footer'=>'Your school queue','icon'=>'pending_actions','tone'=>'error','target'=>'tasks'],
                        ['label'=>'Subscription','value'=>$activeSubscriptions,'change'=>'School plan','footer'=>'Your school only','icon'=>'card_membership','tone'=>'secondary','target'=>'schools'],
                    ];
            @endphp
            <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 {{ $isMasterUser ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
                @foreach ($metrics as $metric)
                    @php
                        [$accent, $soft] = match ($metric['tone']) { 'secondary' => ['#2a7f64', '#ddf0e8'], 'error' => ['#b64a50', '#f8e2e4'], default => ['#103967', '#e7eef4'] };
                    @endphp
                    <article data-dashboard-card="{{ $metric['target'] }}" class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-outline-variant/50 bg-white p-4 pl-5">
                        <span class="absolute inset-y-0 left-0 w-1" style="background:{{ $accent }}" aria-hidden="true"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $metric['label'] }}</p>
                                <p class="mt-1.5 text-3xl font-bold leading-none tabular-nums" style="color:{{ $metric['tone'] === 'error' ? $accent : '#103967' }}">{{ $metric['value'] }}</p>
                            </div>
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="background:{{ $soft }};color:{{ $accent }}"><span class="material-symbols-outlined text-[21px]" aria-hidden="true">{{ $metric['icon'] }}</span></span>
                        </div>
                        @isset($metric['bar'])<div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-container" role="progressbar" aria-valuenow="{{ $metric['bar'] }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full" style="width:{{ min(100, $metric['bar']) }}%;background:{{ $accent }}"></div></div>@endisset
                        <p class="mt-2 text-xs font-semibold" style="color:{{ $accent }}">{{ $metric['change'] }}</p>
                        <div class="mt-3 flex items-center justify-between border-t border-outline-variant/40 pt-2.5 text-xs text-on-surface-variant"><span>{{ $metric['footer'] }}</span><span class="material-symbols-outlined text-[16px] transition group-hover:translate-x-0.5" aria-hidden="true">chevron_right</span></div>
                    </article>
                @endforeach
            </div>
            @endif

            @if($dashboardView === 'system')
            <section aria-label="System KPIs" class="mb-4 rounded-xl border border-primary/15 border-l-[3px] border-l-primary bg-[#eef4fa] p-3">
                <div class="mb-2.5 flex items-center justify-between"><h2 class="flex items-center gap-1.5 text-sm font-bold text-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">monitoring</span>System KPIs</h2><span class="flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-secondary"><i class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-secondary"></i>Live</span></div>
                <div class="grid grid-cols-2 gap-2 xl:grid-cols-4">
                    @foreach([
                        ['Database', $systemKpi['database'], $systemKpi['database_note'], 'database', $systemKpi['database'] === 'Connected' ? '#2a7f64' : '#b64a50', $systemKpi['database'] === 'Connected' ? '#ddf0e8' : '#f8e2e4'],
                        ['Storage', $systemKpi['storage'], 'Application files and cache', 'hard_drive', $systemKpi['storage'] === 'Ready' ? '#2a7f64' : '#b64a50', $systemKpi['storage'] === 'Ready' ? '#ddf0e8' : '#f8e2e4'],
                        ['Open tasks', $systemKpi['tasks'], $systemKpi['tasks'] ? 'Approvals and activations waiting' : 'Nothing waiting', 'task_alt', $systemKpi['tasks'] ? '#b46f1f' : '#2a7f64', $systemKpi['tasks'] ? '#fff0dc' : '#ddf0e8'],
                        ['Audit events', $systemKpi['audit_today'], 'Recorded in the last 24 hours', 'history', '#286da8', '#e2edf7'],
                    ] as [$kpiLabel, $kpiValue, $kpiNote, $kpiIcon, $kpiColor, $kpiSoft])
                        <article class="relative flex items-center justify-between gap-2 overflow-hidden rounded-lg border border-outline-variant/40 bg-white py-2 pl-4 pr-3"><span class="absolute inset-y-0 left-0 w-1" style="background:{{ $kpiColor }}" aria-hidden="true"></span>
                            <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ $kpiLabel }}</p><p class="mt-0.5 text-base font-bold leading-none" style="color:{{ $kpiColor }}">{{ $kpiValue }}</p><p class="mt-0.5 truncate text-[11px] text-on-surface-variant">{{ $kpiNote }}</p></div>
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md" style="background:{{ $kpiSoft }};color:{{ $kpiColor }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $kpiIcon }}</span></span>
                        </article>
                    @endforeach
                </div>
            </section>
            <section id="schools-section" class="overflow-hidden rounded border border-outline-variant/30 bg-white"><div class="flex flex-col justify-between gap-3 border-b border-l-[3px] border-primary/15 border-l-primary bg-[#eef4fa] px-4 py-2.5 sm:flex-row sm:items-center"><div><h2 class="text-sm font-bold leading-5 text-primary">{{ $isMasterUser ? 'Recent Schools & Organizations' : 'My School' }}</h2><p class="mt-0.5 text-xs text-on-surface-variant">{{ $isMasterUser ? 'Manage institutional subscriptions, verification statuses, and billing profiles.' : 'School users only see records attached to their assigned school.' }}</p></div><div class="flex flex-wrap gap-2"><div class="relative"><span class="material-symbols-outlined absolute left-2.5 top-1.5 text-[17px] text-on-surface-variant">search</span><input id="school-search" class="w-full rounded-lg border border-outline-variant bg-white py-1.5 pl-8 pr-3 text-xs text-on-surface outline-none focus:border-action sm:w-52" placeholder="Search schools..." type="search"></div><select id="school-status-filter" class="rounded-lg border border-outline-variant bg-white px-2.5 py-1.5 text-xs text-on-surface outline-none focus:border-action"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] border-collapse text-left"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr class="border-b border-outline-variant/30"><th class="px-5 py-3 font-semibold">School / Institution</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Subscription Plan</th><th class="px-5 py-3 font-semibold">Users</th><th class="px-5 py-3 font-semibold">Renewal Date</th><th class="px-5 py-3 text-right font-semibold">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20 text-[13px]">
                    @forelse ($schools as $school)
                        @php $subscription = $school->subscriptions->first(); $active = $school->status === 'active'; $initials = collect(preg_split('/\s+/', trim($school->name)))->filter()->take(2)->map(fn($word) => strtoupper(substr($word, 0, 1)))->implode(''); @endphp
                        <tr data-school-row data-name="{{ strtolower($school->name . ' ' . $school->code . ' ' . $school->address) }}" data-status="{{ $school->status }}" class="hover:bg-surface-low/60"><td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-sm font-bold text-primary">{{ $initials ?: 'SC' }}</div><div><div class="font-semibold">{{ $school->name }}</div><div class="mt-0.5 text-xs text-on-surface-variant">ID: {{ $school->code }} · {{ $school->address ?: 'Address not set' }}</div></div></div></td><td class="px-5 py-3.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-semibold {{ $active ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error' }}"><span class="h-1.5 w-1.5 rounded-full {{ $active ? 'bg-secondary' : 'bg-error' }}"></span>{{ $active ? 'Active' : 'Inactive' }}</span></td><td class="px-5 py-3.5 font-medium">{{ $subscription?->plan ?? 'No subscription' }}</td><td class="px-5 py-3.5 text-on-surface-variant tabular-nums">{{ $school->users_count }} {{ Str::plural('user', $school->users_count) }}</td><td class="px-5 py-3.5 {{ $active ? 'text-on-surface-variant' : 'text-error' }}">{{ $subscription?->renews_at ? \Carbon\Carbon::parse($subscription->renews_at)->format('M d, Y') : 'Not scheduled' }}</td><td class="px-5 py-3.5 text-right"><div class="flex justify-end gap-1">@if($isMasterUser && !$active)<form method="POST" action="{{ route('school-settings.school.approve', $school) }}">@csrf<input type="hidden" name="redirect_to" value="dashboard"><button type="submit" class="rounded px-2.5 py-1.5 text-xs font-semibold text-secondary hover:bg-secondary hover:text-white" title="Activate school">Activate</button></form>@endif<a href="{{ route('school-settings', ['school_id' => $school->id]) }}" class="rounded p-1.5 text-on-surface-variant hover:bg-surface-high hover:text-primary" title="View and edit school"><span class="material-symbols-outlined text-[18px]">settings</span></a></div></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-on-surface-variant">{{ $isMasterUser ? 'No schools have been added yet. Use the Add School button to create the first one.' : 'No school is assigned to your account yet.' }}</td></tr>
                    @endforelse
                    <tr id="school-filter-empty" class="hidden"><td colspan="6" class="px-5 py-10 text-center text-sm text-on-surface-variant">No schools match the selected search and status.</td></tr>
                </tbody></table></div><div class="flex flex-col justify-between gap-3 border-t border-outline-variant/20 bg-surface-low px-5 py-3 text-xs text-on-surface-variant sm:flex-row sm:items-center"><span>Showing {{ $schools->count() }} {{ Str::plural('institution', $schools->count()) }}</span><a href="{{ route('school-settings') }}" class="font-semibold text-primary hover:underline">{{ $isMasterUser ? 'Manage all schools' : 'Open school settings' }}</a></div>
            </section>

            <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="flex flex-col justify-between rounded border border-outline-variant/30 bg-white p-5"><div><div class="mb-4 flex items-center justify-between"><h3 class="text-lg font-semibold">System Health</h3><span class="rounded bg-secondary/10 px-2 py-1 text-xs font-semibold text-secondary">Live Status</span></div><p class="mb-5 text-[13px] text-on-surface-variant">Check the application database, storage, and cache services.</p><div class="space-y-4"><div><div class="mb-1 flex justify-between text-xs"><span class="text-on-surface-variant">Primary Procurement Database</span><span class="font-semibold text-secondary">Connected</span></div><div class="h-2 overflow-hidden rounded-full bg-surface-container"><div class="h-full w-full rounded-full bg-secondary"></div></div></div><div><div class="mb-1 flex justify-between text-xs"><span class="text-on-surface-variant">Application Storage</span><span class="font-semibold text-primary">Ready</span></div><div class="h-2 overflow-hidden rounded-full bg-surface-container"><div class="h-full w-full rounded-full bg-primary"></div></div></div></div></div><div class="mt-6 flex items-center justify-between border-t border-outline-variant/20 pt-4 text-xs text-on-surface-variant"><span>On-demand status check</span><button id="run-diagnostics" type="button" class="font-semibold text-primary hover:underline">Run Diagnostics</button></div></section>
                <section class="flex flex-col justify-between rounded border border-outline-variant/30 bg-white p-5"><div><div class="mb-4 flex items-center justify-between"><h3 class="text-lg font-semibold">Pending Tasks</h3><span class="rounded bg-error/10 px-2 py-1 text-xs font-semibold text-error">{{ $pendingProcurements + ($isMasterUser ? $pendingPreRegistrations->count() : 0) }} Pending</span></div><div class="space-y-2"><div class="flex gap-2 rounded bg-surface-low p-2"><span class="material-symbols-outlined text-[18px] text-error">priority_high</span><div class="min-w-0 flex-1"><div class="flex justify-between text-xs font-semibold"><span class="truncate">Procurement Approvals</span><span class="text-[10px] font-normal text-on-surface-variant">Live</span></div><p class="truncate text-xs text-on-surface-variant">{{ $pendingProcurements }} purchase requests require review.</p></div></div>@if($isMasterUser)<div class="flex gap-2 rounded bg-surface-low p-2"><span class="material-symbols-outlined text-[18px] text-primary">domain_disabled</span><div class="min-w-0 flex-1"><div class="flex justify-between text-xs font-semibold"><span class="truncate">School Activations</span><span class="text-[10px] font-normal text-on-surface-variant">Live</span></div><p class="truncate text-xs text-on-surface-variant">{{ $pendingPreRegistrations->count() }} schools are waiting for activation.</p></div></div>@endif</div></div><div class="mt-6 flex items-center justify-between border-t border-outline-variant/20 pt-4 text-xs text-on-surface-variant"><span>Action queue</span><button id="view-all-tasks" type="button" class="font-semibold text-primary hover:underline">View All Tasks</button></div></section>
                <section class="flex flex-col justify-between rounded border border-outline-variant/30 bg-white p-5"><div><div class="mb-4 flex items-center justify-between"><h3 class="text-lg font-semibold">Audit Log Stream</h3><span class="material-symbols-outlined text-[18px] text-on-surface-variant">history</span></div><div class="space-y-3 text-xs text-on-surface-variant">@forelse($auditLogs->take(3) as $log)<div class="flex items-start gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span><span><strong class="text-on-surface">{{ $log->user?->name ?? 'System' }}</strong> {{ str($log->action)->replace('_', ' ') }}<small class="mt-0.5 block">{{ $log->created_at->diffForHumans() }}</small></span></div>@empty<div class="rounded bg-surface-low p-3">No audit activity recorded yet.</div>@endforelse</div></div><div class="mt-6 flex items-center justify-between border-t border-outline-variant/20 pt-4 text-xs text-on-surface-variant"><span>{{ $auditLogs->count() }} recent records</span><a href="{{ route('dashboard.audit-logs.export') }}" class="font-semibold text-primary hover:underline">Export Logs</a></div></section>
            </div>
            @endif
        </div></main>
    </div>
    <button id="dashboard-sidebar-overlay" type="button" class="fixed inset-0 z-40 hidden bg-black/40 md:hidden" aria-label="Close navigation"></button>
    <div id="notifications-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-outline-variant/30 px-5 py-4"><h2 class="text-lg font-semibold">Notifications</h2><button type="button" data-close-modal="notifications-modal" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div><div class="space-y-3 p-5"><a href="{{ route('procurement') }}" class="flex items-start gap-3 rounded bg-surface-low p-3 hover:bg-surface-container"><span class="material-symbols-outlined text-error">pending_actions</span><div><p class="text-sm font-semibold">Procurement approvals</p><p class="mt-1 text-xs text-on-surface-variant">{{ $pendingProcurements }} requests are waiting for review.</p></div></a>@if($isMasterUser)<button type="button" data-close-modal="notifications-modal" onclick="document.querySelector('#schools-section')?.scrollIntoView({behavior:'smooth'})" class="flex w-full items-start gap-3 rounded bg-surface-low p-3 text-left hover:bg-surface-container"><span class="material-symbols-outlined text-primary">domain_disabled</span><div><p class="text-sm font-semibold">Schools for activation</p><p class="mt-1 text-xs text-on-surface-variant">{{ $pendingPreRegistrations->count() }} school registrations are waiting for activation.</p></div></button>@endif</div></div></div>
    <div id="diagnostics-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-outline-variant/30 px-5 py-4"><h2 class="text-lg font-semibold">System Diagnostics</h2><button type="button" data-close-modal="diagnostics-modal" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div><div id="diagnostics-results" class="p-5 text-sm text-on-surface-variant">Running diagnostics…</div></div></div>
    <div id="tasks-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-xl rounded bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-outline-variant/30 px-5 py-4"><h2 class="text-lg font-semibold">Action Queue</h2><button type="button" data-close-modal="tasks-modal" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div><div class="space-y-3 p-5"><a href="{{ route('procurement') }}" class="flex items-center justify-between rounded border border-outline-variant/30 p-4 hover:border-primary"><div><p class="text-sm font-semibold">Review Procurement Requests</p><p class="mt-1 text-xs text-on-surface-variant">{{ $pendingProcurements }} pending requests</p></div><span class="material-symbols-outlined text-primary">arrow_forward</span></a><button type="button" data-close-modal="tasks-modal" onclick="document.querySelector('#schools-section')?.scrollIntoView({behavior:'smooth'})" class="flex w-full items-center justify-between rounded border border-outline-variant/30 p-4 text-left hover:border-primary"><div><p class="text-sm font-semibold">{{ $isMasterUser ? 'Activate Pending Schools' : 'Open School Settings' }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $isMasterUser ? $pendingPreRegistrations->count().' school registrations waiting' : 'Manage your assigned school profile' }}</p></div><span class="material-symbols-outlined text-primary">arrow_forward</span></button>@if($isMasterUser)<a href="{{ route('subscriptions') }}" class="flex items-center justify-between rounded border border-outline-variant/30 p-4 hover:border-primary"><div><p class="text-sm font-semibold">Manage Subscriptions</p><p class="mt-1 text-xs text-on-surface-variant">{{ $activeSubscriptions }} active subscriptions</p></div><span class="material-symbols-outlined text-primary">arrow_forward</span></a>@endif</div></div></div>
    @if($isMasterUser)
    <div id="add-school-modal" class="fixed inset-0 z-[100] {{ $errors->any() ? 'flex' : 'hidden' }} items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="add-school-title">
        <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-outline-variant/30 bg-white px-6 py-4"><div><h2 id="add-school-title" class="text-xl font-semibold">Add School</h2><p class="mt-1 text-xs text-on-surface-variant">Register a new school or institution in ProcureMS.</p></div><button type="button" data-close-school-modal class="rounded p-2 text-on-surface-variant hover:bg-primary hover:text-white" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
            <form method="POST" action="{{ route('schools.store') }}" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">@csrf
                <label class="text-xs font-semibold text-on-surface-variant">School ID / Code<input value="System generated after saving" disabled class="mt-2 w-full cursor-not-allowed rounded border border-outline-variant/50 bg-surface-container px-3 py-2.5 text-sm font-normal text-on-surface-variant"></label>
                <label class="text-xs font-semibold text-on-surface-variant">School Name <span class="text-error">*</span><input name="name" required value="{{ old('name') }}" placeholder="Official school name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">School Type<select name="school_type" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><option value="">Select type</option>@foreach(['Elementary','Secondary','Integrated','Higher Education'] as $type)<option @selected(old('school_type') === $type)>{{ $type }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-on-surface-variant">Status<select name="status" required class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><option value="active" @selected(old('status', 'active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select></label>
                <label class="text-xs font-semibold text-on-surface-variant">Region<input name="region" list="place-regions" autocomplete="off" value="{{ old('region') }}" placeholder="Enter region" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Schools Division<input name="division" list="place-divisions" autocomplete="off" value="{{ old('division') }}" placeholder="Division office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">District<input name="district" list="place-districts" autocomplete="off" value="{{ old('district') }}" placeholder="District office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">School Email<input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="school@example.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Contact Number<input name="contact_number" value="{{ old('contact_number') }}" placeholder="Telephone or mobile" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">School Address<textarea name="address" rows="2" placeholder="Complete school address" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">{{ old('address') }}</textarea></label>
                <div class="mt-2 border-t border-outline-variant/30 pt-5 md:col-span-2"><h3 class="text-base font-semibold">System User Details</h3><p class="mt-1 text-xs text-on-surface-variant">Create the initial account that will manage this school.</p></div>
                <label class="text-xs font-semibold text-on-surface-variant">Full Name <span class="text-error">*</span><input name="system_user_name" required value="{{ old('system_user_name') }}" placeholder="System user full name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Email Address <span class="text-error">*</span><input type="email" name="system_user_email" required value="{{ old('system_user_email') }}" placeholder="admin@school.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">System Role <span class="text-error">*</span><select name="system_user_role" required class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">@foreach(\App\Models\User::ROLES as $roleKey => $roleLabel)<option value="{{ $roleKey }}" @selected(old('system_user_role', 'school_admin') === $roleKey)>{{ $roleLabel }}</option>@endforeach</select></label>
                <div></div>
                <label class="text-xs font-semibold text-on-surface-variant">Temporary Password <span class="text-error">*</span><input type="password" name="system_user_password" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <label class="text-xs font-semibold text-on-surface-variant">Confirm Password <span class="text-error">*</span><input type="password" name="system_user_password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Repeat temporary password" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
                <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-4 md:col-span-2"><button type="button" data-close-school-modal class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Cancel</button><button type="submit" class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Save School &amp; User</button></div>
            </form>
        </div>
    </div>
    @endif
    <script>
        const openModal = (id) => { const modal = document.getElementById(id); modal?.classList.remove('hidden'); modal?.classList.add('flex'); };
        const closeModal = (id) => { const modal = document.getElementById(id); modal?.classList.add('hidden'); modal?.classList.remove('flex'); };
        const showDashboardMessage = (message) => {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-5 right-5 z-[120] rounded bg-primary px-4 py-3 text-sm font-semibold text-white shadow-xl';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        };

        const sidebar = document.getElementById('dashboard-sidebar');
        const sidebarOverlay = document.getElementById('dashboard-sidebar-overlay');
        document.getElementById('open-dashboard-menu')?.addEventListener('click', () => { sidebar.classList.remove('hidden'); sidebar.classList.add('flex'); sidebarOverlay.classList.remove('hidden'); });
        sidebarOverlay?.addEventListener('click', () => { sidebar.classList.add('hidden'); sidebar.classList.remove('flex'); sidebarOverlay.classList.add('hidden'); });

        const roleView = document.getElementById('dashboard-role-view');
        const savedRoleView = localStorage.getItem('procurems-dashboard-role');
        if (roleView && savedRoleView) roleView.value = savedRoleView;
        roleView?.addEventListener('change', () => { localStorage.setItem('procurems-dashboard-role', roleView.value); showDashboardMessage(`Dashboard view changed to ${roleView.value}`); });

        const userMenu = document.getElementById('user-menu');
        document.getElementById('open-user-menu')?.addEventListener('click', (event) => { event.stopPropagation(); userMenu.classList.toggle('hidden'); });
        document.addEventListener('click', (event) => { if (!userMenu?.contains(event.target)) userMenu?.classList.add('hidden'); });

        document.getElementById('open-notifications')?.addEventListener('click', () => openModal('notifications-modal'));
        document.getElementById('view-all-tasks')?.addEventListener('click', () => openModal('tasks-modal'));
        document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.dataset.closeModal)));
        ['notifications-modal', 'diagnostics-modal', 'tasks-modal'].forEach((id) => document.getElementById(id)?.addEventListener('click', (event) => { if (event.target.id === id) closeModal(id); }));

        const filterSchools = () => {
            const query = document.getElementById('school-search').value.trim().toLowerCase();
            const status = document.getElementById('school-status-filter').value;
            let visible = 0;
            document.querySelectorAll('[data-school-row]').forEach((row) => {
                const matches = row.dataset.name.includes(query) && (status === 'all' || row.dataset.status === status);
                row.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            document.getElementById('school-filter-empty')?.classList.toggle('hidden', visible !== 0);
        };
        document.getElementById('school-search')?.addEventListener('input', filterSchools);
        document.getElementById('school-status-filter')?.addEventListener('change', filterSchools);

        document.getElementById('run-diagnostics')?.addEventListener('click', async () => {
            openModal('diagnostics-modal');
            const output = document.getElementById('diagnostics-results');
            output.textContent = 'Running diagnostics…';
            try {
                const response = await fetch(@json(route('dashboard.diagnostics')), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Diagnostic request failed');
                const data = await response.json();
                output.innerHTML = `<div class="space-y-3"><div class="flex justify-between rounded bg-surface-low p-3"><span>Database</span><strong class="text-secondary">${data.database} (${data.database_latency_ms} ms)</strong></div><div class="flex justify-between rounded bg-surface-low p-3"><span>Storage</span><strong class="text-secondary">${data.storage}</strong></div><div class="flex justify-between rounded bg-surface-low p-3"><span>Cache</span><strong class="text-secondary">${data.cache}</strong></div><p class="pt-2 text-xs">Checked ${data.checked_at}</p></div>`;
            } catch (error) {
                output.innerHTML = '<p class="rounded bg-error/10 p-3 font-semibold text-error">Diagnostics could not be completed. Please try again.</p>';
            }
        });

        document.querySelectorAll('[data-dashboard-card]').forEach((card) => {
            card.classList.add('cursor-pointer', 'transition-colors', 'hover:border-primary');
            card.setAttribute('tabindex', '0');
            const activate = () => {
                const target = card.dataset.dashboardCard;
                if (target === 'schools') document.getElementById('schools-section')?.scrollIntoView({ behavior: 'smooth' });
                else if (target === 'user-management') window.location.href = @json(route('user-management'));
                else if (target === 'subscriptions') window.location.href = @json(route('subscriptions'));
                else openModal('tasks-modal');
            };
            card.addEventListener('click', activate);
            card.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') activate(); });
        });

        const schoolModal = document.getElementById('add-school-modal');
        document.getElementById('open-add-school')?.addEventListener('click', () => openModal('add-school-modal'));
        document.querySelectorAll('[data-close-school-modal]').forEach((button) => button.addEventListener('click', () => closeModal('add-school-modal')));
        schoolModal?.addEventListener('click', (event) => { if (event.target === schoolModal) closeModal('add-school-modal'); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') ['add-school-modal', 'notifications-modal', 'diagnostics-modal', 'tasks-modal'].forEach(closeModal); });
    </script>
@include('partials.profile-menu')
@include('partials.place-suggestions')
</body>
</html>
