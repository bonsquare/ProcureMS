<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Procurement') · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb','surface-low':'#eef3f7','surface-container':'#e4ebf1','surface-high':'#d8e2ea',primary:'#103967','primary-container':'#174b82',action:'#286da8',secondary:'#369878',attention:'#cc8535',error:'#b64a50','on-surface':'#172538','on-surface-variant':'#536273','outline-variant':'#cbd7e1'},fontFamily:{inter:['Inter','sans-serif']}}}};
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/procurement.css') }}">
    @include('partials.input-fixes')
    @stack('head')
</head>
<body class="bg-surface font-inter text-on-surface antialiased">
    <a href="#main-content" class="civic-skip-link">Skip to main content</a>
    @include('partials.procurement.navigation')

    <div class="md:pl-60">
        <header class="civic-topbar">
            <div class="flex min-w-0 items-center gap-3">
                <button id="civic-menu-trigger" type="button" class="civic-icon-button md:hidden" aria-label="Open main navigation" aria-controls="civic-navigation" aria-expanded="false">
                    <span class="material-symbols-outlined" aria-hidden="true">menu</span>
                </button>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-action">Procurement Workspace</p>
                    <p class="truncate text-sm font-semibold text-on-surface">@yield('page-title', 'Overview')</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(($showProcurementSchoolFilter ?? false) && ($isMasterUser ?? false))
                    <form method="GET" action="{{ url()->current() }}" class="hidden items-end gap-2 md:flex" aria-label="School scope">
                        @foreach(request()->except(['school_id', 'page']) as $name => $value)
                            @if(!is_array($value))
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label class="text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant">School
                            <select name="school_id" class="mt-1 h-9 max-w-48 rounded-lg border border-outline-variant bg-white px-2 text-xs font-semibold text-on-surface" onchange="this.form.submit()">
                                <option value="">All schools</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" @selected((int) ($selectedSchoolId ?? 0) === $school->id)>{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button class="h-9 rounded-lg bg-primary px-3 text-xs font-bold text-white">Apply</button></noscript>
                    </form>
                @endif
                @yield('header-actions')
                @php $menuUser = auth()->user(); @endphp
                <div class="relative" id="civic-user-menu-wrap">
                    <button id="civic-user-menu-button" type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white transition hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-action/50" aria-label="User menu" aria-haspopup="menu" aria-expanded="false" aria-controls="civic-user-menu">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person</span>
                    </button>
                    <div id="civic-user-menu" role="menu" class="absolute right-0 top-11 z-50 hidden w-64 rounded-xl border border-outline-variant/60 bg-white p-2 text-xs shadow-xl">
                        <div class="border-b border-outline-variant/40 px-3 pb-2.5 pt-2">
                            <p class="truncate text-sm font-bold">{{ $menuUser?->name }}</p>
                            <p class="truncate text-on-surface-variant">{{ $menuUser?->email }}</p>
                            <p class="mt-1.5 inline-flex rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ str($menuUser?->role)->replace('_', ' ')->title() }}</p>
                            @if($menuUser?->school)<p class="mt-1 truncate text-on-surface-variant">{{ $menuUser->school->name }}</p>@endif
                        </div>
                        <a role="menuitem" href="{{ route('school-settings') }}" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">settings</span>School Settings</a>
                        @if($menuUser?->role === 'master_user')
                            @php $pendingTransfers = \App\Models\StationTransferRequest::where('status', 'pending')->count(); @endphp
                            <a role="menuitem" href="{{ route('transfer-requests') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">swap_horiz</span>Transfer requests @if($pendingTransfers)<span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $pendingTransfers }}</span>@endif</a>
                        @else
                            <a role="menuitem" href="{{ route('station-transfer') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">swap_horiz</span>Station transfer</a>
                        @endif
                        @if($menuUser?->role === 'master_user')
                            <a role="menuitem" href="{{ route('user-management') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">manage_accounts</span>User Management</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left font-semibold hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">logout</span>Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="min-h-screen px-4 pb-16 pt-24 md:px-6 lg:px-8">
            <div class="mx-auto max-w-[1600px]">
                @unless($__env->hasSection('hide-module-tabs'))
                    @include('partials.procurement.module-tabs')
                @endunless
                @if(($showProcurementSchoolFilter ?? false) && ($isMasterUser ?? false))
                    <form method="GET" action="{{ url()->current() }}" class="mb-4 grid gap-2 rounded-xl border border-outline-variant/60 bg-white p-3 md:hidden" aria-label="Mobile school scope">
                        @foreach(request()->except(['school_id', 'page']) as $name => $value)
                            @if(!is_array($value))
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label class="text-xs font-bold">School
                            <select name="school_id" class="mt-1 min-h-11 w-full rounded-lg border border-outline-variant bg-white px-3 text-sm" onchange="this.form.submit()">
                                <option value="">All schools</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" @selected((int) ($selectedSchoolId ?? 0) === $school->id)>{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button class="min-h-11 rounded-lg bg-primary px-3 text-xs font-bold text-white">Apply school</button></noscript>
                    </form>
                @endif
                @if(session('error'))
                    <div role="alert" class="civic-alert civic-alert--error" style="border-color:#e4b4b7;background:#f8e2e4;color:#8a2f35">{{ session('error') }}</div>
                @endif
                @if(session('success') && ! $__env->hasSection('flash-handled'))
                    <div role="status" class="civic-alert civic-alert--success">{{ session('success') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>

    <div id="civic-navigation-backdrop" class="civic-nav-backdrop hidden" aria-hidden="true"></div>
    <script>
        (() => {
            const trigger = document.getElementById('civic-menu-trigger');
            const navigation = document.getElementById('civic-navigation');
            const backdrop = document.getElementById('civic-navigation-backdrop');
            if (!trigger || !navigation || !backdrop) return;
            const close = () => {
                navigation.classList.remove('civic-nav--open');
                backdrop.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.setAttribute('aria-label', 'Open main navigation');
            };
            const open = () => {
                navigation.classList.add('civic-nav--open');
                backdrop.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                trigger.setAttribute('aria-label', 'Close main navigation');
                navigation.querySelector('a')?.focus();
            };
            trigger.addEventListener('click', () => trigger.getAttribute('aria-expanded') === 'true' ? close() : open());
            backdrop.addEventListener('click', close);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                    close();
                    trigger.focus();
                }
            });
        })();
    </script>
    @stack('scripts')
<script>
    (() => {
        const button = document.getElementById('civic-user-menu-button');
        const menu = document.getElementById('civic-user-menu');
        if (!button || !menu) return;
        const close = () => { menu.classList.add('hidden'); button.setAttribute('aria-expanded', 'false'); };
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const opened = menu.classList.toggle('hidden') === false;
            button.setAttribute('aria-expanded', String(opened));
            if (opened) menu.querySelector('[role=menuitem]')?.focus();
        });
        document.addEventListener('click', (event) => { if (!menu.contains(event.target)) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !menu.classList.contains('hidden')) { close(); button.focus(); } });
    })();
</script>
</body>
</html>
