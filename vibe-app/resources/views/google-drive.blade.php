<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb','surface-low':'#eef3f7','surface-container':'#e4ebf1','surface-high':'#d8e2ea',primary:'#103967','primary-container':'#174b82','on-surface':'#172538','on-surface-variant':'#536273',secondary:'#369878','outline-variant':'#cbd7e1',error:'#b64a50'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>html{font-size:14px}</style>
@include('partials.input-fixes')
@include('partials.favicon')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    <aside class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col bg-[#103967] px-4 py-6 text-white md:flex">@include('partials.brand')<nav class="flex-1 space-y-1" aria-label="Main navigation">
        @php $navigation=[['icon'=>'dashboard','label'=>'Dashboard','route'=>'home'],['icon'=>'shopping_cart','label'=>'Procurement','route'=>'procurement'],['icon'=>'receipt_long','label'=>'Liquidation','route'=>'liquidation'],['icon'=>'folder','label'=>'Google Drive','route'=>'google-drive','active'=>true],['icon'=>'bar_chart','label'=>'Reports'],['icon'=>'group','label'=>'User Management'],['icon'=>'card_membership','label'=>'Subscriptions'],['icon'=>'settings','label'=>'School Settings']]; $navigation = array_values(array_filter($navigation, fn ($item) => match ($item['label'] ?? '') { 'User Management' => (bool) auth()->user()?->isAnyMaster(), 'Subscriptions' => (bool) auth()->user()?->hasAccess('subscriptions'), default => true })); if (auth()->user()?->hasAccess('schools')) { array_splice($navigation, count($navigation) - 1, 0, [['icon'=>'domain','label'=>'School Management','route'=>'school-management']]); } if (auth()->user()?->hasPermission('planning.view') || auth()->user()?->hasPermission('planning.manage')) { array_splice($navigation, 3, 0, [['icon'=>'account_tree','label'=>'Planning','route'=>'planning']]); } @endphp
        @foreach($navigation as $item)<a href="{{ isset($item['route']) ? route($item['route']) : ($item['label'] === 'Reports' ? route('reports') : ($item['label'] === 'User Management' ? route('user-management') : ($item['label'] === 'Subscriptions' ? route('subscriptions') : ($item['label'] === 'School Settings' ? route('school-settings') : '#')))) }}" class="flex min-h-11 items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-white/10 font-bold text-white shadow-[inset_3px_0_0_#55c79e]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span class="material-symbols-outlined mr-3 text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a> @if(($item['label'] ?? '') === 'Dashboard')<details class="group"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[.55rem] px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-3 text-[20px]">account_balance_wallet</span><span class="flex-1">Finance</span><span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-1 space-y-1"><a href="{{ route('budget') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">account_balance</span>Budget</a><a href="{{ route('accounting') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">request_quote</span>Accounting</a><a href="{{ route('chart-of-accounts') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">list_alt</span>Chart of Accounts</a><a href="{{ route('allotment-registry') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">menu_book</span>Allotment Registry</a><a href="{{ route('cash') }}" class="ml-8 flex items-center rounded-[.55rem] px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white"><span class="material-symbols-outlined mr-2 text-[17px]">payments</span>Cash</a></div></details>@endif @endforeach
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
        <section class="max-w-3xl rounded border border-outline-variant/30 bg-white p-6">
            <div class="flex items-start gap-4">
                <span class="material-symbols-outlined text-4xl {{ $connected ? 'text-secondary' : ($needsReconnect ? 'text-error' : 'text-on-surface-variant') }}">{{ $connected ? 'cloud_done' : ($needsReconnect ? 'cloud_off' : 'cloud') }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold">{{ $connected ? 'Connected' : ($needsReconnect ? 'Connection needs to be renewed' : 'Not connected') }}</h2>
                    @if($connection)
                        <p class="mt-1 text-sm text-on-surface-variant">Google account: <span class="font-semibold text-on-surface">{{ $connection->google_email ?: 'unknown' }}</span></p>
                        <p class="mt-1 text-xs text-on-surface-variant">Connected {{ $connection->connected_at?->format('M d, Y') }}. Folder: ProcMS, with Backup, Logo and Files inside.</p>
                    @else
                        <p class="mt-1 text-sm text-on-surface-variant">You must connect your Google Drive before you can upload files. ProcMS only sees the folders and files it creates itself, never the rest of your Drive.</p>
                    @endif
                    @unless($configured)<p class="mt-3 rounded border border-error/30 bg-error/5 px-3 py-2 text-xs text-error">Google Drive is not set up on this server yet. Ask the system administrator.</p>@endunless
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('drive-files.index') }}" class="inline-flex items-center gap-2 rounded border border-primary/40 px-4 py-2.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]">folder_open</span>My Drive Files</a>
                        @if(auth()->user()->hasAccess('backup'))<a href="{{ route('backup.index') }}" class="inline-flex items-center gap-2 rounded border border-primary/40 px-4 py-2.5 text-xs font-semibold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[18px]">backup</span>Database Backup</a>@endif
                        @if($configured)<a href="{{ route('google-drive.redirect') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">link</span>{{ $needsReconnect ? 'Reconnect Google Drive' : ($connected ? 'Connect a different account' : 'Connect Google Drive') }}</a>@endif
                        @if($connection)
                            <form method="POST" action="{{ route('google-drive.disconnect') }}" onsubmit="return confirm('Disconnect Google Drive? Your files stay in your Drive.')">@csrf @method('DELETE')<button class="inline-flex items-center gap-2 rounded border border-outline-variant px-4 py-2.5 text-xs font-semibold hover:bg-surface-container"><span class="material-symbols-outlined text-[18px]">link_off</span>Disconnect</button></form>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div></main></div>
@include('partials.profile-menu')
@include('partials.mobile-nav')
</body>
</html>
