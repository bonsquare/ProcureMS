<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#e6edf4','surface-low':'#dbe5ee','surface-container':'#cfdbe6','surface-high':'#c0cfdd',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#374a5d',secondary:'#369878','outline-variant':'#7f95a9',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>html{font-size:14px}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    <aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#103967] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
        @php $navigation=[['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive','active'=>true],['icon'=>'bar_chart','label'=>'Reports'],['icon'=>'group','label'=>'User Management'],['icon'=>'card_membership','label'=>'Subscriptions'],['icon'=>'settings','label'=>'School Settings']]; $navigation = array_values(array_filter($navigation, fn ($item) => match ($item['label'] ?? '') { 'User Management' => (bool) auth()->user()?->isAnyMaster(), 'Subscriptions' => (bool) auth()->user()?->hasAccess('subscriptions'), default => true })); if (auth()->user()?->hasAccess('schools')) { array_splice($navigation, count($navigation) - 1, 0, [['icon'=>'domain','label'=>'School Management','route'=>'school-management']]); } if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); } @endphp
        @foreach($navigation as $item)<a href="{{ isset($item['route']) ? route($item['route']) : ($item['label'] === 'Reports' ? route('reports') : ($item['label'] === 'User Management' ? route('user-management') : ($item['label'] === 'Subscriptions' ? route('subscriptions') : ($item['label'] === 'School Settings' ? route('school-settings') : '#')))) }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if(($item['label'] ?? '') === 'Dashboard')<details class="group"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('financial-reports') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">assessment</span>Financial Reports</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
    </nav><div class="border-t border-white/15 px-2 pt-4 text-[11px] leading-5 text-white/55"><p>Multi-School Procurement System</p><p>Secure operational workspace</p></div></aside>

    <div class="md:pl-60"><header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-outline-variant/30 bg-surface/95 px-4 backdrop-blur md:left-60 md:px-6"><div class="flex items-center gap-3"><button class="rounded p-2 text-on-surface-variant md:hidden" aria-label="Open navigation"><span class="material-symbols-outlined">menu</span></button><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Document Workspace</p><p class="truncate text-sm font-semibold text-on-surface">Google Drive</p></div></div><div class="flex items-center gap-4"><button class="relative rounded p-1 text-on-surface-variant" aria-label="Notifications"><span class="material-symbols-outlined text-[21px]">notifications</span><span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-error"></span></button><div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[18px]">person</span></div></div></header>

    <main class="min-h-screen bg-surface px-4 pb-16 pt-24 md:px-6 lg:px-8">
        <div class="mb-6"><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">folder</span><span class="font-semibold text-primary">Your files</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">Google Drive</h1><p class="mt-1 max-w-2xl text-sm text-on-surface-variant">Connect your own Google Drive. Files you upload to ProcMS are kept in your Drive, in a folder only you can open. Nobody else in the system can see them.</p></div>
        @if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ session('error') }}</div>@endif

        @php
            $connected = $connection?->isConnected();
            $needsReconnect = $connection && ! $connected;
        @endphp
        <section class="grid max-w-5xl gap-5 lg:grid-cols-[1.4fr_1fr]">
            <div class="overflow-hidden rounded-2xl border border-outline-variant/30 bg-white shadow-sm">
                <div class="flex items-center gap-4 px-6 py-5 {{ $connected ? 'bg-secondary/10' : ($needsReconnect ? 'bg-error/10' : 'bg-surface-low') }}">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm"><span class="material-symbols-outlined text-[32px] {{ $connected ? 'text-secondary' : ($needsReconnect ? 'text-error' : 'text-primary') }}">{{ $connected ? 'cloud_done' : ($needsReconnect ? 'cloud_off' : 'cloud') }}</span></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Connection status</p>
                        <h2 class="text-xl font-semibold leading-7">{{ $connected ? 'Connected' : ($needsReconnect ? 'Connection needs to be renewed' : 'Not connected') }}</h2>
                    </div>
                </div>
                <div class="px-6 py-5">
                    @if($connection)
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div><dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Google account</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $connection->google_email ?: 'unknown' }}</dd></div>
                            <div><dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Connected since</dt><dd class="mt-1 text-sm font-semibold">{{ $connection->connected_at?->format('M d, Y') }}</dd></div>
                        </dl>
                    @else
                        <p class="text-sm text-on-surface-variant">You must connect your Google Drive before you can upload files. ProcMS only sees the folders and files it creates itself, never the rest of your Drive.</p>
                    @endif
                    @unless($configured)<p class="mt-4 rounded-lg border border-error/30 bg-error/5 px-3 py-2 text-xs text-error">Google Drive is not set up on this server yet. Ask the system administrator.</p>@endunless
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if($configured)<a href="{{ route('google-drive.redirect') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">link</span>{{ $needsReconnect ? 'Reconnect Google Drive' : ($connected ? 'Connect a different account' : 'Connect Google Drive') }}</a>@endif
                        @if($connection)
                            <form method="POST" action="{{ route('google-drive.disconnect') }}" onsubmit="return confirm('Disconnect Google Drive? Your files stay in your Drive.')">@csrf @method('DELETE')<button class="inline-flex items-center gap-2 rounded-xl border border-outline-variant px-5 py-3 text-xs font-semibold hover:bg-surface-container"><span class="material-symbols-outlined text-[18px]">link_off</span>Disconnect</button></form>
                        @endif
                    </div>
                </div>
                <div class="grid gap-3 border-t border-outline-variant/30 bg-surface/50 p-4 sm:grid-cols-2">
                    <a href="{{ route('drive-files.index') }}" class="flex items-center gap-3 rounded-xl border border-outline-variant/30 bg-white p-3 hover:border-primary/50 hover:shadow-sm"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]">folder_open</span></span><span><span class="block text-sm font-semibold">My Drive Files</span><span class="block text-xs text-on-surface-variant">Browse what you uploaded</span></span></a>
                    @if(auth()->user()->hasAccess('backup'))<a href="{{ route('backup.index') }}" class="flex items-center gap-3 rounded-xl border border-outline-variant/30 bg-white p-3 hover:border-primary/50 hover:shadow-sm"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]">backup</span></span><span><span class="block text-sm font-semibold">Database Backup</span><span class="block text-xs text-on-surface-variant">Daily copy to your Drive</span></span></a>@endif
                </div>
            </div>

            <aside class="rounded-2xl border border-outline-variant/30 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Your private folder</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex items-center gap-2 font-semibold"><span class="material-symbols-outlined text-[20px] text-primary">folder</span>ProcMS</li>
                    @foreach(['Backup' => 'database copies', 'Logo' => 'school logos', 'Files' => 'your uploads'] as $folder => $note)
                        <li class="ml-3 flex items-center gap-2 border-l border-outline-variant/40 pl-4"><span class="material-symbols-outlined text-[18px] text-on-surface-variant">subdirectory_arrow_right</span><span class="font-medium">{{ $folder }}</span><span class="text-xs text-on-surface-variant">{{ $note }}</span></li>
                    @endforeach
                </ul>
                <div class="mt-5 space-y-3 border-t border-outline-variant/30 pt-5 text-xs text-on-surface-variant">
                    <p class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-secondary">lock</span>Only you can open this folder. Nobody else in the system sees it.</p>
                    <p class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-secondary">visibility_off</span>ProcMS never sees the rest of your Drive.</p>
                </div>
            </aside>
        </section>
    </div></main></div>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
