<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Procurement') · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config={theme:{extend:{colors:{surface:'#f5f8fb','surface-low':'#eef3f7','surface-container':'#e4ebf1','surface-high':'#d8e2ea',primary:'#103967','primary-container':'#174b82','action:'#286da8',secondary:'#369878',attention:'#cc8535',error:'#b64a50','on-surface':'#172538','on-surface-variant':'#536273','outline-variant':'#cbd7e1'},fontFamily:{inter:['Inter','sans-serif']}}}};
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

    <div class="md:pl-72">
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
                @yield('header-actions')
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white" aria-label="Signed-in user">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person</span>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="min-h-screen px-4 pb-16 pt-24 md:px-6 lg:px-8">
            <div class="mx-auto max-w-[1600px]">
                @include('partials.procurement.module-tabs')
                @if(session('success'))
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
</body>
</html>
